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
     * salon has answered by hand, when nothing is configured.
     *
     * Without any pause the two talk over each other: the professional replies
     * from her own phone, the client writes again, and the assistant answers too
     * — two voices, possibly contradicting each other, in front of a client.
     *
     * Fifteen minutes, not an hour. An hour was tried and was clearly wrong: the
     * professional sent a single curious message into a conversation and that
     * silenced the assistant while a real client asked four questions about a
     * $200 service and got nothing. The pause has to cover an active
     * back-and-forth, not punish one stray message.
     */
    private const DEFAULT_HANDOVER_MINUTES = 15;

    /**
     * The exact text RespondToWhatsAppMessage sends when the assistant could
     * not answer. Recognising it in the client's own Kapso history is how the
     * assistant knows it already gave up on this conversation, with no second
     * table to keep in sync — Kapso already stores every message this app
     * ever sent (see turns()).
     */
    public const WAIT_MESSAGE = 'Gracias por escribirnos. Ahora mismo no puedo responderte yo, pero ya avisé al salón y una persona te contesta en breve. 💛';

    /**
     * How long the assistant stays out of a conversation after telling a
     * client "a person will answer you", when nothing is configured.
     *
     * Seen for real: the assistant failed once, sent the wait message, and
     * the very next message from the same client hit the same failure again
     * — two "no puedo responderte" in a row, which undermines the hand-off
     * instead of honouring it. A client told a human is coming is expected to
     * wait for that human (or write again later), not get answered by the
     * same assistant that just said it couldn't.
     */
    private const DEFAULT_HANDOFF_PAUSE_MINUTES = 120;

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

        if ($this->recentlyHandedOff($turns)) {
            Log::info('Already told this client a person would answer; staying quiet.', [
                'phone_number_id' => $message->phoneNumberId,
                'provider' => $provider->slug,
            ]);

            return null;
        }

        $messages = [
            ['role' => 'system', 'content' => $this->prompt->for($provider, $message->fromPhone)],
            ...$this->history($turns, $message),
            ['role' => 'user', 'content' => $message->text],
        ];

        return $this->converse($messages, $context);
    }

    /**
     * Runs the tool-calling loop to completion for an already-assembled
     * message list.
     *
     * Split out from reply() so a caller with its own conversation state — a
     * benchmark harness replaying a scripted conversation without a live
     * Kapso thread, for instance — can drive the exact same loop, guardrail
     * included, without a webhook.
     *
     * @param  list<array<string, mixed>>  $messages
     *
     * @throws AssistantUnavailable
     */
    private function converse(array $messages, ToolContext $context): string
    {
        $definitions = $this->tools->definitions();
        $maxIterations = (int) (config('services.assistant.max_iterations') ?: 6);

        // Whether crear_cita succeeded at any point in *this* reply — the
        // only thing that licenses the model to tell the client a booking
        // exists. Reset per call, not per iteration: a booking made two
        // iterations ago still justifies a confirmation now.
        $bookingConfirmed = false;

        for ($iteration = 1; $iteration <= $maxIterations; $iteration++) {
            $assistant = $this->model->chat($messages, $definitions);

            $toolCalls = $assistant['tool_calls'] ?? null;

            if (! is_array($toolCalls) || $toolCalls === []) {
                $answer = trim((string) ($assistant['content'] ?? ''));

                if ($answer === '') {
                    // Transient, not permanent: an empty generation is the one
                    // failure that reliably clears on a second attempt, and
                    // treating it as final sent a client to a human on the
                    // model's first bad roll. The retry is free of the usual
                    // danger — RespondToWhatsAppMessage claims the reply scope
                    // before sending, so a re-run cannot deliver twice.
                    throw AssistantUnavailable::transient('The model answered with neither text nor a tool call.');
                }

                if (! $bookingConfirmed && $this->claimsAConfirmedBooking($answer)) {
                    // Caught for real on 2026-08-12: gemini-2.5-flash-lite told
                    // a client "¡Listo! Cita agendada..." twice in one
                    // conversation without ever calling crear_cita — the model
                    // proposes, the server executes (see AssistantTools), and
                    // this is that rule violated from the other side: the
                    // client is told something happened that never reached the
                    // database. Rather than relay it, push the model to either
                    // actually book or admit it has not, same as any other
                    // tool-less turn — bounded by the same iteration limit.
                    Log::warning('WhatsApp assistant claimed a booking without calling crear_cita this turn; forcing it to actually book.', [
                        'provider' => $context->provider->slug,
                    ]);

                    $messages[] = [
                        'role' => 'system',
                        'content' => 'No llamaste a crear_cita en este turno, asi que no puedes decir que la cita quedo agendada, confirmada o registrada. Si la clienta ya confirmo servicio, dia, hora y nombre, llama a crear_cita ahora mismo. Si falta algun dato, pideselo primero.',
                    ];

                    continue;
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
                $executed = $this->execute($call, $context);
                $messages[] = $executed['message'];

                if ($executed['tool'] === 'crear_cita' && ! $executed['failed']) {
                    $bookingConfirmed = true;
                }
            }
        }

        throw AssistantUnavailable::permanent("The model kept calling tools past {$maxIterations} rounds.");
    }

    /**
     * Whether a final, tool-less answer reads as telling the client a booking
     * is done — "cita agendada", "queda confirmada" and the like — as opposed
     * to *proposing* one and waiting for a yes ("¿Es correcto?"). A question
     * is never treated as a claim of completion, because the model is
     * expected to propose exactly that way before crear_cita is ever called,
     * and flagging that would force a booking through without the client's
     * explicit yes.
     *
     * A heuristic over the model's own wording, not a substitute for the real
     * check (whether crear_cita actually ran) — it only decides which answers
     * are worth holding to that check.
     */
    private function claimsAConfirmedBooking(string $answer): bool
    {
        if (str_contains($answer, '?')) {
            return false;
        }

        return (bool) preg_match(
            '/\bcita\b[^.!]{0,60}\b(agendad[ao]|confirmad[ao]|reservad[ao]|registrad[ao])\b'
            .'|\b(agendad[ao]|confirmad[ao]|reservad[ao]|registrad[ao])\b[^.!]{0,60}\bcita\b'
            // "pendiente de confirmación" is AssistantTools' own vocabulary
            // (see createBooking's `estado`) and the model echoes it verbatim
            // whether or not it actually called the tool, so it is treated as
            // a claim on its own — a live benchmark run showed it paired with
            // "queda", "quedó", "está" and "tiene" in different turns, too
            // many verbs to chase individually.
            .'|\bpendiente\s+de\s+confirmaci[oó]n\b'
            .'|\bqued(?:a|[oó])\s+(?:ya\s+)?(?:registrada|confirmada|agendada)\b/iu',
            $answer,
        );
    }

    /**
     * @param  array<mixed>  $call
     * @return array{tool: string, failed: bool, message: array<string, mixed>}
     */
    private function execute(array $call, ToolContext $context): array
    {
        $name = (string) ($call['function']['name'] ?? '');
        $decoded = json_decode((string) ($call['function']['arguments'] ?? '{}'), true);
        $arguments = is_array($decoded) ? $decoded : [];

        $result = $this->tools->run($name, $arguments, $context);
        $failed = isset($result['error']);

        // The tool name and, when it failed, *why*. Never the arguments or a
        // successful result: those carry the client's name, phone and plans. The
        // error strings are written here in this codebase and contain no client
        // data — and without them a failure is undiagnosable after the fact,
        // which is exactly what happened the first time a real client hit one.
        Log::info('WhatsApp assistant ran a tool.', array_filter([
            'tool' => $name,
            'provider' => $context->provider->slug,
            'failed' => $failed,
            'why' => $result['error'] ?? null,
        ], static fn ($value): bool => $value !== null));

        return [
            'tool' => $name,
            'failed' => $failed,
            'message' => [
                'role' => 'tool',
                'tool_call_id' => (string) ($call['id'] ?? ''),
                'content' => json_encode($result, JSON_UNESCAPED_UNICODE) ?: '{}',
            ],
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
        $minutes = (int) (config('services.assistant.human_handover_minutes') ?: self::DEFAULT_HANDOVER_MINUTES);

        $cutoff = CarbonImmutable::now()->subMinutes($minutes)->getTimestamp();

        foreach ($turns as $turn) {
            if ($turn['direction'] === 'outbound' && $turn['from'] !== '' && $turn['at'] >= $cutoff) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the assistant already told this client "a person will answer
     * you" within the pause window. Matched on the exact wait-message text
     * among this app's own outbound turns (empty `from`, see humanTookOver())
     * so a professional's own reply from her phone never trips this.
     *
     * @param  list<array{id: string, direction: string, text: string, at: int, from: string}>  $turns
     */
    private function recentlyHandedOff(array $turns): bool
    {
        $minutes = (int) (config('services.assistant.handoff_pause_minutes') ?: self::DEFAULT_HANDOFF_PAUSE_MINUTES);

        $cutoff = CarbonImmutable::now()->subMinutes($minutes)->getTimestamp();

        foreach ($turns as $turn) {
            if ($turn['direction'] === 'outbound' && $turn['at'] >= $cutoff && trim($turn['text']) === self::WAIT_MESSAGE) {
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
