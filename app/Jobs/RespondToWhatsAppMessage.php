<?php

namespace App\Jobs;

use App\Models\Provider;
use App\Notifications\HumanHandoffRequested;
use App\Support\Assistant\AssistantUnavailable;
use App\Support\Assistant\Coordinator;
use App\Support\Kapso\InboundMessage;
use App\Support\Kapso\KapsoClient;
use App\Support\Kapso\OptOutRegistry;
use App\Support\Kapso\ReplyPolicy;
use App\Support\Kapso\WebhookDeduplicator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

/**
 * Answers one inbound WhatsApp message.
 *
 * Runs off the request so the webhook can ACK inside Kapso's 10 second window.
 *
 * The order of the checks below is the safety design, not an accident: who may
 * be answered, then whether they asked to be left alone, then whether we
 * already answered — each one is cheaper and more absolute than the next, and
 * none of them depends on the assistant working.
 */
class RespondToWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    /**
     * Three attempts, but only ever for a *retryable* failure: a permanent one
     * goes straight to a human regardless (see handleFailure). Rate limits are
     * the common case here and they clear on their own within the minute, so
     * giving up after one retry hands off conversations that would have worked.
     * Nothing has been sent when a retry happens, so this cannot double-text.
     */
    public int $tries = 3;

    /**
     * Room for a bounded tool-calling loop against Groq, still well under the
     * queue's own patience.
     */
    public int $timeout = 120;

    /** @var list<int> */
    public array $backoff = [20, 45];

    public function __construct(public readonly InboundMessage $message) {}

    /**
     * Serialises processing per conversation so two quick messages from the
     * same client are never answered concurrently (and so out-of-order
     * delivery, which Kapso allows after 30 seconds, can't interleave).
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->message->conversationKey()))
                ->releaseAfter(10)
                ->expireAfter(300),
        ];
    }

    public function handle(
        KapsoClient $kapso,
        WebhookDeduplicator $deduplicator,
        ReplyPolicy $policy,
        OptOutRegistry $optOuts,
        Coordinator $coordinator,
    ): void {
        // Checked again here even though the controller already refused to queue
        // disallowed messages. The two checks guard different moments: a job
        // sitting on the queue while the allowlist is being tightened would
        // otherwise still go out. The cost is one config read; the cost of being
        // wrong is a message on a real client's phone.
        //
        // contactName is resolved fresh here rather than trusted from the
        // webhook delivery: confirmed against a live payload that Kapso does
        // not include it there at all (contrary to the webhook docs'
        // example), only through this read call — so the personal-contact
        // guard cannot work off the delivery alone. Scoped to the guarded
        // number only, since every other message answers just as before
        // with zero extra requests. A failed lookup falls back to the
        // delivery's own value (normally null, i.e. "unknown") rather than
        // holding up the reply — an API hiccup must not silence the
        // assistant for a genuine new client.
        $contactName = $this->resolvedContactName($kapso);

        if (! $policy->allows($this->message->fromPhone, $this->message->phoneNumberId, $contactName)) {
            Log::info('Inbound WhatsApp message is outside the reply policy; ignoring.', [
                'phone_number_id' => $this->message->phoneNumberId,
                'policy' => $policy->describe(),
                'reason' => $policy->refusalReason($this->message->fromPhone, $this->message->phoneNumberId, $contactName),
            ]);

            return;
        }

        if ($this->message->fromPhone === null) {
            // A Business-Scoped User ID conversation with no phone number.
            // Nothing to reply to over the Cloud API's `to` field, so this is
            // recorded and dropped rather than crashing the worker.
            Log::warning('Inbound WhatsApp message has no phone number; skipping reply.', [
                'phone_number_id' => $this->message->phoneNumberId,
                'has_business_scoped_user_id' => $this->message->businessScopedUserId !== null,
            ]);

            return;
        }

        // Guards the narrow window where a previous attempt sent successfully
        // and then died before recording it. One missed reply is recoverable by
        // the client writing again; two identical replies are not recoverable
        // at all.
        if ($deduplicator->claimed(WebhookDeduplicator::SCOPE_REPLY, $this->message->wamid)) {
            return;
        }

        if ($this->handleOptOut($kapso, $deduplicator, $optOuts)) {
            return;
        }

        $provider = Provider::query()
            ->where('whatsapp_phone_number_id', $this->message->phoneNumberId)
            ->first();

        if ($provider === null) {
            // The number reached us but nobody in the database owns it, so there
            // is no catalogue, no schedule and nobody to hand off to. Answering
            // anything about appointments would be invention.
            Log::error('No provider is mapped to this WhatsApp number.', [
                'phone_number_id' => $this->message->phoneNumberId,
            ]);

            $this->send($kapso, $deduplicator, $this->waitMessage());

            return;
        }

        try {
            $reply = $coordinator->reply($this->message, $provider);
        } catch (AssistantUnavailable $exception) {
            $this->handleFailure($kapso, $deduplicator, $provider, $exception);

            return;
        }

        // null means somebody at the salon is already answering this client by
        // hand. Nothing is sent and nothing is claimed: if they go quiet and the
        // client writes again later, the assistant picks it up.
        if ($reply === null) {
            return;
        }

        $this->send($kapso, $deduplicator, $reply);
    }

    /**
     * The delivery's own contactName, unless this message is on the one
     * number guarded for personal contacts — where it is always null (see
     * handle()) and worth the extra request to get right.
     */
    private function resolvedContactName(KapsoClient $kapso): ?string
    {
        $guarded = config('services.kapso.personal_phone_number_id');
        $onGuardedNumber = is_string($guarded) && trim($guarded) !== '' && trim($guarded) === $this->message->phoneNumberId;

        if (! $onGuardedNumber || $this->message->conversationId === null) {
            return $this->message->contactName;
        }

        try {
            return $kapso->contactNameFor($this->message->conversationId);
        } catch (\Throwable $exception) {
            Log::warning('Could not resolve the contact name for the personal-contact guard; answering as if unknown.', [
                'phone_number_id' => $this->message->phoneNumberId,
                'reason' => $exception->getMessage(),
            ]);

            return $this->message->contactName;
        }
    }

    /**
     * @return bool true when the message was an opt-out/opt-in and is fully handled
     */
    private function handleOptOut(KapsoClient $kapso, WebhookDeduplicator $deduplicator, OptOutRegistry $optOuts): bool
    {
        $phoneNumberId = $this->message->phoneNumberId;
        $phone = (string) $this->message->fromPhone;

        if (OptOutRegistry::isOptOutRequest($this->message->text)) {
            $optOuts->optOut($phoneNumberId, $phone);

            // One last message, then silence. Confirming is what makes the
            // opt-out trustworthy, and it also tells them how to come back.
            $this->send($kapso, $deduplicator, 'Listo, no volveremos a escribirte por aquí. Si algún día quieres retomar, escribe START. 💛');

            return true;
        }

        if (! $optOuts->optedOut($phoneNumberId, $phone)) {
            return false;
        }

        if (OptOutRegistry::isOptInRequest($this->message->text)) {
            $optOuts->optIn($phoneNumberId, $phone);

            $this->send($kapso, $deduplicator, '¡Qué bueno tenerte de vuelta! ¿En qué te puedo ayudar? 💛');

            return true;
        }

        // Opted out and not asking to come back: nothing is sent. The salon can
        // still reply by hand from their own phone, which is a person's choice
        // rather than automation.
        Log::info('Message from an opted-out number; not answering.', [
            'phone_number_id' => $phoneNumberId,
        ]);

        return true;
    }

    private function handleFailure(
        KapsoClient $kapso,
        WebhookDeduplicator $deduplicator,
        Provider $provider,
        AssistantUnavailable $exception,
    ): void {
        if ($exception->retryable && $this->attempts() < $this->tries) {
            // Honour Groq's own retry-after when it gave one; guessing shorter
            // just gets throttled again.
            $this->release($exception->retryAfterSeconds ?? $this->backoff[0]);

            return;
        }

        Log::error('WhatsApp assistant could not answer; handing off to a person.', [
            'phone_number_id' => $this->message->phoneNumberId,
            'provider' => $provider->slug,
            'reason' => $exception->getMessage(),
        ]);

        // The client gets a human, not an error. Silence would leave someone
        // waiting for a salon that never answers.
        $provider->loadMissing('user')->user?->notify(new HumanHandoffRequested(
            clientPhone: (string) $this->message->fromPhone,
            clientName: $this->message->contactName ?? 'Cliente de WhatsApp',
            reason: 'El asistente no pudo responder automáticamente.',
        ));

        $this->send($kapso, $deduplicator, $this->waitMessage());
    }

    private function send(KapsoClient $kapso, WebhookDeduplicator $deduplicator, string $body): void
    {
        $kapso->sendText(
            phoneNumberId: $this->message->phoneNumberId,
            to: (string) $this->message->fromPhone,
            body: $body,
        );

        $deduplicator->claim(WebhookDeduplicator::SCOPE_REPLY, $this->message->wamid);
    }

    private function waitMessage(): string
    {
        // Kept as one constant, not duplicated: Coordinator recognises this
        // exact text in the client's own history to know it already handed
        // this conversation off and pause instead of trying again.
        return Coordinator::WAIT_MESSAGE;
    }
}
