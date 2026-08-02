<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    /**
     * Covers both confirm and cancel — ownership is the only distinction;
     * the legal-transition check lives in AppointmentStatus::canTransitionTo().
     */
    public function update(User $user, Appointment $appointment): bool
    {
        $user->loadMissing('provider');

        return $appointment->provider_id === $user->provider?->id;
    }
}
