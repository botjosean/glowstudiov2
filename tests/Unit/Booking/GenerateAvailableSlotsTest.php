<?php

namespace Tests\Unit\Booking;

use App\Actions\Booking\GenerateAvailableSlots;
use App\Models\Appointment;
use App\Models\Provider;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateAvailableSlotsTest extends TestCase
{
    use RefreshDatabase;

    private GenerateAvailableSlots $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new GenerateAvailableSlots;
    }

    /**
     * 9:00-20:00 work day, 13:00-14:00 lunch, 15min buffer — mirrors the
     * seeded pati provider so the numbers here read the same as manual testing.
     */
    private function provider(array $overrides = []): Provider
    {
        return Provider::factory()->withSchedule(
            $overrides['work_start_minute'] ?? 540,
            $overrides['work_end_minute'] ?? 1200,
            $overrides['lunch_start_minute'] ?? 780,
            $overrides['lunch_end_minute'] ?? 840,
            $overrides['buffer_minutes'] ?? 15,
        )->create();
    }

    private function service(Provider $provider, int $durationMinutes = 60): Service
    {
        return Service::factory()->for($provider)->create(['duration_minutes' => $durationMinutes]);
    }

    public function test_empty_day_produces_the_full_grid(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        // Lunch disabled so this genuinely tests "no constraints besides
        // work hours" — the lunch-specific interaction has its own tests below.
        $provider = $this->provider(['lunch_start_minute' => 780, 'lunch_end_minute' => 780]);
        $service = $this->service($provider, 60);

        $slots = $this->generator->handle($provider, $service, $provider->currentTime()->addDay()->startOfDay());

        // (1200 - 540) / 15 + 1 candidates fit an empty day for a 60min service.
        $this->assertCount(41, $slots);
        $this->assertSame(540, $slots[0]);
    }

    public function test_last_slot_is_work_end_minus_duration(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        $provider = $this->provider();
        $service = $this->service($provider, 60);

        $slots = $this->generator->handle($provider, $service, $provider->currentTime()->addDay()->startOfDay());

        $this->assertSame(1140, end($slots));
    }

    public function test_slot_ending_exactly_at_lunch_start_is_allowed(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        $provider = $this->provider();
        $service = $this->service($provider, 60);

        $slots = $this->generator->handle($provider, $service, $provider->currentTime()->addDay()->startOfDay());

        $this->assertContains(720, $slots); // 12:00-13:00, ends exactly at lunch start
    }

    public function test_slot_starting_exactly_at_lunch_end_is_allowed(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        $provider = $this->provider();
        $service = $this->service($provider, 60);

        $slots = $this->generator->handle($provider, $service, $provider->currentTime()->addDay()->startOfDay());

        $this->assertContains(840, $slots); // 14:00-15:00, starts exactly at lunch end
    }

    public function test_slot_straddling_lunch_is_dropped(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        $provider = $this->provider();
        $service = $this->service($provider, 60);

        $slots = $this->generator->handle($provider, $service, $provider->currentTime()->addDay()->startOfDay());

        $this->assertNotContains(750, $slots); // 12:30-13:30, crosses into lunch
    }

    public function test_equal_lunch_start_and_end_disables_the_lunch_block(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        $provider = $this->provider(['lunch_start_minute' => 780, 'lunch_end_minute' => 780]);
        $service = $this->service($provider, 60);

        $slots = $this->generator->handle($provider, $service, $provider->currentTime()->addDay()->startOfDay());

        $this->assertContains(750, $slots);
        $this->assertCount(41, $slots);
    }

    public function test_buffer_excludes_before_and_after_an_existing_appointment(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        // Placed at opening (9:00-10:00), away from lunch, so the buffer
        // boundary isn't confounded by the lunch-block test elsewhere.
        $provider = $this->provider();
        $service = $this->service($provider, 40);
        $existingService = $this->service($provider, 60);

        $day = $provider->currentTime()->addDay()->startOfDay();
        Appointment::factory()->for($provider)->forService($existingService)
            ->at($day->setTime(9, 0))->confirmed()->create(); // 9:00-10:00

        $slots = $this->generator->handle($provider, $service, $day);

        $this->assertNotContains(600, $slots); // 10:00, starts exactly at busyEnd, buffer not yet elapsed
        $this->assertContains(615, $slots); // busyEnd(600) + buffer(15)
    }

    public function test_zero_buffer_allows_back_to_back_bookings(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        $provider = $this->provider(['buffer_minutes' => 0]);
        $service = $this->service($provider, 40);
        $existingService = $this->service($provider, 60);

        $day = $provider->currentTime()->addDay()->startOfDay();
        Appointment::factory()->for($provider)->forService($existingService)
            ->at($day->setTime(9, 0))->confirmed()->create(); // 9:00-10:00

        $slots = $this->generator->handle($provider, $service, $day);

        $this->assertContains(600, $slots); // starts exactly when the previous appointment ends
    }

    public function test_max_buffer_of_300_minutes_blocks_a_wide_window(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        $provider = $this->provider(['buffer_minutes' => 300]);
        $service = $this->service($provider, 15);
        $existingService = $this->service($provider, 60);

        $day = $provider->currentTime()->addDay()->startOfDay();
        Appointment::factory()->for($provider)->forService($existingService)
            ->at($day->setTime(9, 0))->confirmed()->create(); // 9:00-10:00

        $slots = $this->generator->handle($provider, $service, $day);

        $this->assertNotContains(885, $slots);
        $this->assertContains(900, $slots); // busyEnd(600) + buffer(300)
    }

    public function test_cancelled_appointment_does_not_block(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        $provider = $this->provider();
        $service = $this->service($provider, 60);

        $day = $provider->currentTime()->addDay()->startOfDay();
        Appointment::factory()->for($provider)->forService($service)
            ->at($day->setTime(12, 0))->cancelled()->create();

        $slots = $this->generator->handle($provider, $service, $day);

        $this->assertContains(720, $slots);
    }

    public function test_closed_appointment_does_not_block(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        $provider = $this->provider();
        $service = $this->service($provider, 60);

        $day = $provider->currentTime()->addDay()->startOfDay();
        Appointment::factory()->for($provider)->forService($service)
            ->at($day->setTime(12, 0))->closed()->create();

        $slots = $this->generator->handle($provider, $service, $day);

        $this->assertContains(720, $slots);
    }

    public function test_pending_appointment_blocks(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        $provider = $this->provider();
        $service = $this->service($provider, 60);

        $day = $provider->currentTime()->addDay()->startOfDay();
        Appointment::factory()->for($provider)->forService($service)
            ->at($day->setTime(12, 0))->pending()->create();

        $slots = $this->generator->handle($provider, $service, $day);

        $this->assertNotContains(720, $slots);
    }

    public function test_duration_longer_than_work_window_yields_no_slots(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        $provider = $this->provider(['work_start_minute' => 540, 'work_end_minute' => 600]); // 1 hour window
        $service = $this->service($provider, 120);

        $slots = $this->generator->handle($provider, $service, $provider->currentTime()->addDay()->startOfDay());

        $this->assertSame([], $slots);
    }

    public function test_work_end_before_or_equal_to_work_start_yields_no_slots(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        // The providers_work_window_chk CHECK constraint makes an inverted
        // window impossible to persist, so this guard is unreachable via a
        // real stored Provider — build an unsaved instance instead; the
        // action only reads attributes off it, no DB round-trip needed.
        $provider = Provider::factory()->withSchedule(600, 540, 0, 0, 15)->make();
        $service = Service::factory()->make(['duration_minutes' => 30]);

        $slots = $this->generator->handle($provider, $service, $provider->currentTime()->addDay()->startOfDay());

        $this->assertSame([], $slots);
    }

    public function test_today_drops_slots_already_past_in_the_providers_timezone(): void
    {
        // 14:30 UTC = 10:30 EDT (America/New_York, UTC-4 in August).
        $this->travelTo(CarbonImmutable::parse('2026-08-02 14:30:00', 'UTC'));

        $provider = $this->provider();
        $service = $this->service($provider, 60);

        $slots = $this->generator->handle($provider, $service, $provider->currentTime()->startOfDay());

        $this->assertNotContains(615, $slots); // 10:15, already past
        $this->assertContains(630, $slots); // 10:30, exactly now — still bookable
    }

    public function test_a_past_date_yields_no_slots(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-10 12:00:00', 'America/New_York'));

        $provider = $this->provider();
        $service = $this->service($provider, 60);

        $slots = $this->generator->handle($provider, $service, $provider->currentTime()->subDay()->startOfDay());

        $this->assertSame([], $slots);
    }

    public function test_a_date_beyond_the_horizon_yields_no_slots(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        $provider = $this->provider();
        $service = $this->service($provider, 60);

        $tooFar = $provider->currentTime()->addDays(GenerateAvailableSlots::HORIZON_DAYS + 1)->startOfDay();
        $slots = $this->generator->handle($provider, $service, $tooFar);

        $this->assertSame([], $slots);
    }

    public function test_dst_transition_day_produces_a_sane_slot_count(): void
    {
        // America/New_York springs forward on 2026-03-08 at 2:00 AM.
        $this->travelTo(CarbonImmutable::parse('2026-03-01 00:00:00', 'America/New_York'));

        $provider = $this->provider();
        $service = $this->service($provider, 60);

        $dstDay = CarbonImmutable::parse('2026-03-08 00:00:00', 'America/New_York');
        $slots = $this->generator->handle($provider, $service, $dstDay);

        // Slot generation works in local minutes throughout, so a DST day
        // produces the same grid as any other day — this documents that,
        // rather than asserting a magic number that would break for the
        // wrong reason if the schedule constants above ever change.
        $this->assertNotEmpty($slots);
        $this->assertSame(540, $slots[0]);
        $this->assertSame(1140, end($slots));
    }

    public function test_for_month_reports_zero_for_a_fully_booked_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        $provider = $this->provider(['work_start_minute' => 540, 'work_end_minute' => 600]); // 1 hour window
        $service = $this->service($provider, 60);

        $day = $provider->currentTime()->addDay()->startOfDay();
        Appointment::factory()->for($provider)->forService($service)
            ->at($day->setTime(9, 0))->confirmed()->create(); // occupies the entire window

        $availability = $this->generator->forMonth($provider, $service, $day);

        $this->assertSame(0, $availability[$day->toDateString()]);
    }
}
