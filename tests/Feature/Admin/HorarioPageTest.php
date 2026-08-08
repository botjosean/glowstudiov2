<?php

namespace Tests\Feature\Admin;

use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HorarioPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_values_are_all_integers_in_minutes(): void
    {
        $provider = Provider::factory()->withSchedule(540, 1200, 780, 840, 15)->published()->create();

        $this->actingAs($provider->user)->get('/admin/horario')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Horario')
            ->where('schedule.lunchStart', 780)
            ->where('schedule.lunchEnd', 840)
            ->where('schedule.bufferMinutes', 15)
            ->where('schedule.bufferMinutes', fn ($v) => is_int($v))
            ->where('schedule.days.0.workStart', 540)
            ->where('schedule.days.0.workEnd', 1200)
            ->where('schedule.days.0.workStart', fn ($v) => is_int($v))
        );
    }

    /**
     * The page renders a row per day unconditionally, so a short list would
     * silently drop a weekday from the form rather than fail loudly.
     */
    public function test_all_seven_weekdays_are_always_sent(): void
    {
        $provider = Provider::factory()->withSchedule(540, 1200, 780, 840, 15)->published()->create();

        $this->actingAs($provider->user)->get('/admin/horario')->assertInertia(fn (Assert $page) => $page
            ->has('schedule.days', 7)
            ->where('schedule.days', fn ($days) => collect($days)->pluck('weekday')->all() === [0, 1, 2, 3, 4, 5, 6])
            ->where('schedule.days', fn ($days) => collect($days)->every(fn ($day) => $day['isOpen'] === true))
        );
    }

    public function test_blocked_dates_are_listed_with_their_ids(): void
    {
        $provider = Provider::factory()->published()->create();
        $block = $provider->timeOff()->create([
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-05',
            'reason' => 'Vacaciones',
        ]);

        $this->actingAs($provider->user)->get('/admin/horario')->assertInertia(fn (Assert $page) => $page
            ->has('timeOff', 1)
            ->where('timeOff.0.id', $block->id)
            ->where('timeOff.0.startsOn', '2026-09-01')
            ->where('timeOff.0.endsOn', '2026-09-05')
            ->where('timeOff.0.reason', 'Vacaciones')
        );
    }

    public function test_another_providers_blocked_dates_are_not_listed(): void
    {
        $mine = Provider::factory()->published()->create();
        $theirs = Provider::factory()->published()->create();
        $theirs->timeOff()->create(['starts_on' => '2026-09-01', 'ends_on' => '2026-09-05']);

        $this->actingAs($mine->user)->get('/admin/horario')->assertInertia(fn (Assert $page) => $page
            ->has('timeOff', 0)
        );
    }
}
