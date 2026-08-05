<?php

namespace App\Support\Assistant;

use App\Models\Provider;
use App\Support\Kapso\InboundMessage;
use App\Support\Kapso\KapsoClient;
use Carbon\CarbonImmutable;
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

    /**
     * How long the assistant stays out of a conversation after a person from the
     * salon has answered by hand.
     *
     * Without this the two talk over each other: the professional replies from
     * her own phone, the client writes again, and the assistant answers too —
     * two voices, possibly contradicting each other, in front of a client. An
     * hour is long enough to cover a real back-and-forth and short enough that
     * the assistant picks up tomorrow's messages again on its own.
     */
    private const HUMAN_HANDOVER_MINUTES = 60;

    public function __construct(
        private readonly ChatModel $model,
        private readonly AssistantTools $tools,
        private readonly SystemPrompt $prompt,
        private readonly KapsoClient $kapso,
    ) {}

    /**
     * @return string|null the reply, or null when a person from the salon has
     *                     taken the conversation over and the assistant must stay quiet
     *
     * @throws AssistantUnavailable
     */
    public function reply(InboundMessage $message, Provider $provider): ?string
    {
        $context = ToolContext::for($provider, (string) $message->fromPhone, $message->contactName);

        $turns = $this->turns($message);

        if ($this->humanTookOver($turns)) {
            Log::info('A person from the salon answered by hand recently; staying quiet.', [
                'phone_number_id' => $message->phoneNumberId,
                'provider' => $provider->slug,
            ]);

            return null;
        }

        $messages = [
            ['role' => 'system', 'content' => $this->prompt->for($provider)],
            ...$this->history($turns, $message),
            ['role' => 'user', 'content' => $message->text],
        ];

        $definitions = $this->tools->definitions();
        $maxIterations = (int) (config('services.assistant.max_iterations') ?: 6);

        for ($iteration = 1; $iteration <= $maxIterations; $iteration++) {
            $assistant = $this->model->chat($messages, $definitions);

            $toolCalls = $assistant['tool_calls'] ?? null;

            if (! is_array($toolCalls) || $toolCalls === []) {
                $answer = trim((string) ($assistant['content'] ?? ''));

                if ($answer === '') {
                    throw AssistantUnavailable::permanent('The model answered with neither text nor a tool call.');
                }

                return $answer;
            }

            // `reasoning` is deliberately not echoed back: it is a provider
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

        // The tool name and, when it failed, *why*. Never the arguments or a
        // successful result: those carry the client's name, phone and plans. The
        // error strings are written here in this codebase and contain no client
        // data — and without them a failure is undiagnosable after the fact,
        // which is exactly what happened the first time a real client hit one.
        Log::info('WhatsApp assistant ran a tool.', array_filter([
            'tool' => $name,
            'provider' => $context->provider->slug,
            'failed' => isset($result['error']),
            'why' => $result['error'] ?? null,
        ], static fn ($value): bool => $value !== null));

        return [
            'role' => 'tool',
            'tool_call_id' => (string) ($call['id'] ?? ''),
            'content' => json_encode($result, JSON_UNESCAPED_UNICODE) ?: '{}',
        ];
    }

    /**
     * Whether somebody at the salon answered this conversation by hand recently.
     *
     * The signal is empirical: a message the salon typed on their own phone
     * arrives back through Kapso as outbound *with* a `from` (their number),
     * while a message this app sent through the API arrives outbound with no
     * `from` at all. Observed on the live numbers, which run in coexistence
     * mode, and worth re-checking if Kapso ever changes that shape.
     *
     * @param  list<array{id: string, direction: string, text: string, at: int, from: string}>  $turns
     */
    private function humanTookOver(array $turns): bool
    {
        $cutoff = CarbonImmutable::now()->subMinutes(self::HUMAN_HANDOVER_MINUTES)->getTimestamp();

        foreach ($turns as $turn) {
            if ($turn['direction'] === 'outbound' && $turn['from'] !== '' && $turn['at'] >= $cutoff) {
                return true;
            }
        }

        return false;
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
     * @return list<array{id: string, direction: string, text: string, at: int, from: string}>
     */
    private function turns(InboundMessage $message): array
    {
        if ($message->conversationId === null) {
            return [];
        }

        try {
            return $this->kapso->recentMessages(
                $message->conversationId,
                (int) (config('services.assistant.history_messages') ?: self::DEFAULT_HISTORY_LIMIT),
            );
        } catch (\Throwable $exception) {
            // A conversation without its history is worse than none, but far
            // better than no reply at all.
            Log::warning('Could not load WhatsApp history; answering without it.', [
                'reason' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param  list<array{id: string, direction: string, text: string, at: int, from: string}>  $turns
     * @return list<array<string, mixed>>
     */
    private function history(array $turns, InboundMessage $message): array
    {
        // An explicit line in the sand. Kapso stores every message a business
        // ever exchanged and offers no way to delete one, which is right for the
        // business but leaves a test conversation polluting the model's context
        // forever. Setting this to a timestamp makes the assistant treat
        // everything before it as if it had not happened, without destroying
        // anybody's data.
        $since = $this->historyCutoff();

        $history = [];

        foreach ($turns as $turn) {
            if ($since !== null && $turn['at'] < $since) {
                continue;
            }

            // Kapso has already stored the message that triggered this webhook,
            // so it comes back in the history too; it is appended as the final
            // user turn instead, where the model expects it. In a buffered batch
            // the merged message carries the last wamid, so the earlier ones of
            // the batch stay in history where they belong.
            if ($turn['id'] === $message->wamid) {
                continue;
            }

            $text = trim($turn['text']);

            if ($text === '') {
                continue;
            }

            // Leftovers from the echo-only gate, before the assistant existed.
            // Feeding them back would invite the model to imitate them. Safe to
            // delete once no live conversation contains them any more.
            if ($turn['direction'] === 'outbound' && str_starts_with($text, 'Recibí: ')) {
                continue;
            }

            $history[] = [
                'role' => $turn['direction'] === 'outbound' ? 'assistant' : 'user',
                'content' => $text,
            ];
        }

        return $history;
    }

    /**
     * @return int|null unix seconds, or null when no cutoff is configured
     */
    private function historyCutoff(): ?int
    {
        $since = config('services.assistant.history_since');

        if (! is_string($since) || trim($since) === '') {
            return null;
        }

        try {
            return (int) CarbonImmutable::parse($since)->getTimestamp();
        } catch (\Throwable) {
            // A malformed date must not silently drop the whole history.
            Log::warning('ASSISTANT_HISTORY_SINCE is not a valid date; ignoring it.');

            return null;
        }
    }
}
