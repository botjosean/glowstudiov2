<?php

namespace App\Support\Assistant;

use App\Models\Provider;
use App\Support\Kapso\InboundMessage;
use App\Support\Kapso\KapsoClient;
use Illuminate\Support\Facades\Log;

/**
 * Turns one inbound WhatsApp message into one reply.
 *
 * Runs the tool-calling loop: ask the model, carry out whatever it proposes,
 * feed the results back, until it answers in words. The loop is bounded — a
 * model that keeps calling tools forever would otherwise hold a queue worker
 * and burn tokens with nothing to show the client.
 */
class Coordinator
{
    /**
     * How many past turns to replay, when nothing is configured.
     *
     * Small on purpose. Every round of the tool-calling loop resends the whole
     * conversation, so history is multiplied by the number of rounds, and it is
     * the single biggest lever on tokens-per-minute — the limit that actually
     * bites in practice.
     */
    private const DEFAULT_HISTORY_LIMIT = 8;

    public function __construct(
        private readonly GroqClient $groq,
        private readonly AssistantTools $tools,
        private readonly SystemPrompt $prompt,
        private readonly KapsoClient $kapso,
    ) {}

    /**
     * @throws AssistantUnavailable
     */
    public function reply(InboundMessage $message, Provider $provider): string
    {
        $context = ToolContext::for($provider, (string) $message->fromPhone, $message->contactName);

        $messages = [
            ['role' => 'system', 'content' => $this->prompt->for($provider)],
            ...$this->history($message),
            ['role' => 'user', 'content' => $message->text],
        ];

        $definitions = $this->tools->definitions();
        $maxIterations = (int) (config('services.groq.max_iterations') ?: 6);

        for ($iteration = 1; $iteration <= $maxIterations; $iteration++) {
            $assistant = $this->groq->chat($messages, $definitions);

            $toolCalls = $assistant['tool_calls'] ?? null;

            if (! is_array($toolCalls) || $toolCalls === []) {
                $answer = trim((string) ($assistant['content'] ?? ''));

                if ($answer === '') {
                    throw AssistantUnavailable::permanent('The model answered with neither text nor a tool call.');
                }

                return $answer;
            }

            // `reasoning` is deliberately not echoed back: it is Groq's own
            // extension, not part of the shape the API contract guarantees to
            // accept, and the loop works without it.
            $messages[] = [
                'role' => 'assistant',
                'content' => $assistant['content'] ?? null,
                'tool_calls' => $toolCalls,
            ];

            foreach ($toolCalls as $call) {
                $messages[] = $this->execute($call, $context);
            }
        }

        throw AssistantUnavailable::permanent("The model kept calling tools past {$maxIterations} rounds.");
    }

    /**
     * @param  array<mixed>  $call
     * @return array<string, mixed> the `tool` message to feed back
     */
    private function execute(array $call, ToolContext $context): array
    {
        $name = (string) ($call['function']['name'] ?? '');
        $decoded = json_decode((string) ($call['function']['arguments'] ?? '{}'), true);
        $arguments = is_array($decoded) ? $decoded : [];

        $result = $this->tools->run($name, $arguments, $context);

        // The tool name and whether it worked, never the arguments or the
        // result: those carry the client's name, phone and plans.
        Log::info('WhatsApp assistant ran a tool.', [
            'tool' => $name,
            'provider' => $context->provider->slug,
            'failed' => isset($result['error']),
        ]);

        return [
            'role' => 'tool',
            'tool_call_id' => (string) ($call['id'] ?? ''),
            'content' => json_encode($result, JSON_UNESCAPED_UNICODE) ?: '{}',
        ];
    }

    /**
     * Recent turns of this conversation, straight from Kapso.
     *
     * No local history table exists on purpose: Kapso already stores and backs
     * up every message, so a second copy would be duplicated state to keep in
     * sync for no gain. Only the conversational transcript is needed — the
     * appointment state that matters lives in the appointments table, where the
     * website put it.
     *
     * @return list<array<string, mixed>>
     */
    private function history(InboundMessage $message): array
    {
        if ($message->conversationId === null) {
            return [];
        }

        try {
            $turns = $this->kapso->recentMessages(
                $message->conversationId,
                (int) (config('services.groq.history_messages') ?: self::DEFAULT_HISTORY_LIMIT),
            );
        } catch (\Throwable $exception) {
            // A conversation without its history is worse than none, but far
            // better than no reply at all.
            Log::warning('Could not load WhatsApp history; answering without it.', [
                'reason' => $exception->getMessage(),
            ]);

            return [];
        }

        $history = [];

        foreach ($turns as $turn) {
            // Kapso has already stored the message that triggered this webhook,
            // so it comes back in the history too; it is appended as the final
            // user turn instead, where the model expects it.
            if (($turn['id'] ?? null) === $message->wamid) {
                continue;
            }

            $text = trim((string) ($turn['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            // Leftovers from the echo-only gate, before the assistant existed.
            // Feeding them back would invite the model to imitate them. Safe to
            // delete once no live conversation contains them any more.
            if (($turn['direction'] ?? null) === 'outbound' && str_starts_with($text, 'Recibí: ')) {
                continue;
            }

            $history[] = [
                'role' => ($turn['direction'] ?? null) === 'outbound' ? 'assistant' : 'user',
                'content' => $text,
            ];
        }

        return $history;
    }
}
