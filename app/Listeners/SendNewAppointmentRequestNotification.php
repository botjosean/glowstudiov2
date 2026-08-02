<?php

namespace App\Listeners;

use App\Events\AppointmentRequested;
use App\Notifications\NewAppointmentRequest;

class SendNewAppointmentRequestNotification
{
    public function handle(AppointmentRequested $event): void
    {
        $appointment = $event->appointment->loadMissing('provider.user');

        $appointment->provider->user->notify(new NewAppointmentRequest($appointment));
    }
}
