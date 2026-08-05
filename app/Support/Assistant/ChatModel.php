<?php

namespace App\Support\Assistant;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The chat model behind the assistant, over the OpenAI-compatible protocol.
 *
 * Provider-neutral on purpose: base URL, model and key are all configuration,
 * so moving between Groq, OpenRouter, Cerebras or anyone else costs three
 * environment variables and no code. That is not hypothetical — this project
 * has already had to move once, when Groq's paid tier stopped accepting
 * upgrades mid-build.
 *
 * Structured outputs are deliberately not used: providers document that strict
 * schema decoding cannot be combined with tool use, so argument validation
 * happens on the server (see AssistantTools) rather than being delegated to
 * constrained decoding that would silently not apply here.
 */
class ChatModel
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
        $config = config('services.assistant');

        $apiKey = $config['api_key'] ?? null;

        if (! is_string($apiKey) || $apiKey === '') {
            throw AssistantUnavailable::permanent('The assistant model API key is not configured.');
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
        // provider is a base_url away — and sending an unknown field to a
        // provider that validates strictly would be a 400 on every message.
        $effort = $config['reasoning_effort'] ?? null;

        if (is_string($effort) && $effort !== '') {
            $payload['reasoning_effort'] = $effort;
        }

        try {
            $response = $this->request($apiKey, (int) ($config['timeout'] ?? 30))
                ->post(rtrim((string) $config['base_url'], '/').'/chat/completions', $payload);
        } catch (ConnectionException $exception) {
            // A timeout says nothing about whether the request was bad, so it
            // is worth one more attempt.
            throw AssistantUnavailable::transient('The model did not respond: '.$exception->getMessage());
        }

        return $this->messageFrom($response);
    }

    private function request(string $apiKey, int $timeout): PendingRequest
    {
        return Http::withToken($apiKey)
            ->withHeaders([
                // Attribution headers OpenRouter reads and everyone else
                // ignores; they make this app identifiable in its dashboard.
                'HTTP-Referer' => (string) config('app.url'),
                'X-Title' => (string) config('app.name').' WhatsApp',
            ])
            ->acceptJson()
            ->asJson()
            ->timeout($timeout)
            ->connectTimeout(5);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws AssistantUnavailable
     */
    private function messageFrom(Response $response): array
    {
        if ($response->status() === 429) {
            // Providers return the wait in seconds; honouring it is cheaper
            // than guessing and being throttled again.
            $retryAfter = (int) $response->header('retry-after');

            throw AssistantUnavailable::transient(
                'The model provider rate limited us.',
                $retryAfter > 0 ? $retryAfter : null,
            );
        }

        if ($response->status() === 400) {
            // A malformed tool call comes back as a 400. Retrying reproduces it,
            // so this is terminal.
            throw AssistantUnavailable::permanent(
                'The model provider rejected the request: '.($response->json('error.message') ?? 'unknown reason')
            );
        }

        if ($response->serverError()) {
            throw AssistantUnavailable::transient("The model provider returned HTTP {$response->status()}.");
        }

        if ($response->failed()) {
            throw AssistantUnavailable::permanent("The model provider returned HTTP {$response->status()}.");
        }

        $message = $response->json('choices.0.message');

        if (! is_array($message)) {
            // OpenRouter reports an upstream failure inside a 200 body, so a
            // missing message is not necessarily a malformed response — it can
            // be a provider outage worth retrying.
            $error = $response->json('error.message');

            if (is_string($error) && $error !== '') {
                throw AssistantUnavailable::transient('The model provider failed upstream: '.$error);
            }

            throw AssistantUnavailable::permanent('The model returned no assistant message.');
        }

        return $message;
    }
}
