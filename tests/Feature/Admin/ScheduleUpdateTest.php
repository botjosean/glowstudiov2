<?php

namespace Tests\Feature\Admin;

use App\Models\Appointment;
use App\Models\Provider;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScheduleUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'workStart' => 540,
            'workEnd' => 1200,
            'lunchStart' => 780,
            'lunchEnd' => 840,
            'bufferMinutes' => 15,
        ], $overrides);
    }

    public function test_updating_the_schedule(): void
    {
        $provider = Provider::factory()->withSchedule(0, 60, 0, 0, 0)->published()->create();

        $response = $this->actingAs($provider->user)
            ->put('/admin/horario', $this->validPayload());

        $response->assertSessionHasNoErrors();
        $provider->refresh();
        $this->assertSame(540, $provider->work_start_minute);
        $this->assertSame(1200, $provider->work_end_minute);
        $this->assertSame(780, $provider->lunch_start_minute);
        $this->assertSame(840, $provider->lunch_end_minute);
        $this->assertSame(15, $provider->buffer_minutes);
        $this->assertSame('admin.scheduleUpdated', session('success'));
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function invalidPayloads(): iterable
    {
        yield 'end before start' => [['workStart' => 1000, 'workEnd' => 540]];
        yield 'start not a multiple of 15' => [['workStart' => 542]];
        yield 'buffer over max' => [['bufferMinutes' => 315]];
        yield 'buffer not a multiple of 15' => [['bufferMinutes' => 20]];
        yield 'negative buffer' => [['bufferMinutes' => -15]];
    }

    #[DataProvider('invalidPayloads')]
    public function test_validation_rejects_invalid_payloads(array $overrides): void
    {
        $provider = Provider::factory()->withSchedule(540, 1200, 780, 840, 15)->published()->create();

        $this->actingAs($provider->user)
            ->put('/admin/horario', $this->validPayload($overrides))
            ->assertSessionHasErrors();

        $provider->refresh();
        $this->assertSame(540, $provider->work_start_minute); // unchanged
    }

    public function test_shrinking_the_day_warns_about_appointments_now_outside_hours(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        $provider = Provider::factory()->withSchedule(540, 1200, 780, 840, 15)->published()->create();
        $service = Service::factory()->for($provider)->create(['duration_minutes' => 60]);

        $tomorrow = $provider->currentTime()->addDay();
        Appointment::factory()->for($provider)->forService($service)
            ->at($tomorrow->setTime(19, 0))->confirmed()->create(); // 19:00-20:00

        $this->actingAs($provider->user)->put('/admin/horario', $this->validPayload([
            'workStart' => 540, 'workEnd' => 1080, // shrink to 9:00-18:00
        ]))->assertSessionHasNoErrors();

        $this->assertSame(
            ['key' => 'admin.scheduleConflictWarning', 'count' => 1],
            session('warning'),
        );
    }

    public function test_no_warning_when_nothing_falls_outside_the_new_hours(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        $provider = Provider::factory()->withSchedule(540, 1200, 780, 840, 15)->published()->create();
        $service = Service::factory()->for($provider)->create(['duration_minutes' => 60]);

        $tomorrow = $provider->currentTime()->addDay();
        Appointment::factory()->for($provider)->forService($service)
            ->at($tomorrow->setTime(10, 0))->confirmed()->create(); // stays inside 9:00-18:00

        $this->actingAs($provider->user)->put('/admin/horario', $this->validPayload([
            'workStart' => 540, 'workEnd' => 1080,
        ]));

        $this->assertNull(session('warning'));
    }

    public function test_a_provider_cannot_change_another_providers_schedule(): void
    {
        $mine = Provider::factory()->withSchedule(540, 1200, 780, 840, 15)->published()->create();
        $theirs = Provider::factory()->withSchedule(600, 1080, 0, 0, 30)->published()->create();

        $this->actingAs($mine->user)->put('/admin/horario', $this->validPayload());

        $theirs->refresh();
        $this->assertSame(600, $theirs->work_start_minute);
        $this->assertSame(1080, $theirs->work_end_minute);
        $this->assertSame(30, $theirs->buffer_minutes);
    }
}
