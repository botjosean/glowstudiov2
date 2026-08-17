<?php

namespace App\Support\Kapso;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Outbound WhatsApp sending, and reading conversation history, through Kapso.
 *
 * Sending goes through Kapso's Meta-compatible proxy, so the request body here
 * is Meta's, not Kapso's own — which is why the version segment (v24.0) is
 * configurable: it tracks Meta's Graph version, and pinning it in code would
 * mean a deploy every time that moves. Reading history goes through Kapso's own
 * platform API, which is a different base path.
 */
class KapsoClient
{
    /**
     * Sends a plain text message and returns the resulting WAMID.
     *
     * @param  string  $phoneNumberId  the business number to send *from* — always
     *                                 the one the inbound message arrived on, so a
     *                                 reply can never surface from the wrong number
     * @param  string  $to  recipient in digits (E.164 without the +)
     *
     * @throws RuntimeException when Kapso rejects the send
     */
    public function sendText(string $phoneNumberId, string $to, string $body): string
    {
        $base = rtrim((string) config('services.kapso.base_url'), '/');
        $version = config('services.kapso.graph_version');

        $response = $this->request()->post("{$base}/meta/whatsapp/{$version}/{$phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $body],
        ]);

        return $this->wamidFrom($response);
    }

    /**
     * The most recent turns of one conversation, oldest first.
     *
     * Kapso stores and backs up every message already, so this is read rather
     * than mirrored into a local table — one copy of the transcript, no sync to
     * keep right.
     *
     * @return list<array{id: string, direction: string, text: string, at: int, from: string}>
     *
     * @throws RuntimeException
     */
    public function recentMessages(string $conversationId, int $limit): array
    {
        $base = rtrim((string) config('services.kapso.base_url'), '/');

        // `conversation_id`, not `whatsapp_conversation_id`. Kapso ignores an
        // unrecognised query parameter instead of rejecting it, so the wrong
        // name here does not fail loudly: it silently returns the account's
        // latest messages instead of this conversation's, and both the model's
        // history and the human hand-off guard below read them as if they were.
        // Verified directly against Kapso: a nonexistent conversation id still
        // returned rows under the old name, and returned none under this one.
        $response = $this->request()->get("{$base}/platform/v1/whatsapp/messages", [
            'conversation_id' => $conversationId,
            'per_page' => $limit,
        ]);

        if ($response->failed()) {
            throw new RuntimeException("Kapso returned HTTP {$response->status()} for conversation history.");
        }

        $rows = $response->json('data');

        if (! is_array($rows)) {
            return [];
        }

        $turns = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $text = $row['text']['body'] ?? null;

            $turns[] = [
                'id' => (string) ($row['id'] ?? ''),
                'direction' => (string) ($row['kapso']['direction'] ?? 'inbound'),
                'text' => is_string($text) ? $text : '',
                // Unix seconds, as Kapso sends it. Needed so a conversation can
                // be given a clean slate without deleting the business's data.
                'at' => (int) ($row['timestamp'] ?? 0),
                // Empty on messages this app sent through the API, set to the
                // salon's own number on messages somebody typed on their phone.
                // That difference is how the assistant knows to stay out of a
                // conversation a person has taken over.
                'from' => (string) ($row['from'] ?? ''),
            ];
        }

        // Kapso answers newest first; a conversation reads the other way round.
        return array_reverse($turns);
    }

    /**
     * The contact name Kapso has resolved for a conversation, when it has
     * one.
     *
     * The webhook delivery never carries this — confirmed against a live
     * payload, where both message.kapso and conversation.kapso lacked the
     * key entirely, unlike the webhook docs' example. Kapso resolves it
     * later and only exposes it through this read endpoint, so a caller that
     * needs it at reply time (see ReplyPolicy's personal-contact guard) has
     * to ask for it directly rather than trust the delivery.
     *
     * @throws RuntimeException
     */
    public function contactNameFor(string $conversationId): ?string
    {
        $base = rtrim((string) config('services.kapso.base_url'), '/');

        $response = $this->request()->get("{$base}/platform/v1/whatsapp/conversations/{$conversationId}");

        if ($response->failed()) {
            throw new RuntimeException("Kapso returned HTTP {$response->status()} for a conversation lookup.");
        }

        $name = $response->json('data.kapso.contact_name');

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * The Kapso customer that stands for this provider, creating it if this is
     * the first time.
     *
     * Keyed by `external_customer_id`, which carries the provider's slug — the
     * same identifier used everywhere else in this app. Doing it by lookup
     * rather than by storing a second id means a customer created by hand in
     * Kapso's panel (which is how Vanessa's got there) is adopted instead of
     * duplicated.
     *
     * @throws RuntimeException
     */
    public function findOrCreateCustomer(string $externalId, string $name): string
    {
        $base = rtrim((string) config('services.kapso.base_url'), '/');

        $response = $this->request()->get("{$base}/platform/v1/customers", [
            'external_customer_id' => $externalId,
            'per_page' => 100,
        ]);

        if ($response->failed()) {
            throw new RuntimeException("Kapso returned HTTP {$response->status()} listing customers.");
        }

        // Filtered again here rather than trusted: an unrecognised query
        // parameter is ignored by Kapso instead of rejected (see
        // recentMessages), so a server that does not support this filter would
        // hand back somebody else's customer as the first row.
        foreach ((array) $response->json('data') as $row) {
            if (is_array($row) && ($row['external_customer_id'] ?? null) === $externalId && is_string($row['id'] ?? null)) {
                return $row['id'];
            }
        }

        $created = $this->request()->post("{$base}/platform/v1/customers", [
            'customer' => ['name' => $name, 'external_customer_id' => $externalId],
        ]);

        if ($created->failed()) {
            throw new RuntimeException("Kapso rejected the customer with HTTP {$created->status()}.");
        }

        $id = $created->json('data.id');

        if (! is_string($id) || $id === '') {
            throw new RuntimeException('Kapso created a customer without returning its id.');
        }

        return $id;
    }

    /**
     * A one-time hosted page where a professional connects her own WhatsApp.
     *
     * Locked to `coexistence` on purpose: the alternative provisions a fresh
     * number that cannot be used from a phone at all, which would leave a
     * professional reading her messages in a browser while her hands are in
     * somebody's hair. Never `provision_phone_number` for the same reason.
     *
     * @return array{url: string, id: string}
     *
     * @throws RuntimeException
     */
    public function createSetupLink(string $customerId, string $successUrl, string $failureUrl): array
    {
        $base = rtrim((string) config('services.kapso.base_url'), '/');

        $response = $this->request()->post("{$base}/platform/v1/customers/{$customerId}/setup_links", [
            'setup_link' => [
                'success_redirect_url' => $successUrl,
                'failure_redirect_url' => $failureUrl,
                'provision_phone_number' => false,
                'allowed_connection_types' => ['coexistence'],
                'meta_billing_mode' => 'customer_managed',
                'language' => 'es',
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException("Kapso rejected the setup link with HTTP {$response->status()}.");
        }

        $url = $response->json('data.url');

        if (! is_string($url) || $url === '') {
            throw new RuntimeException('Kapso returned a setup link without a URL.');
        }

        return ['url' => $url, 'id' => (string) $response->json('data.id')];
    }

    /**
     * The connected number belonging to one customer, or null when the
     * professional has not finished (or abandoned) the hosted flow.
     *
     * @return array{phone_number_id: string, display_phone_number: ?string}|null
     *
     * @throws RuntimeException
     */
    public function connectedNumberFor(string $customerId): ?array
    {
        $base = rtrim((string) config('services.kapso.base_url'), '/');

        $response = $this->request()->get("{$base}/platform/v1/whatsapp/phone_numbers", [
            'customer_id' => $customerId,
            'per_page' => 50,
        ]);

        if ($response->failed()) {
            throw new RuntimeException("Kapso returned HTTP {$response->status()} listing phone numbers.");
        }

        foreach ((array) $response->json('data') as $row) {
            // Same defensive re-filter as above, and the status matters: a row
            // exists from the moment the flow starts, so claiming it before
            // Meta says CONNECTED would wire the assistant to a dead number.
            if (! is_array($row)
                || ($row['customer_id'] ?? null) !== $customerId
                || ($row['status'] ?? null) !== 'CONNECTED'
                || ! is_string($row['phone_number_id'] ?? null)) {
                continue;
            }

            return [
                'phone_number_id' => $row['phone_number_id'],
                'display_phone_number' => is_string($row['display_phone_number'] ?? null) ? $row['display_phone_number'] : null,
            ];
        }

        return null;
    }

    /**
     * Points a freshly connected number at this app's webhook.
     *
     * Without this the number is connected and completely silent: Kapso has it,
     * and nothing ever reaches the queue. The buffering values mirror what the
     * live numbers were configured with by hand — a few seconds of grace so a
     * client typing three short lines gets one answer instead of three.
     *
     * @throws RuntimeException
     */
    public function createWebhook(string $phoneNumberId, string $url, string $secret): void
    {
        $base = rtrim((string) config('services.kapso.base_url'), '/');

        $response = $this->request()->post("{$base}/platform/v1/whatsapp/phone_numbers/{$phoneNumberId}/webhooks", [
            'whatsapp_webhook' => [
                'url' => $url,
                'kind' => 'kapso',
                'secret_key' => $secret,
                'active' => true,
                'events' => ['whatsapp.message.received'],
                'buffer_enabled' => true,
                'buffer_window_seconds' => 8,
                'max_buffer_size' => 10,
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException("Kapso rejected the webhook with HTTP {$response->status()}.");
        }
    }

    /**
     * Whether this number already delivers to the given URL.
     *
     * Used to tell "connected and listening" from "connected and deaf" after
     * the hosted flow — the difference between a working assistant and one
     * that never hears anybody, which is invisible from the panel otherwise.
     *
     * @throws RuntimeException
     */
    public function hasWebhookFor(string $phoneNumberId, string $url): bool
    {
        $base = rtrim((string) config('services.kapso.base_url'), '/');

        $response = $this->request()->get("{$base}/platform/v1/whatsapp/phone_numbers/{$phoneNumberId}/webhooks");

        if ($response->failed()) {
            throw new RuntimeException("Kapso returned HTTP {$response->status()} listing webhooks.");
        }

        foreach ((array) $response->json('data') as $row) {
            if (is_array($row) && ($row['url'] ?? null) === $url && ($row['active'] ?? false)) {
                return true;
            }
        }

        return false;
    }

    private function request(): PendingRequest
    {
        $apiKey = config('services.kapso.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('Kapso API key is not configured.');
        }

        return Http::withHeaders(['X-API-Key' => $apiKey])
            ->acceptJson()
            ->asJson()
            // Short on purpose: this runs on a queue worker, and a hung call
            // holding a worker slot delays every other client's reply.
            ->timeout(15)
            ->connectTimeout(5);
    }

    /**
     * @throws RuntimeException
     */
    private function wamidFrom(Response $response): string
    {
        if ($response->failed()) {
            // The status is what the caller needs to decide whether retrying is
            // worth it; the body is not logged here because it echoes the
            // recipient's number back.
            throw new RuntimeException("Kapso rejected the send with HTTP {$response->status()}.");
        }

        $wamid = $response->json('messages.0.id');

        if (! is_string($wamid) || $wamid === '') {
            throw new RuntimeException('Kapso accepted the send but returned no message id.');
        }

        return $wamid;
    }
}
