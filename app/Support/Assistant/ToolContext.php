<?php

namespace App\Support\Assistant;

use App\Models\Provider;
use App\Support\Format;

/**
 * Who the assistant is acting for, and on whose behalf.
 *
 * Every tool reads its scope from here and never from its own arguments, which
 * is what stops a model from asking about somebody else's appointments: the
 * provider and the client's phone are decided by the webhook identity before
 * the model is ever consulted.
 *
 * The two phone representations are not redundant. WhatsApp identifies the
 * client with the country code (`12056455856`) and Kapso needs that form to
 * reply, while this app has always stored `appointments.client_phone` as the
 * ten digits Format::digitsOnly() produces (`2056455856`). Mixing them up
 * would mean the assistant silently never finds a client's existing bookings,
 * and would write rows that look nothing like the ones the website creates.
 */
final readonly class ToolContext
{
    private function __construct(
        public Provider $provider,
        public string $whatsappPhone,
        public string $storedPhone,
        public string $clientName,
    ) {}

    public static function for(Provider $provider, string $whatsappPhone, ?string $contactName): self
    {
        return new self(
            provider: $provider,
            whatsappPhone: $whatsappPhone,
            storedPhone: Format::digitsOnly($whatsappPhone),
            // The WhatsApp profile name is a display name the client chose, not
            // necessarily the name they want on the appointment, so it is only
            // a starting point — crear_cita still takes an explicit one.
            clientName: $contactName !== null && trim($contactName) !== '' ? trim($contactName) : 'Cliente de WhatsApp',
        );
    }
}
