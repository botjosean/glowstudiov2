<?php

namespace App\Jobs;

use App\Support\Kapso\InboundMessage;
use App\Support\Kapso\KapsoClient;
use App\Support\Kapso\WebhookDeduplicator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

/**
 * Answers one inbound WhatsApp message.
 *
 * Runs off the request so the webhook can ACK inside Kapso's 10 second window.
 */
class RespondToWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    /**
     * Two, not the default three: every attempt is a real message to a real
     * person's phone. Retrying once covers a transient network blip; retrying
     * repeatedly risks texting a client the same thing several times, which is
     * worse than staying quiet.
     */
    public int $tries = 2;

    public int $timeout = 40;

    /** @var list<int> */
    public array $backoff = [10, 30];

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
                ->releaseAfter(5)
                ->expireAfter(120),
        ];
    }

    public function handle(KapsoClient $kapso, WebhookDeduplicator $deduplicator): void
    {
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

        $kapso->sendText(
            phoneNumberId: $this->message->phoneNumberId,
            to: $this->message->fromPhone,
            body: $this->reply(),
        );

        $deduplicator->claim(WebhookDeduplicator::SCOPE_REPLY, $this->message->wamid);
    }

    /**
     * Gate 2 is a plain echo: it proves the whole path (signature, dedup,
     * queue, outbound send) end to end without an LLM or the appointment
     * domain in the picture yet.
     */
    private function reply(): string
    {
        return 'Recibí: '.$this->message->text;
    }
}
