<?php

namespace App\Support\Assistant;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Provider;
use App\Support\Format;
use App\Support\Kapso\InboundMessage;
use Illuminate\Support\Facades\Log;

/**
 * The assistant as a receptionist: says the message arrived, asks for what the
 * professional needs, and gets out of the way.
 *
 * **No model is consulted here at all.** That is the point, not an
 * optimisation. Patricia asked for this after two failures on her live number
 * (voice note, 2026-08-16): the assistant rambled at a client who asked about
 * a service missing from her two-service catalogue, and greeted a client who
 * was already driving to her appointment — "el cliente dirá como que será que
 * me equivoqué el número". Both are failures of a machine deciding what to
 * say. A machine that cannot decide cannot fail that way, and it also cannot
 * invent an appointment, which is the incident that cost this project the most
 * (see CITAS FANTASMA). It is instant and free as a side effect.
 *
 * Three things it must get right, in this order:
 *
 * 1. **Silence for a client who already has an appointment.** This is the
 *    "voy en camino" case, and shortening the assistant does not fix it —
 *    it makes it worse, because a welcome is even more obviously wrong to
 *    someone arriving. The verdict is the appointments table.
 * 2. **Two messages, ever.** Counted on the lead row rather than by reading
 *    the transcript back, because Kapso's history window scrolls and a client
 *    who sends ten messages would silently re-arm the greeting.
 * 3. **Everything the client writes gets recorded**, so the conversation stops
 *    dying in the chat and reaches the panel as something a person can act on.
 */
class Receptionist
{
    /**
     * How long a conversation has to be cold before the two messages are
     * offered again.
     *
     * Without this a client who comes back in three months is met with
     * silence, because her lead row still remembers a conversation nobody
     * involved recalls. A week is long enough that nobody is greeted twice
     * about the same enquiry.
     */
    private const DEFAULT_REARM_DAYS = 7;

    /**
     * How much of the conversation to keep on the card.
     *
     * The lead is a pointer to a conversation, not a copy of it — WhatsApp
     * already has the transcript. Enough to see what she wants and the name
     * and date she sent back, not enough to turn the agenda into a chat log.
     */
    private const MAX_RECORDED_CHARACTERS = 1000;

    /**
     * @param  bool  $humanReplied  whether somebody at the salon has answered this
     *                              conversation by hand recently
     * @return string|null the message to send, or null to stay quiet
     */
    public function reply(InboundMessage $message, Provider $provider, bool $humanReplied): ?string
    {
        $digits = Format::digitsOnly((string) $message->fromPhone);

        if (strlen($digits) !== 10) {
            // Without a phone in the app's normal form there is no lead row,
            // and without a lead row the two-message cap cannot be enforced —
            // this conversation could be greeted on every single message. A
            // client who is never answered writes again or calls; a client
            // greeted twelve times is the exact complaint this replaces.
            Log::warning('Receptionist skipped a conversation whose phone is not ten digits.', [
                'provider' => $provider->slug,
            ]);

            return null;
        }

        if ($this->hasLiveAppointment($provider, $digits)) {
            // She is already booked. Whatever she is writing — "voy en
            // camino", "llego 10 minutos tarde" — belongs to the professional,
            // and a welcome would read as a wrong number.
            Log::info('Receptionist staying quiet: this client already has an appointment.', [
                'provider' => $provider->slug,
            ]);

            return null;
        }

        $lead = $this->recordContact($provider, $digits, $message);

        if ($humanReplied) {
            // A person is already on it. Marking it here is what lets the
            // panel tell "waiting" from "being handled" without anybody
            // remembering to press a button.
            if ($lead->answered_at === null) {
                $lead->forceFill([
                    'status' => Lead::STATUS_HANDLED,
                    'answered_at' => now(),
                ])->save();
            }

            Log::info('Receptionist staying quiet: a person is already answering.', [
                'provider' => $provider->slug,
            ]);

            return null;
        }

        if ($lead->botIsDone()) {
            Log::info('Receptionist has already sent its two messages; staying quiet.', [
                'provider' => $provider->slug,
            ]);

            return null;
        }

        $body = $lead->bot_messages_sent === 0
            ? $this->greeting($provider, $digits)
            : $this->intake($provider);

        $lead->forceFill(['bot_messages_sent' => $lead->bot_messages_sent + 1])->save();

        Log::info('Receptionist answered.', [
            'provider' => $provider->slug,
            'message_number' => $lead->bot_messages_sent,
            'lead_id' => $lead->id,
        ]);

        return $body;
    }

