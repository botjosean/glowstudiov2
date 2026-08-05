<?php

namespace App\Support\Kapso;

/**
 * One inbound WhatsApp text message, parsed out of a single Kapso v2 webhook
 * delivery.
 *
 * Constructed only through fromDelivery(), which returns null for anything
 * this app deliberately ignores (non-text, outbound, echoes, malformed).
 * "Ignore" and "fail" are different outcomes and the caller must not have to
 * tell them apart: a null here always means "nothing to answer", never "the
 * payload was broken".
 *
 * Field locations follow Kapso's payload v2, which mirrors Meta's shape — not
 * v1. Notably the sender lives in `message.from` and the display name in
 * `conversation.kapso.contact_name`.
 */
final readonly class InboundMessage
{
    private function __construct(
        public string $wamid,
        public string $phoneNumberId,
        public ?string $conversationId,
        public ?string $fromPhone,
        public ?string $businessScopedUserId,
        public ?string $contactName,
        public string $text,
    ) {}

    /**
     * Splits a webhook body into its individual deliveries.
     *
     * With buffering enabled Kapso wraps *every* delivery in a batch envelope
     * — including single-message ones — so the shape alone cannot be trusted
     * to tell the two apart. The caller decides via the `X-Webhook-Batch`
     * header or the `batch` field, never by looking for `data`.
     *
     * @param  array<mixed>  $payload
     * @return list<array<mixed>>
     */
    public static function deliveriesFrom(array $payload, bool $isBatch): array
    {
        if (! $isBatch) {
            return [$payload];
        }

        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            return [];
        }

        return array_values(array_filter($data, 'is_array'));
    }

    /**
     * @param  array<mixed>  $delivery
     */
    public static function fromDelivery(array $delivery): ?self
    {
        $message = $delivery['message'] ?? null;

        if (! is_array($message)) {
            return null;
        }

        // v2 carries the type in `type` (Meta's shape). v1's `message_type` is
        // deliberately not consulted: reading both would silently accept a
        // payload version this parser hasn't been verified against.
        if (($message['type'] ?? null) !== 'text') {
            return null;
        }

        $kapso = is_array($message['kapso'] ?? null) ? $message['kapso'] : [];

        // Never react to our own traffic. Answering an echo of a message this
        // app just sent is how a bot ends up talking to itself forever.
        if (($kapso['direction'] ?? 'inbound') !== 'inbound') {
            return null;
        }

        if (($kapso['origin'] ?? null) === 'smb_message_echo') {
            return null;
        }

        $wamid = $message['id'] ?? null;
        $text = $message['text']['body'] ?? null;

        if (! is_string($wamid) || $wamid === '' || ! is_string($text) || trim($text) === '') {
            return null;
        }

        $conversation = is_array($delivery['conversation'] ?? null) ? $delivery['conversation'] : [];

        $phoneNumberId = $delivery['phone_number_id'] ?? $conversation['phone_number_id'] ?? null;

        if (! is_string($phoneNumberId) || $phoneNumberId === '') {
            return null;
        }

        return new self(
            wamid: $wamid,
            phoneNumberId: $phoneNumberId,
            conversationId: self::stringOrNull($conversation['id'] ?? null),
            // A Business-Scoped User ID conversation carries no phone number at
            // all, so this is genuinely nullable — assuming otherwise is the
            // documented trap here.
            fromPhone: self::digitsOrNull($message['from'] ?? $conversation['phone_number'] ?? null),
            businessScopedUserId: self::stringOrNull(
                $message['from_user_id'] ?? $conversation['business_scoped_user_id'] ?? null
            ),
            contactName: self::stringOrNull($conversation['kapso']['contact_name'] ?? null),
            text: trim($text),
        );
    }

    /**
     * A stable per-conversation key, used to serialise processing so two
     * messages from the same client can't be answered out of order.
     *
     * Falls back through conversation id -> phone -> BSUID: at least one is
     * always present, and mixing them across messages of one conversation is
     * harmless as long as the value itself is stable, which it is.
     */
    public function conversationKey(): string
    {
        return $this->phoneNumberId.':'.($this->conversationId ?? $this->fromPhone ?? $this->businessScopedUserId ?? $this->wamid);
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Normalises to bare digits, matching how `appointments.client_phone`
     * already stores numbers elsewhere in this app.
     */
    private static function digitsOrNull(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', (string) $value) ?? '';

        return $digits === '' ? null : $digits;
    }
}
