<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Booking\CreateAppointment;
use App\Actions\Booking\GenerateAvailableSlots;
use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\Service;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Appointments the professional books herself from the panel's agenda —
 * walk-ins and phone bookings.
 *
 * Everything funnels through the same CreateAppointment/GenerateAvailableSlots
 * pair the bot and the public page use, so a manual appointment blocks the
 * slot for every other channel exactly like any other, and a taken slot is
 * rejected inside the same lock. The only difference is the resulting status:
 * a professional booking for a client standing in front of her has already
 * agreed — it is born confirmed, with no notification round-trip.
 */
class ManualAppointmentController extends Controller
{
    /**
     * Free start times for one of the provider's own services on one day,
     * for the create-appointment sheet. `value` is the machine format the
     * store() endpoint expects; `label` is what the professional reads.
     */
    public function slots(Request $request, GenerateAvailableSlots $slots): JsonResponse
    {
        $provider = $request->user()->provider;

        $validated = $request->validate([
            'serviceId' => ['required', 'integer'],
            'fecha' => ['required', 'date_format:Y-m-d'],
        ]);

        $service = $this->ownActiveService($provider->services(), (int) $validated['serviceId']);

        $date = CarbonImmutable::createFromFormat('Y-m-d', $validated['fecha'], $provider->timezone)->startOfDay();

        return response()->json([
            'horas' => array_map(
                static fn (int $minute): array => [
                    'value' => sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60),
                    'label' => Format::clock($minute),
                ],
                $slots->handle($provider, $service, $date),
            ),
        ]);
    }

    public function store(Request $request, CreateAppointment $createAppointment): RedirectResponse
    {
        $provider = $request->user()->provider;

        $validated = $request->validate([
            'serviceId' => ['required', 'integer'],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora' => ['required', 'date_format:H:i'],
            'clientName' => ['required', 'string', 'max:120'],
            'clientPhone' => ['nullable', 'string', 'max:30'],
        ]);

        $service = $this->ownActiveService($provider->services(), (int) $validated['serviceId']);

        // Optional on purpose: a walk-in may not leave a phone, and forcing
        // the field invites invented digits — which can belong to a real
        // person the assistant would then confuse with this client. Empty
        // stays empty; the WhatsApp assistant simply won't recognize her.
        $phoneDigits = Format::digitsOnly($validated['clientPhone'] ?? '');

        if ($phoneDigits !== '' && strlen($phoneDigits) !== 10) {
            throw ValidationException::withMessages([
                'clientPhone' => __('admin.phoneInvalid'),
            ]);
        }

        $localStart = CarbonImmutable::createFromFormat(
            'Y-m-d H:i',
            "{$validated['fecha']} {$validated['hora']}",
            $provider->timezone,
        );

        $appointment = $createAppointment->handle(
            $provider,
            $service,
            $localStart,
            trim($validated['clientName']),
            $phoneDigits,
        );

        $appointment->update([
            'status' => AppointmentStatus::Confirmed->value,
            'confirmed_at' => now(),
        ]);

        return to_route('admin.citas')->with('success', 'admin.appointmentCreated');
    }

    /**
     * @param  HasMany<Service, Provider>  $services
     */
    private function ownActiveService($services, int $serviceId): Service
    {
        $service = $services->active()->whereKey($serviceId)->first();

        if ($service === null) {
            throw ValidationException::withMessages([
                'serviceId' => __('admin.invalidService'),
            ]);
        }

        return $service;
    }
}
