<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Support\Assistant\Receptionist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The WhatsApp assistant, configured from the app instead of from the server.
 *
 * Everything the assistant says used to live in code and in one shared file,
 * so changing how it introduced a professional meant a deploy. The owner's
 * point on 2026-08-16 is that this belongs next to her cover photo, her hours
 * and her services: it is part of setting up a business, not part of running
 * an app. What she saves here reaches the next client who writes, with no
 * rebuild.
 *
 * Connecting the number itself is still not self-service — Kapso and Meta both
 * have their own approval — so this page states plainly where that stands
 * rather than pretending the switch is here.
 */
class AssistantSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $provider = $request->user()->provider;

        return Inertia::render('Admin/Asistente', [
            'settings' => [
                'mode' => $provider->bot_mode,
                'displayName' => $provider->bot_display_name,
                'businessName' => $provider->bot_business_name,
                'greeting' => $provider->bot_greeting,
                'greetingReturning' => $provider->bot_greeting_returning,
                'intake' => $provider->bot_intake,
                'offersBookingLink' => $provider->bot_offers_booking_link,
                'notes' => $provider->bot_notes,
                'startMinute' => $provider->bot_start_minute,
                'endMinute' => $provider->bot_end_minute,
            ],
            // What she gets if she leaves a box empty. Sent rather than
            // duplicated in the Vue file so the preview shows the real text the
            // server would send, not a copy that can drift from it.
            'defaults' => [
                'greeting' => Receptionist::defaultGreeting(),
                'greetingReturning' => Receptionist::defaultReturningGreeting(),
                'intake' => Receptionist::defaultIntake(),
                'displayName' => $provider->public_name,
                'businessName' => Provider::DEFAULT_BUSINESS_NAME,
                'bookingLinkLine' => Receptionist::bookingLinkLine(route('providers.show', $provider)),
            ],
            // Whether there is a WhatsApp number behind any of this at all.
            // Without it the page would let her write messages nobody will
            // ever receive and never say why.
            'connection' => [
                'connected' => $provider->whatsapp_phone_number_id !== null,
            ],
            'publicUrl' => route('providers.show', $provider),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $provider = $request->user()->provider;

        $validated = $request->validate([
            'mode' => ['required', Rule::in([Provider::BOT_AGENT, Provider::BOT_RECEPTIONIST])],
            'displayName' => ['nullable', 'string', 'max:60'],
            'businessName' => ['nullable', 'string', 'max:80'],
            // Long enough for a real message, short enough that nobody pastes
            // a policy document into a WhatsApp reply.
            'greeting' => ['nullable', 'string', 'max:1000'],
            'greetingReturning' => ['nullable', 'string', 'max:1000'],
            'intake' => ['nullable', 'string', 'max:1000'],
            'offersBookingLink' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:20000'],
            // Misma rejilla de 15 que el resto del horario, y el fin después
            // del principio: una ventana invertida dejaría al bot mudo todo el
            // día sin decir por qué.
            'startMinute' => ['required', 'integer', 'between:0,1440', 'multiple_of:15'],
            'endMinute' => ['required', 'integer', 'between:0,1440', 'multiple_of:15', 'gt:startMinute'],
        ]);

        $provider->update([
            'bot_mode' => $validated['mode'],
            // Empty means "use the default", and an empty string is not that:
            // it would print nothing where her name should go.
            'bot_display_name' => $this->nullIfBlank($validated['displayName'] ?? null),
            'bot_business_name' => $this->nullIfBlank($validated['businessName'] ?? null),
            'bot_greeting' => $this->nullIfBlank($validated['greeting'] ?? null),
            'bot_greeting_returning' => $this->nullIfBlank($validated['greetingReturning'] ?? null),
            'bot_intake' => $this->nullIfBlank($validated['intake'] ?? null),
            'bot_offers_booking_link' => $validated['offersBookingLink'],
            'bot_notes' => $this->nullIfBlank($validated['notes'] ?? null),
            'bot_start_minute' => $validated['startMinute'],
            'bot_end_minute' => $validated['endMinute'],
        ]);

        return to_route('admin.asistente')->with('success', 'admin.assistantSaved');
    }

    private function nullIfBlank(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
