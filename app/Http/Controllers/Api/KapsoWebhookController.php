<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RespondToWhatsAppMessage;
use App\Support\Kapso\InboundMessage;
use App\Support\Kapso\ReplyPolicy;
use App\Support\Kapso\WebhookDeduplicator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Receives Kapso's WhatsApp webhook deliveries.
 *
 * The signature is already verified by VerifyKapsoWebhookSignature — nothing
 * here reads the body before that has happened.
 *
 * This method does the minimum needed to answer 200 and nothing more. Kapso
 * requires the ACK within 10 seconds and retries otherwise, so calling an LLM
 * or writing appointments inline would turn one slow request into a stream of
 * duplicate deliveries. Everything real happens on the queue.
 */
class KapsoWebhookController extends Controller
{
    /**
     * Buffering only ever applies to this event, and it is the only one this
     * app subscribes to. Anything else is acknowledged and dropped rather than
     * 4xx'd, so an accidental extra subscription in Kapso can't trip the
     * auto-pause that kicks in after a run of failed deliveries.
     */
    private const HANDLED_EVENT = 'whatsapp.message.received';

    /**
     * Messages answered per conversation per hour.
     *
     * A real booking conversation is under a dozen turns, so this is far above
     * anyone genuine and still bounds what one sender can cost. Every answered
     * message spends model tokens, and the sender chooses how many to send —
     * without a ceiling, a single person hammering the number is an unbounded
     * bill.
     *
     * Deliberately silent when exceeded rather than replying "slow down":
     * answering an abuser is what they wanted, and a real client is never going
     * to reach this.
     */
    private const MAX_PER_CONVERSATION_HOURLY = 30;

    public function store(
        Request $request,
        WebhookDeduplicator $deduplicator,
        ReplyPolicy $policy,
    ): JsonResponse {
        if ($request->header('X-Webhook-Event') !== self::HANDLED_EVENT) {
            return response()->json(['status' => 'ignored']);
        }

        $idempotencyKey = $request->header('X-Idempotency-Key');

        if (is_string($idempotencyKey) && $idempotencyKey !== '' && ! $deduplicator->claim(WebhookDeduplicator::SCOPE_DELIVERY, $idempotencyKey)) {
            // A retry of something already accepted. 200, because a 4xx here
            // would make Kapso keep retrying a delivery that succeeded.
            return response()->json(['status' => 'duplicate']);
        }

        $notAllowed = 0;
        $throttled = 0;

        /** @var array<string, list<InboundMessage>> */
        $byConversation = [];

        foreach (InboundMessage::deliveriesFrom($request->json()->all(), $this->isBatch($request)) as $delivery) {
            $message = InboundMessage::fromDelivery($delivery);

            if ($message === null) {
                continue;
            }

            // Checked before queueing, not inside the job: a message from
            // someone the assistant must not answer should leave no job, no
            // claim and no trace beyond the count reported here.
            //
            // On the number guarded for personal contacts, contactName is
            // always null this early — Kapso does not include it in the
            // delivery at all, only through a later read call — so this
            // gate cannot enforce that guard by itself. RespondToWhatsAppMessage
            // resolves the real name before its own check and is what
            // actually blocks a saved contact; this one still queues the
            // job, which is a wasted claim rather than a leak.
            if (! $policy->allows($message->fromPhone, $message->phoneNumberId, $message->contactName)) {
                $notAllowed++;

                continue;
            }

            if ($this->overTheLimit($message)) {
                $throttled++;

                continue;
            }

            // Per-message claim as well as per-delivery: after its retries are
            // exhausted Kapso re-sends a batch as individual deliveries, each
            // with a fresh idempotency key but the same messages inside. Every
            // message is claimed even though the batch is answered once, so no
            // later delivery can resurrect one of them on its own.
            if (! $deduplicator->claim(WebhookDeduplicator::SCOPE_MESSAGE, $message->wamid)) {
                continue;
            }

            $byConversation[$message->conversationKey()][] = $message;
        }

        $queued = 0;

        // One job per conversation, not per message. A client who fires off
        // "hola", "quiero una cita", "para el jueves" in ten seconds was getting
        // three separate replies that talked over each other; with Kapso's
        // buffering on, those arrive as one batch and deserve one answer.
        foreach ($byConversation as $messages) {
            RespondToWhatsAppMessage::dispatch(InboundMessage::merged($messages));
            $queued++;
        }

        return response()->json([
            'status' => 'accepted',
            'queued' => $queued,
            'not_allowed' => $notAllowed,
            'throttled' => $throttled,
        ]);
    }

    /**
     * Counts this message against the conversation's hourly allowance.
     *
     * Keyed on the conversation rather than the phone number so that the same
     * person writing to both salon numbers gets an allowance for each — they are
     * two different businesses as far as the client is concerned.
     */
    private function overTheLimit(InboundMessage $message): bool
    {
        $key = 'whatsapp-assistant:'.$message->conversationKey();

        if (RateLimiter::tooManyAttempts($key, self::MAX_PER_CONVERSATION_HOURLY)) {
            Log::warning('Conversation is over its hourly message allowance; not answering.', [
                'phone_number_id' => $message->phoneNumberId,
                'limit' => self::MAX_PER_CONVERSATION_HOURLY,
            ]);

            return true;
        }

        RateLimiter::hit($key, 3600);

        return false;
    }

    /**
     * With buffering on, single messages also arrive wrapped in a batch
     * envelope, so presence of `data` proves nothing. Only the header or the
     * explicit `batch` flag is authoritative.
     */
    private function isBatch(Request $request): bool
    {
        if ($request->hasHeader('X-Webhook-Batch')) {
            return filter_var($request->header('X-Webhook-Batch'), FILTER_VALIDATE_BOOLEAN);
        }

        return $request->boolean('batch');
    }
}
