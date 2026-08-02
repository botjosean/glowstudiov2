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
            ->where('schedule.workStart', 540)
            ->where('schedule.workEnd', 1200)
            ->where('schedule.lunchStart', 780)
            ->where('schedule.lunchEnd', 840)
            ->where('schedule.bufferMinutes', 15)
            ->where('schedule.workStart', fn ($v) => is_int($v))
            ->where('schedule.bufferMinutes', fn ($v) => is_int($v))
        );
    }
}
