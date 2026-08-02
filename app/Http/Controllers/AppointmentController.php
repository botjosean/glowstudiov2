<?php

namespace App\Http\Controllers;

use App\Actions\Booking\CreateAppointment;
use App\Events\AppointmentRequested;
use App\Http\Requests\StoreAppointmentRequest;
use App\Models\Provider;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;

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
        ]);
    }
}
