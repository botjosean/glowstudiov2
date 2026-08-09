<?php

namespace App\Http\Controllers;

use App\Actions\Booking\CreateAppointment;
use App\Events\AppointmentRequested;
use App\Http\Requests\StoreAppointmentRequest;
use App\Models\Provider;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    /**
     * Books a pending appointment. Public, unauthenticated — the route
     * carries the `booking` rate limiter since this creates rows with no
     * captcha in front of it.
     */
    public function store(
        StoreAppointmentRequest $request,
        Provider $provider,
        Service $service,
        CreateAppointment $createAppointment,
    ): RedirectResponse {
        abort_unless($provider->published_at !== null && $service->is_active, 404);

        $data = $request->validated();

        // Home service must be doubly enabled — by the provider (she also
        // goes out) and by this exact service (her per-service safety
        // switch). A pure-mobile provider books as always: everything is
        // already at the client's. Enforced server-side so a crafted POST
        // cannot force a home visit the professional never offered.
        $atHome = (bool) ($data['atHome'] ?? false);

        if ($atHome && ! ($service->home_available && $provider->home_service && ! $provider->is_mobile)) {
            throw ValidationException::withMessages([
                'atHome' => __('booking.home_not_available'),
            ]);
        }

        $localStart = CarbonImmutable::createFromFormat(
            'Y-m-d H:i',
            "{$data['date']} {$data['time']}",
            $provider->timezone,
        );

        $appointment = $createAppointment->handle(
            provider: $provider,
            service: $service,
            localStart: $localStart,
            clientName: $data['fullName'],
            phoneDigits: $data['phone'],
            atHome: $atHome,
            clientAddress: $atHome ? trim((string) $data['address']) : null,
        );

        // Dispatched here, not inside CreateAppointment's transaction — by
        // this point the transaction has already committed, so a queued
        // listener can never run against a row the DB hasn't persisted yet.
        event(new AppointmentRequested($appointment));

        return back()->with('booking', [
            'id' => $appointment->id,
            'clientName' => $appointment->client_name,
            'serviceName' => $appointment->service_name,
            'providerName' => $provider->public_name,
            'startsAt' => $appointment->starts_at->toIso8601String(),
            'atHome' => $appointment->at_home,
        ]);
    }
}