    /**
     * Whether this client has an appointment that has not happened yet.
     *
     * Same query the fabricated-booking guard uses, and scoped the same way:
     * this provider and this phone only.
     */
    private function hasLiveAppointment(Provider $provider, string $digits): bool
    {
        return Appointment::query()
            ->where('provider_id', $provider->id)
            ->where('client_phone', $digits)
            ->whereIn('status', AppointmentStatus::blocking())
            ->where('starts_at', '>=', now()->utc())
            ->exists();
    }

    /**
     * Finds or opens this conversation's lead, and appends what was just said.
     *
     * A cold conversation re-arms: the counter goes back to zero and the card
     * returns to the waiting list, because a client writing again after a week
     * is a new enquiry to whoever reads the panel.
     */
    private function recordContact(Provider $provider, string $digits, InboundMessage $message): Lead
    {
        $now = now();

        $lead = Lead::query()->firstOrCreate(
            ['provider_id' => $provider->id, 'phone' => $digits],
            [
                'name' => $this->savedName($message),
                'message' => $this->trimmed($message->text),
                'status' => Lead::STATUS_NEW,
                'bot_messages_sent' => 0,
                'first_contact_at' => $now,
                'last_contact_at' => $now,
            ],
        );

        if ($lead->wasRecentlyCreated) {
            return $lead;
        }

        $changes = ['last_contact_at' => $now];

        if ($this->hasGoneCold($lead)) {
            $changes['bot_messages_sent'] = 0;
            $changes['status'] = Lead::STATUS_NEW;
            $changes['answered_at'] = null;
            $changes['message'] = $this->trimmed($message->text);
        } else {
            $changes['message'] = $this->appended($lead->message, $message->text);
        }

        if ($lead->name === null) {
            $changes['name'] = $this->savedName($message);
        }

        $lead->forceFill($changes)->save();

        return $lead;
    }

    private function hasGoneCold(Lead $lead): bool
    {
        $days = (int) (config('services.assistant.receptionist_rearm_days') ?: self::DEFAULT_REARM_DAYS);

        return $lead->last_contact_at->lt(now()->subDays($days));
    }

    /**
     * The name the salon has saved for this sender, when it is a name.
     *
     * Kapso answers with the sender's own number when nothing is saved, so a
     * "name" that reduces to those same digits is no name at all — writing it
     * onto the card would fill the client book with phone numbers pretending
     * to be people.
     */
    private function savedName(InboundMessage $message): ?string
    {
        $name = trim((string) $message->contactName);

        if ($name === '') {
            return null;
        }

        return preg_replace('/\D/', '', $name) === preg_replace('/\D/', '', (string) $message->fromPhone)
            ? null
            : mb_substr($name, 0, 120);
    }

    private function trimmed(string $text): string
    {
        return mb_substr(trim($text), 0, self::MAX_RECORDED_CHARACTERS);
    }

    private function appended(?string $existing, string $text): string
    {
        $existing = trim((string) $existing);
        $addition = trim($text);

        if ($existing === '') {
            return $this->trimmed($addition);
        }

        return mb_substr($existing."\n".$addition, 0, self::MAX_RECORDED_CHARACTERS);
    }

    /**
     * Message one: the message arrived, and a person is coming.
     *
     * Two versions, because a client of two years being told "bienvenida" is
     * the impersonal note Patricia is trying to get rid of. Which one she gets
     * is decided by the client book and her own booking history, never by a
     * model guessing.
     */
    private function greeting(Provider $provider, string $digits): string
    {
        $name = $this->knownClientName($provider, $digits);

        $template = $name !== null
            ? ($provider->bot_greeting_returning ?? $this->defaultReturningGreeting())
            : ($provider->bot_greeting ?? $this->defaultGreeting($provider));

        return $this->fill($template, $provider, $name);
    }

