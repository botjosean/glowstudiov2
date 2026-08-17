<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Support\Kapso\KapsoClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AppointmentStatusController extends Controller
{
    public function __construct(private readonly KapsoClient $kapso) {}

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

        $notified = $this->notifyClient($appointment, 'Confirmed');

        return to_route('admin.citas')->with('success', 'admin.appointmentConfirmed')->with('notified', $notified);
    }

    public function cancel(Appointment $appointment): RedirectResponse
    {
        // Read before transition() validates and update() overwrites it —
        // it's what tells a rejection from a cancellation apart.
        $variant = $appointment->status === AppointmentStatus::Pending ? 'Rejected' : 'Cancelled';

        $this->transition($appointment, AppointmentStatus::Cancelled);

        $appointment->update([
            'status' => AppointmentStatus::Cancelled->value,
            'cancelled_at' => now(),
        ]);

        $notified = $this->notifyClient($appointment, $variant);

        return to_route('admin.citas')->with('success', 'admin.appointmentCancelled')->with('notified', $notified);
    }

    private function transition(Appointment $appointment, AppointmentStatus $target): void
    {
        if (! $appointment->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => __('admin.invalidTransition'),
            ]);
        }
    }

    /**
     * Sends the client-status notice through the provider's own connected
     * WhatsApp instead of asking her to send it by hand — only when there's a
     * bot on that number. A Kapso failure must never fail the status change
     * that already committed, so it's caught here; the frontend falls back to
     * its manual prompt whenever this returns false.
     */
    private function notifyClient(Appointment $appointment, string $variant): bool
    {
        $provider = $appointment->provider;

        if ($provider->whatsapp_phone_number_id === null) {
            return false;
        }

        $message = __("admin.waMessage{$variant}", [
            'client' => $appointment->client_name,
            'provider' => $provider->public_name,
            'service' => $appointment->service_name,
            'date' => $this->dateLabelFor($appointment),
        ]);

        try {
            $this->kapso->sendText(
                phoneNumberId: $provider->whatsapp_phone_number_id,
                to: '1'.$appointment->client_phone,
                body: $message,
            );

            return true;
        } catch (RuntimeException $exception) {
            Log::warning('Auto-notify via Kapso failed after a status change.', [
                'appointment_id' => $appointment->id,
                'provider' => $provider->slug,
                'variant' => $variant,
                'reason' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function dateLabelFor(Appointment $appointment): string
    {
        $localStart = $appointment->starts_at
            ->setTimezone($appointment->provider->timezone)
            ->locale(app()->getLocale());

        return app()->getLocale() === 'es'
            ? $localStart->translatedFormat('l j \d\e F, g:i A')
            : $localStart->translatedFormat('l, F j, g:i A');
    }
}
