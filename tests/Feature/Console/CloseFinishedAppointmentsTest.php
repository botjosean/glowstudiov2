<?php

namespace Tests\Feature\Console;

use App\Models\Appointment;
use App\Models\Provider;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CloseFinishedAppointmentsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Each fixture lives on its own provider — the appointments_no_overlap
     * exclusion constraint would reject two blocking (pending/confirmed)
     * rows on the same provider with overlapping ranges, and several of
     * these fixtures sit close together in time on purpose.
     */
    private function appointmentAt(string $status, CarbonImmutable $startsAt, CarbonImmutable $endsAt): Appointment
    {
        return Appointment::factory()
            ->for(Provider::factory()->create())
            ->create(['status' => $status, 'starts_at' => $startsAt, 'ends_at' => $endsAt]);
    }

    public function test_closes_past_and_boundary_confirmed_appointments_only(): void
    {
        $now = CarbonImmutable::parse('2026-08-02 18:00:00', 'UTC');
        $this->travelTo($now);

        $pastConfirmed = $this->appointmentAt('confirmed', $now->subHours(2), $now->subHour());
        $boundaryConfirmed = $this->appointmentAt('confirmed', $now->subHour(), $now); // ends_at === now
        $inProgressConfirmed = $this->appointmentAt('confirmed', $now->subMinutes(30), $now->addMinutes(30));
        $tomorrowConfirmed = $this->appointmentAt('confirmed', $now->addDay(), $now->addDay()->addHour());
        $pastPending = $this->appointmentAt('pending', $now->subHours(2), $now->subHour());
        $pastCancelled = $this->appointmentAt('cancelled', $now->subHours(2), $now->subHour());
        // A second provider's past confirmed appointment — proves the command is global, not scoped.
        $otherProviderPastConfirmed = $this->appointmentAt('confirmed', $now->subHours(2), $now->subHour());

        $this->artisan('appointments:close-finished')->assertExitCode(0);

        $this->assertSame('closed', $pastConfirmed->fresh()->status->value);
        $this->assertSame('closed', $boundaryConfirmed->fresh()->status->value);
        $this->assertSame('confirmed', $inProgressConfirmed->fresh()->status->value);
        $this->assertSame('confirmed', $tomorrowConfirmed->fresh()->status->value);
        $this->assertSame('pending', $pastPending->fresh()->status->value);
        $this->assertSame('cancelled', $pastCancelled->fresh()->status->value);
        $this->assertSame('closed', $otherProviderPastConfirmed->fresh()->status->value);
    }

    public function test_the_command_is_registered_on_the_hourly_schedule(): void
    {
        // A command that isn't scheduled is a silent no-op — this catches
        // that. Black-box via schedule:list's own output rather than
        // resolving Schedule::class directly: afterResolving() hooks (how
        // withSchedule() wires this up) proved order-sensitive across test
        // methods sharing a process, this doesn't.
        $this->artisan('schedule:list')
            ->expectsOutputToContain('appointments:close-finished')
            ->assertExitCode(0);
    }
}
