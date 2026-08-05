<?php

namespace App\Support\Assistant;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Groq's OpenAI-compatible chat completions, used only for tool calling.
 *
 * Structured outputs are deliberately not used: Groq documents that strict
 * schema decoding cannot be combined with tool use, so argument validation
 * happens on the server (see AssistantTools) rather than being delegated to
 * constrained decoding that would silently not apply here.
 */
class GroqClient
{
    /**
     * Asks for the next assistant turn.
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @return array<string, mixed> the assistant message, with `content` and/or `tool_calls`
     *
     * @throws AssistantUnavailable
     */
    public function chat(array $messages, array $tools): array
    {
        $config = config('services.groq');

        $apiKey = $config['api_key'] ?? null;

        if (! is_string($apiKey) || $apiKey === '') {
            throw AssistantUnavailable::permanent('Groq API key is not configured.');
        }

        $payload = [
            'model' => $config['model'],
            'messages' => $messages,
            'tools' => $tools,
            'tool_choice' => 'auto',
            'temperature' => (float) ($config['temperature'] ?? 0.3),
            'max_completion_tokens' => (int) ($config['max_completion_tokens'] ?? 1024),
        ];

        // reasoning_effort is a gpt-oss extension, only sent when configured.
        // Everything else in this payload is plain OpenAI-compatible, so the
        // provider is a base_url away -- and sending an unknown field to a
        // provider that validates strictly would be a 400 on every message.
        $effort = $config['reasoning_effort'] ?? null;

        if (is_string($effort) && $effort !== '') {
            $payload['reasoning_effort'] = $effort;
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout((int) ($config['timeout'] ?? 30))
                ->connectTimeout(5)
                ->post(rtrim((string) $config['base_url'], '/').'/chat/completions', $payload);
        } catch (ConnectionException $exception) {
            // A timeout says nothing about whether the request was bad, so it
            // is worth one more attempt.
            throw AssistantUnavailable::transient('Groq did not respond: '.$exception->getMessage());
        }

        return $this->messageFrom($response);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws AssistantUnavailable
     */
    private function messageFrom(Response $response): array
    {
        if ($response->status() === 429) {
            // Groq returns the wait in seconds; honouring it is cheaper than
            // guessing and being throttled again.
            $retryAfter = (int) $response->header('retry-after');

            throw AssistantUnavailable::transient(
                'Groq rate limit reached.',
                $retryAfter > 0 ? $retryAfter : null,
            );
        }

        if ($response->status() === 400) {
            // Groq reports a malformed tool call as a 400 carrying
            // `failed_generation`. Retrying reproduces it, so this is terminal.
            throw AssistantUnavailable::permanent(
                'Groq rejected the request: '.($response->json('error.message') ?? 'unknown reason')
            );
        }

        if ($response->serverError()) {
            throw AssistantUnavailable::transient("Groq returned HTTP {$response->status()}.");
        }

        if ($response->failed()) {
            throw AssistantUnavailable::permanent("Groq returned HTTP {$response->status()}.");
        }

        $message = $response->json('choices.0.message');

        if (! is_array($message)) {
            throw AssistantUnavailable::permanent('Groq returned no assistant message.');
        }

        return $message;
    }
}
