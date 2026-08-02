<?php

namespace App\Events;

use App\Models\Appointment;

/**
 * Fired once the appointment row has actually committed — see
 * AppointmentController::store(), which dispatches this after
 * CreateAppointment::handle() returns, not from inside its transaction.
 */
class AppointmentRequested
{
    public function __construct(public readonly Appointment $appointment) {}
}
