<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RespondToWhatsAppMessage;
use App\Support\Kapso\InboundMessage;
use App\Support\Kapso\ReplyPolicy;
use App\Support\Kapso\WebhookDeduplicator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $queued = 0;
        $notAllowed = 0;

        foreach (InboundMessage::deliveriesFrom($request->json()->all(), $this->isBatch($request)) as $delivery) {
            $message = InboundMessage::fromDelivery($delivery);

            if ($message === null) {
                continue;
            }

            // Checked before queueing, not inside the job: a message from
            // someone the assistant must not answer should leave no job, no
            // claim and no trace beyond the count reported here.
            if (! $policy->allows($message->fromPhone)) {
                $notAllowed++;

                continue;
            }

            // Per-message claim as well as per-delivery: after its retries are
            // exhausted Kapso re-sends a batch as individual deliveries, each
            // with a fresh idempotency key but the same messages inside.
            if (! $deduplicator->claim(WebhookDeduplicator::SCOPE_MESSAGE, $message->wamid)) {
                continue;
            }

            RespondToWhatsAppMessage::dispatch($message);
            $queued++;
        }

        return response()->json([
            'status' => 'accepted',
            'queued' => $queued,
            'not_allowed' => $notAllowed,
        ]);
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
