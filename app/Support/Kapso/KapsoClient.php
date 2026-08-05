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
     * @return list<array{id: string, direction: string, text: string}>
     *
     * @throws RuntimeException
     */
    public function recentMessages(string $conversationId, int $limit): array
    {
        $base = rtrim((string) config('services.kapso.base_url'), '/');

        $response = $this->request()->get("{$base}/platform/v1/whatsapp/messages", [
            'whatsapp_conversation_id' => $conversationId,
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
            ];
        }

        // Kapso answers newest first; a conversation reads the other way round.
        return array_reverse($turns);
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
