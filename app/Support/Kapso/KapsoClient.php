<?php

namespace App\Support\Kapso;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Outbound WhatsApp sending through Kapso's Meta-compatible proxy.
 *
 * Kapso mirrors the Cloud API's request and response shapes, so the body here
 * is Meta's, not Kapso's own — which is why the version segment (v24.0) is
 * configurable: it tracks Meta's Graph version, and pinning it in code would
 * mean a deploy every time that moves.
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
        $response = $this->request()->post($this->messagesUrl($phoneNumberId), [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $body],
        ]);

        return $this->wamidFrom($response);
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
            // Short on purpose: this runs on a queue worker, and a hung send
            // holding a worker slot delays every other client's reply.
            ->timeout(15)
            ->connectTimeout(5);
    }

    private function messagesUrl(string $phoneNumberId): string
    {
        $base = rtrim((string) config('services.kapso.base_url'), '/');
        $version = config('services.kapso.graph_version');

        return "{$base}/meta/whatsapp/{$version}/{$phoneNumberId}/messages";
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
