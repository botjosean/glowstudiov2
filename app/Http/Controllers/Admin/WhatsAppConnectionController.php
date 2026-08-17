<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Support\Kapso\KapsoClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Connecting a professional's own WhatsApp, from the app.
 *
 * Until now this took a person: someone created the customer in Kapso's panel,
 * walked the professional through Meta, read the phone number id off a screen,
 * pasted it into the database and added the webhook by hand. Every one of
 * those steps except the one Meta insists on is an API call, so every one of
 * them is done here.
 *
 * **Her own number, never a provisioned one.** A number Kapso provisions
 * cannot be used from a phone at all — it lives only in the API — so the
 * professional would read her clients in a browser while her hands are busy.
 * Coexistence keeps her number, her chats and her app, which is the only shape
 * that fits the receptionist design: the assistant says two messages and *she*
 * takes over, from the phone in her pocket.
 *
 * The one irreducible manual step is Meta's own: she logs in with her Facebook
 * and scans a code with the WhatsApp Business app. Nobody can do that for her,
 * and no BSP can skip it — it is how Meta establishes that the number is hers.
 */
class WhatsAppConnectionController extends Controller
{
    public function __construct(private readonly KapsoClient $kapso) {}

    /**
     * Sends her to Kapso's hosted page with everything already set up.
     */
    public function store(Request $request): RedirectResponse
    {
        $provider = $request->user()->provider;

        if ($provider->whatsapp_phone_number_id !== null) {
            return to_route('admin.asistente')->with('warning', 'admin.botAlreadyConnected');
        }

        try {
            $customerId = $this->kapso->findOrCreateCustomer(
                externalId: $provider->slug,
                name: $provider->botBusinessName().' — '.$provider->public_name,
            );

            $link = $this->kapso->createSetupLink(
                customerId: $customerId,
                successUrl: route('admin.asistente.conectado'),
                failureUrl: route('admin.asistente').'?conexion=fallo',
            );
        } catch (RuntimeException $exception) {
            Log::error('Could not start the WhatsApp connection flow.', [
                'provider' => $provider->slug,
                'reason' => $exception->getMessage(),
            ]);

            return to_route('admin.asistente')->with('error', 'admin.botConnectFailed');
        }

        // Away to Kapso, which is a different host — hence a plain redirect
        // rather than an Inertia one, which would try to fetch it as JSON.
        return redirect()->away($link['url']);
    }

    /**
     * Where Kapso sends her back once Meta is done.
     *
     * The redirect is only a hint that she finished, never proof: she could
     * have closed the tab, or Meta could still be settling. The truth is asked
     * of Kapso — a number that says CONNECTED and belongs to this provider's
     * own customer — and nothing is written until that answers yes.
     */
    public function callback(Request $request): RedirectResponse
    {
        $provider = $request->user()->provider;

        if ($provider->whatsapp_phone_number_id !== null) {
            return to_route('admin.asistente');
        }

        try {
            $customerId = $this->kapso->findOrCreateCustomer(
                externalId: $provider->slug,
                name: $provider->botBusinessName().' — '.$provider->public_name,
            );

            $number = $this->kapso->connectedNumberFor($customerId);
        } catch (RuntimeException $exception) {
            Log::error('Could not read back the WhatsApp connection.', [
                'provider' => $provider->slug,
                'reason' => $exception->getMessage(),
            ]);

            return to_route('admin.asistente')->with('error', 'admin.botConnectFailed');
        }

        if ($number === null) {
            // Meta can take a moment, and she may simply have abandoned the
            // flow. Either way this is "not yet", not "broken".
            return to_route('admin.asistente')->with('warning', 'admin.botConnectPending');
        }

        if ($this->claimedByAnotherProvider($number['phone_number_id'], $provider)) {
            // Two providers must never share a number: every inbound message
            // is routed by it, so the second one would answer the first one's
            // clients with the wrong catalogue and the wrong name.
            Log::error('A WhatsApp number came back already assigned to another provider.', [
                'provider' => $provider->slug,
            ]);

            return to_route('admin.asistente')->with('error', 'admin.botNumberTaken');
        }

        $provider->update(['whatsapp_phone_number_id' => $number['phone_number_id']]);

        return to_route('admin.asistente')->with(
            'success',
            $this->ensureWebhook($number['phone_number_id'], $provider)
                ? 'admin.botConnected'
                : 'admin.botConnectedNoWebhook',
        );
    }

    /**
     * Points the number at this app, and says whether it worked.
     *
     * Verified rather than assumed: a connected number with no webhook is
     * *silent*, and silence looks exactly like a quiet afternoon from the
     * panel. Better to tell her a step is missing than to let her believe the
     * assistant is listening when it is not.
     */
    private function ensureWebhook(string $phoneNumberId, Provider $provider): bool
    {
        $url = route('api.kapso.webhook');
        $secret = (string) config('services.kapso.webhook_secret');

        if ($secret === '') {
            Log::error('No Kapso webhook secret is configured; the new number cannot be wired up.', [
                'provider' => $provider->slug,
            ]);

            return false;
        }

        try {
            if ($this->kapso->hasWebhookFor($phoneNumberId, $url)) {
                return true;
            }

            $this->kapso->createWebhook($phoneNumberId, $url, $secret);

            // Asked again rather than trusting the 201: this is the difference
            // between an assistant that answers and one that never hears
            // anybody, and it is worth one extra request to be sure.
            return $this->kapso->hasWebhookFor($phoneNumberId, $url);
        } catch (RuntimeException $exception) {
            Log::error('Could not attach the webhook to a newly connected number.', [
                'provider' => $provider->slug,
                'reason' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function claimedByAnotherProvider(string $phoneNumberId, Provider $provider): bool
    {
        return Provider::query()
            ->where('whatsapp_phone_number_id', $phoneNumberId)
            ->whereKeyNot($provider->id)
            ->exists();
    }
}
