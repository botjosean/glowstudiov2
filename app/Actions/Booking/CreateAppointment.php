<?php

namespace App\Actions\Booking;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Provider;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateAppointment
{
    public function __construct(
        private readonly GenerateAvailableSlots $slots,
    ) {}

    /**
     * Creates a pending appointment after revalidating the slot is still
     * free *inside* a row lock on the provider. Validating in the request
     * and inserting afterwards (without the lock) would leave a TOCTOU
     * window a double-booking could slip through.
     *
     * @throws ValidationException when the slot is no longer available
     */
    public function handle(
        Provider $provider,
        Service $service,
        CarbonImmutable $localStart,
        string $clientName,
        string $phoneDigits,
    ): Appointment {
        return DB::transaction(function () use ($provider, $service, $localStart, $clientName, $phoneDigits) {
            // Serializes bookings per provider — the portable guarantee
            // against double-booking. The Postgres exclusion constraint on
            // `appointments` is defense in depth, caught below.
            $lockedProvider = Provider::whereKey($provider->id)->lockForUpdate()->firstOrFail();

            $requestedMinute = $localStart->hour * 60 + $localStart->minute;
            $availableMinutes = $this->slots->handle($lockedProvider, $service, $localStart->startOfDay());

            if (! in_array($requestedMinute, $availableMinutes, true)) {
                throw ValidationException::withMessages([
                    'time' => __('booking.slot_unavailable'),
                ]);
            }

            try {
                return Appointment::create([
                    'provider_id' => $lockedProvider->id,
                    'service_id' => $service->id,
                    'client_name' => $clientName,
                    'client_phone' => $phoneDigits,
                    'service_name' => $service->name,
                    'duration_minutes' => $service->duration_minutes,
                    'price' => $service->price,
                    'starts_at' => $localStart->utc(),
                    'ends_at' => $localStart->addMinutes($service->duration_minutes)->utc(),
                    'status' => AppointmentStatus::Pending->value,
                ]);
            } catch (QueryException $e) {
                // 23P01 = exclusion_violation. The row lock above should make
                // this unreachable in practice; this only guards against a
                // concurrent write outside the lock (e.g. a direct DB write).
                if ($e->getCode() === '23P01') {
                    throw ValidationException::withMessages([
                        'time' => __('booking.slot_unavailable'),
                    ]);
                }

                throw $e;
            }
        });
    }
}
