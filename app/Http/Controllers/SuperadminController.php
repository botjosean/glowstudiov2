<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\User;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SuperadminController extends Controller
{
    /**
     * How many appointments the panel lists for one-by-one cleanup. Test
     * data lives in the recent past/future, so the newest window is enough.
     */
    private const APPOINTMENTS_LIMIT = 100;

    public function index(): Response
    {
        $users = User::query()->with('provider')->orderBy('id')->get();

        return Inertia::render('Superadmin/Panel', [
            'accounts' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => Format::usPhone((string) $user->phone),
                'providerName' => $user->provider?->public_name,
                'published' => $user->provider?->published_at !== null,
                'servicesCount' => $user->provider ? $user->provider->services()->count() : 0,
                'appointmentsCount' => $user->provider ? $user->provider->appointments()->count() : 0,
                'protected' => $this->isProtected($user),
                'protectedReason' => $this->protectedReason($user),
            ])->values()->all(),
            'appointments' => Appointment::query()
                ->with('provider')
                ->orderByDesc('starts_at')
                ->limit(self::APPOINTMENTS_LIMIT)
                ->get()
                ->map(fn (Appointment $appointment) => [
                    'id' => $appointment->id,
                    'clientName' => $appointment->client_name,
                    'clientPhone' => Format::usPhone($appointment->client_phone),
                    'service' => $appointment->service_name,
                    'provider' => $appointment->provider->public_name,
                    'status' => $appointment->status->value,
                    'startsAt' => $appointment->starts_at->toIso8601String(),
                ])->values()->all(),
        ]);
    }

    /**
     * Deletes every account that is not protected, with everything that
     * hangs from it (provider, services, photos, appointments). Protected =
     * superadmins and any professional whose WhatsApp is connected to the
     * bot — deleting those would break a live number, so this button can
     * never touch them regardless of what it's asked.
     */
    public function wipe(): RedirectResponse
    {
        DB::transaction(function (): void {
            User::query()->with('provider')->get()
                ->reject(fn (User $user) => $this->isProtected($user))
                ->each(function (User $user): void {
                    if ($user->provider !== null) {
                        // Appointments first: they reference services.
                        $user->provider->appointments()->delete();
                        $user->provider->services()->delete();
                        $user->provider->photos()->delete();
                        $user->provider->delete();
                    }

                    $user->delete();
                });
        });

        return back()->with('success', 'superadmin.wiped');
    }

    public function destroyAppointment(Appointment $appointment): RedirectResponse
    {
        $appointment->delete();

        return back()->with('success', 'superadmin.appointmentDeleted');
    }

    private function isProtected(User $user): bool
    {
        return $this->protectedReason($user) !== null;
    }

    /**
     * @return 'superadmin'|'whatsapp'|null
     */
    private function protectedReason(User $user): ?string
    {
        if (in_array($user->username, config('app.superadmins'), true)) {
            return 'superadmin';
        }

        if ($user->provider?->whatsapp_phone_number_id !== null) {
            return 'whatsapp';
        }

        return null;
    }
}
