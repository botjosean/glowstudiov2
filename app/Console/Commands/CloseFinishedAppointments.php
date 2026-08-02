<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('appointments:close-finished')]
#[Description('Mark confirmed appointments whose end time has passed as closed.')]
class CloseFinishedAppointments extends Command
{
    /**
     * Execute the console command.
     *
     * Only Confirmed -> Closed (exactly AppointmentStatus::canTransitionTo()'s
     * one command-driven transition). Pending appointments are left alone —
     * a provider who never acted on one stays responsible for it.
     *
     * ends_at, not starts_at: an in-progress appointment must not close.
     *
     * starts_at/ends_at are absolute UTC instants (see AppointmentSeeder),
     * so "this appointment has ended" is the same fact for every observer
     * regardless of the owning provider's timezone — a plain UTC comparison
     * is correct, not just simpler. now()->utc() rather than bare now() so
     * this stays correct even if config('app.timezone') ever changes.
     */
    public function handle(): int
    {
        $closed = Appointment::query()
            ->where('status', AppointmentStatus::Confirmed->value)
            ->where('ends_at', '<=', now()->utc())
            ->update(['status' => AppointmentStatus::Closed->value]);

        $this->info("Closed {$closed} appointment(s).");

        return self::SUCCESS;
    }
}