    /**
     * Message two: what to send ahead so the professional arrives with
     * everything.
     *
     * The booking link is offered rather than pushed, and can be switched off
     * per provider: that page lists prices, and a professional who wants to
     * quote them herself must be able to say so.
     */
    private function intake(Provider $provider): string
    {
        $template = $provider->bot_intake ?? $this->defaultIntake();

        // Checked on the template, not on the filled text: after fill() the
        // placeholder is gone, so asking afterwards would append a second copy
        // of the link to every custom message that already placed one.
        $placesItsOwnLink = str_contains($template, ':enlace');

        $body = $this->fill($template, $provider, null);

        if ($provider->bot_offers_booking_link && ! $placesItsOwnLink) {
            $body .= "\n\nY si prefieres elegir tu horario tú misma, aquí puedes: ".$this->bookingUrl($provider);
        }

        return $body;
    }

    /**
     * The name the salon already has for this number, or null for a stranger.
     *
     * The client book first, then her booking history — the same phone match
     * used everywhere else in this app.
     */
    private function knownClientName(Provider $provider, string $digits): ?string
    {
        $client = Client::query()
            ->where('provider_id', $provider->id)
            ->where('phone', $digits)
            ->first();

        if ($client !== null) {
            return $this->firstName($client->name);
        }

        $appointment = Appointment::query()
            ->where('provider_id', $provider->id)
            ->where('client_phone', $digits)
            ->orderByDesc('starts_at')
            ->first();

        return $appointment === null ? null : $this->firstName($appointment->client_name);
    }

    /**
     * First name only: "¡Hola María!" is a greeting, "¡Hola María Fernanda
     * López!" is a form letter.
     */
    private function firstName(string $name): ?string
    {
        $first = trim(explode(' ', trim($name))[0] ?? '');

        return $first === '' ? null : $first;
    }

    /**
     * @param  string|null  $clientName  null when the sender is not known
     */
    private function fill(string $template, Provider $provider, ?string $clientName): string
    {
        return strtr($template, [
            ':negocio' => $provider->botBusinessName(),
            ':profesional' => $provider->public_name,
            ':oficio' => (string) $provider->bot_trade,
            ':nombre' => (string) $clientName,
            ':enlace' => $this->bookingUrl($provider),
        ]);
    }

    private function bookingUrl(Provider $provider): string
    {
        return route('providers.show', $provider);
    }

    /**
     * Introduces the line rather than impersonating the professional.
     *
     * "Soy el WhatsApp de Patricia" is the honest half of what she asked for:
     * the client knows whose number she reached — which is the doubt that
     * started this ("será que me equivoqué el número") — without the assistant
     * claiming to be a person typing. The trade is named when it is set,
     * because a stranger writing to a number wants to know she found the
     * hairdresser and not a shop.
     */
    private function defaultGreeting(Provider $provider): string
    {
        $trade = trim((string) $provider->bot_trade);

        $intro = $trade === ''
            ? '¡Hola! Bienvenida a :negocio 💛 Soy el WhatsApp de :profesional.'
            : '¡Hola! Bienvenida a :negocio 💛 Soy el WhatsApp de :profesional, :oficio.';

        return $intro."\n\nRecibí tu mensaje. Ahora mismo está atendiendo, pero ella te responde en cuanto se desocupe.";
    }

    private function defaultReturningGreeting(): string
    {
        return '¡Hola :nombre! Qué gusto leerte 💛'
            ."\n\nRecibí tu mensaje. :profesional está atendiendo ahora mismo y te responde en cuanto se desocupe.";
    }

    private function defaultIntake(): string
    {
        return 'Si quieres ir adelantando, mándame por aquí:'
            ."\n\n• una foto de lo que te quieres hacer"
            ."\n• una foto de referencia"
            ."\n• tu nombre"
            ."\n• y qué día te gustaría"
            ."\n\nAsí :profesional ya te llega con todo a la mano ✨";
    }
}
