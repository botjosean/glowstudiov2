<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class AppointmentStatusController extends Controller
{
    /**
     * Ownership is enforced by ->can('update', 'appointment') in routes/web.php.
     *
     * An illegal transition is a 422, not a 403 — 403 is reserved exclusively
     * for ownership so assertForbidden() in tests means one thing only, and
     * because Inertia renders a 403 as a full-page error, which is wrong for
     * "you clicked Confirm on a stale list".
     */
    public function confirm(Appointment $appointment): RedirectResponse
    {
        $this->transition($appointment, AppointmentStatus::Confirmed);

        $appointment->update([
            'status' => AppointmentStatus::Confirmed->value,
            'confirmed_at' => now(),
        ]);

        return to_route('admin.citas')->with('success', 'admin.appointmentConfirmed');
    }

    public function cancel(Appointment $appointment): RedirectResponse
    {
        $this->transition($appointment, AppointmentStatus::Cancelled);

        $appointment->update([
            'status' => AppointmentStatus::Cancelled->value,
            'cancelled_at' => now(),
        ]);

        return to_route('admin.citas')->with('success', 'admin.appointmentCancelled');
    }

    private function transition(Appointment $appointment, AppointmentStatus $target): void
    {
        if (! $appointment->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => __('admin.invalidTransition'),
            ]);
        }
    }
}
