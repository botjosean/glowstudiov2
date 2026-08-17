<?php

namespace App\Support\Assistant;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
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

    /**
     * How many times one reply may be sent back to the model for claiming
     * something the database does not support.
     *
     * Bounded separately from the iteration limit, and much lower, because the
     * two failures are different. Measured on 2026-08-12 across six scripted
     * conversations: a model that will not stop asserting a booking it never
     * made does not recover on the third nudge either — it rephrases the same
     * sentence until the loop runs out, and then the client waits for a whole
     * round trip per attempt before being handed to a person anyway. Spending
     * the remaining rounds on tool calls that might actually book is worth
     * more than spending them on nudges that historically never land.
     */
    private const MAX_CORRECTIONS = 2;

    public function __construct(
        private readonly ChatModel $model,
        private readonly AssistantTools $tools,
        private readonly SystemPrompt $prompt,
        private readonly KapsoClient $kapso,
        private readonly Receptionist $receptionist,
    ) {}

    /**
     * @return string|null the reply, or null when a person from the salon has
     *                     taken the conversation over and the assistant must stay quiet
     *
     * @throws AssistantUnavailable
     */
    public function reply(InboundMessage $message, Provider $provider): ?string
    {
        $turns = $this->turns($message);
        $humanReplied = $this->humanTookOver($turns);

        // A provider in receptionist mode never reaches the model, the tools
        // or the fabricated-booking guard: it acknowledges, asks, and hands
        // over. The branch sits here, after the history is loaded, so the
        // receptionist gets the same "is a person already answering?" answer
        // the agent does — sending an intake on top of the professional's own
        // reply is the talking-over-each-other failure this check exists for.
        if ($provider->botIsReceptionist()) {
            return $this->receptionist->reply($message, $provider, $humanReplied);
        }

        $context = ToolContext::for($provider, (string) $message->fromPhone, $message->contactName);

        if ($humanReplied) {
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

        // What actually happened on the server during *this* reply. Reset per
        // call, not per iteration: a booking made two iterations ago still
        // justifies talking about it now.
        $bookedId = null;
        $cancelled = false;
        $corrections = 0;
        $toolsRun = [];

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

                $correction = $this->contradictedByTheDatabase($answer, $context, $bookedId !== null, $cancelled);

                if ($correction !== null) {
                    if ($corrections >= self::MAX_CORRECTIONS) {
                        // Out of nudges and still asserting something untrue.
                        // Handing the conversation to a person is a worse
                        // answer than a good one and a far better one than a
                        // client walking in for an appointment nobody has.
                        Log::warning('WhatsApp assistant would not stop asserting a booking the database does not have; handing off.', [
                            'provider' => $context->provider->slug,
                            'model' => config('services.assistant.model'),
                        ]);

                        throw AssistantUnavailable::permanent('The model kept claiming a booking that was never made.');
                    }

                    // Caught for real on 2026-08-12: gemini-2.5-flash-lite told
                    // a client "¡Listo! Cita agendada..." twice in one
                    // conversation without ever calling crear_cita — the model
                    // proposes, the server executes (see AssistantTools), and
                    // this is that rule violated from the other side: the
                    // client is told something happened that never reached the
                    // database. Rather than relay it, push the model to either
                    // act for real or say what is actually true.
                    $corrections++;

                    Log::warning('WhatsApp assistant asserted something the database does not support; sending it back.', [
                        'provider' => $context->provider->slug,
                        'correction' => $corrections,
                    ]);

                    $messages[] = ['role' => 'system', 'content' => $correction];

                    continue;
                }

                $reply = $this->asWhatsAppText(
                    $bookedId !== null
                        ? $this->withTheBookingItActuallyMade($answer, $bookedId, $context)
                        : $answer,
                );

                // One line per answered message, so the questions this project
                // has had to answer by grepping ("is the new model even being
                // used?", "did the guardrail fire on a real client?") are a
                // single grep away instead of an archaeology session. Nothing
                // here identifies the client: tool names and counters only.
                Log::info('WhatsApp assistant answered.', [
                    'provider' => $context->provider->slug,
                    'model' => config('services.assistant.model'),
                    'iterations' => $iteration,
                    'tools' => $toolsRun,
                    'corrections' => $corrections,
                    'booked' => $bookedId !== null,
                ]);

                return $reply;
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
                $toolsRun[] = $executed['tool'];

                if ($executed['failed']) {
                    continue;
                }

                // `ya_estaba_reservada` counts too: the tool answers with the
                // appointment the client already had, so a confirmation about
                // it is true, and suppressing it would leave her thinking
                // nothing happened.
                if ($executed['tool'] === 'crear_cita' && isset($executed['result']['cita_id'])) {
                    $bookedId = (int) $executed['result']['cita_id'];
                }

                if ($executed['tool'] === 'cancelar_cita') {
                    $cancelled = true;
                }
            }
        }

        throw AssistantUnavailable::permanent("The model kept calling tools past {$maxIterations} rounds.");
    }

    /**
     * WhatsApp bold is one asterisk, not two.
     *
     * The prompt says so in as many words, and models keep writing Markdown
     * anyway — `claude-haiku-4.5` did it in two of three answers within
     * minutes of going live. WhatsApp does not render `**texto**`: the client
     * sees the asterisks, which reads as a broken robot. The prompt keeps
     * asking, because a model that writes it right needs no cleanup; but
     * whether it lands is no longer left to the model, for the same reason
     * nothing else on this path is.
     *
     * Only the unambiguous case is touched. `__` and `##` are left alone:
     * they have never been seen in a real reply, and a rewrite rule that
     * fires on text nobody sent is a bug waiting for its first client.
     */
    private function asWhatsAppText(string $reply): string
    {
        return preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/su', '*$1*', $reply) ?? $reply;
    }

    /**
     * The correction to send back when a final, tool-less answer asserts
     * something the appointments table does not support — or null when the
     * answer is safe to relay.
     *
     * **The verdict is the database, not the wording.** The first version of
     * this guard asked only "did crear_cita run this turn?", and that was
     * wrong in the most ordinary case there is: a client asking "¿sigue en pie
     * mi cita?" gets a true answer built from listar_mis_citas or from the
     * CLIENTA KNOWN block, no crear_cita anywhere, and the guard blocked it.
     * Four such phrasings were reproduced against the released regex before
     * this rewrite. Asking the table instead makes a true statement pass and a
     * fabricated one fail, which is the distinction that was wanted all along.
     *
     * Because the verdict is now a fact, the wording test can afford to be
     * blunt and wide — English included, which the Spanish-only original
     * missed entirely even though the assistant is told to answer in English
     * when written to in English.
     */
    private function contradictedByTheDatabase(string $answer, ToolContext $context, bool $booked, bool $cancelled): ?string
    {
        if ($booked && $cancelled) {
            return null;
        }

        $statements = $this->statementsIn($answer);

        if ($statements === []) {
            return null;
        }

        $hasLiveAppointments = null;

        if (! $booked && $this->readsAsABooking($statements)) {
            $hasLiveAppointments = $this->clientHasLiveAppointments($context);

            if (! $hasLiveAppointments) {
                return 'No llamaste a crear_cita y esta clienta no tiene ninguna cita registrada, '
                    .'asi que no puedes decirle que quedo agendada, confirmada ni registrada. '
                    .'Si ya confirmo servicio, dia, hora y nombre, llama a crear_cita ahora mismo. '
                    .'Si falta algun dato, pideselo primero.';
            }
        }

        if (! $cancelled && $this->readsAsACancellation($statements)) {
            $hasLiveAppointments ??= $this->clientHasLiveAppointments($context);

            if ($hasLiveAppointments) {
                return 'No llamaste a cancelar_cita y esta clienta sigue teniendo su cita activa, '
                    .'asi que no puedes decirle que quedo cancelada. Si de verdad quiere cancelarla, '
                    .'busca cual es con listar_mis_citas y llama a cancelar_cita.';
            }
        }

        return null;
    }

    /**
     * The parts of an answer that assert something, questions dropped.
     *
     * Split per sentence rather than judged whole, because the model routinely
     * ends a message with an invitation — "¡Listo! Tu cita quedó agendada.
     * ¿Necesitas algo más?" — and a single question mark anywhere used to
     * exempt the entire message, which is precisely the shape of the reply
     * that started this. A *proposal* awaiting a yes ("¿Te la agendo?") is
     * still exempt, because it is a question in itself.
     *
     * @return list<string>
     */
    private function statementsIn(string $answer): array
    {
        $sentences = preg_split('/(?<=[.!?])\s+|\n+/u', $answer, -1, PREG_SPLIT_NO_EMPTY) ?: [$answer];

        return array_values(array_filter(
            $sentences,
            static fn (string $sentence): bool => ! str_contains($sentence, '?'),
        ));
    }

    /**
     * Whether any statement reads as telling the client a booking exists.
     *
     * "pendiente de confirmación" is AssistantTools' own vocabulary (see
     * createBooking's `estado`) and the model echoes it verbatim, so it counts
     * on its own: a live run showed it paired with "queda", "quedó", "está"
     * and "tiene" in different turns, too many verbs to chase one by one.
     *
     * @param  list<string>  $statements
     */
    private function readsAsABooking(array $statements): bool
    {
        return $this->anyMatches(
            '/\b(cita|turno|appointment|booking)\b[^.!]{0,80}\b(agendad[ao]|confirmad[ao]|reservad[ao]|registrad[ao]|apartad[ao]|separad[ao]|booked|scheduled|confirmed|reserved)\b'
            .'|\b(agendad[ao]|confirmad[ao]|reservad[ao]|registrad[ao]|apartad[ao]|separad[ao]|booked|scheduled|confirmed|reserved)\b[^.!]{0,80}\b(cita|turno|appointment|booking)\b'
            .'|\bpendiente\s+de\s+confirmaci[oó]n\b'
            .'|\byou\s*(?:\x27re|are)\s+all\s+set\b/iu',
            $statements,
        );
    }

    /**
     * @param  list<string>  $statements
     */
    private function readsAsACancellation(array $statements): bool
    {
        return $this->anyMatches(
            '/\b(cita|turno|appointment|booking)\b[^.!]{0,80}\b(cancelad[ao]|anulad[ao]|eliminad[ao]|cancell?ed)\b'
            .'|\b(cancelad[ao]|anulad[ao]|eliminad[ao]|cancell?ed)\b[^.!]{0,80}\b(cita|turno|appointment|booking)\b/iu',
            $statements,
        );
    }

    /**
     * @param  list<string>  $statements
     */
    private function anyMatches(string $pattern, array $statements): bool
    {
        foreach ($statements as $statement) {
            if (preg_match($pattern, $statement) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether this client already has an appointment that has not happened yet
     * — the ground truth behind any sentence about "your appointment".
     *
     * Scoped exactly like the tools are (provider + the phone the webhook
     * established, see ToolContext), so it can no more see somebody else's
     * bookings than listar_mis_citas can.
     */
    private function clientHasLiveAppointments(ToolContext $context): bool
    {
        return Appointment::query()
            ->where('provider_id', $context->provider->id)
            ->where('client_phone', $context->storedPhone)
            ->whereIn('status', AppointmentStatus::blocking())
            ->where('starts_at', '>=', now()->utc())
            ->exists();
    }

    /**
     * Makes sure a reply that follows a real booking actually tells the client
     * when it is.
     *
     * The mirror image of the fabricated confirmation, and seen in the same
     * benchmark: crear_cita succeeded, the row was in Postgres, and the model
     * answered with a question instead — the appointment existed and the
     * client had no way to know. When the answer already names the hour it is
     * left untouched, so the assistant's own voice carries the good case and
     * this only fills a silence.
     *
     * The facts come from the row, never from the model's prose, so a booking
     * the model narrates on the wrong day contradicts itself in front of the
     * client instead of being discovered a week later at the salon door.
     */
    private function withTheBookingItActuallyMade(string $answer, int $appointmentId, ToolContext $context): string
    {
        $appointment = Appointment::query()
            ->where('id', $appointmentId)
            ->where('provider_id', $context->provider->id)
            ->first();

        if ($appointment === null) {
            return $answer;
        }

        $local = $appointment->starts_at->setTimezone($context->provider->timezone);

        $mentionsTheHour = preg_match(
            '/\b'.$local->format('g').'(?::'.$local->format('i').')?\s*(?:a\.?\s?m\.?|p\.?\s?m\.?|h)\b/iu',
            $answer,
        ) === 1;

        if ($mentionsTheHour) {
            return $answer;
        }

        Log::info('WhatsApp assistant booked without telling the client when; adding the details.', [
            'provider' => $context->provider->slug,
        ]);

        return $answer."\n\n".sprintf(
            '%s el %s a las %s. Queda pendiente de que %s te la confirme.',
            $appointment->service_name,
            $local->locale('es')->isoFormat('dddd D [de] MMMM'),
            $local->format('g:i A'),
            $context->provider->public_name,
        );
    }

    /**
     * @param  array<mixed>  $call
     * @return array{tool: string, failed: bool, result: array<string, mixed>, message: array<string, mixed>}
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
            'result' => $result,
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
