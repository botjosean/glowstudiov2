<?php

namespace Tests\Feature\Public;

use App\Models\Appointment;
use App\Models\Provider;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BookingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_service_from_another_provider_404s(): void
    {
        $provider = Provider::factory()->published()->create();
        $otherProvider = Provider::factory()->published()->create();
        $otherService = Service::factory()->for($otherProvider)->create();

        $this->get("/reservar/{$provider->slug}/{$otherService->id}")->assertNotFound();
    }

    public function test_an_inactive_service_404s(): void
    {
        $provider = Provider::factory()->published()->create();
        $service = Service::factory()->for($provider)->inactive()->create();

        $this->get("/reservar/{$provider->slug}/{$service->id}")->assertNotFound();
    }

    public function test_an_unpublished_provider_404s(): void
    {
        $provider = Provider::factory()->unpublished()->create();
        $service = Service::factory()->for($provider)->create();

        $this->get("/reservar/{$provider->slug}/{$service->id}")->assertNotFound();
    }

    public function test_default_view_shows_todays_slots_and_a_full_months_availability(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 12:00:00', 'America/New_York'));

        $provider = Provider::factory()->published()->create();
        $service = Service::factory()->for($provider)->create(['duration_minutes' => 60]);

        $this->get("/reservar/{$provider->slug}/{$service->id}")->assertInertia(fn (Assert $page) => $page
            ->component('Public/Booking')
            ->where('provider.slug', $provider->slug)
            ->where('service.id', $service->id)
            ->where('selectedDate', '2026-08-01')
            ->has('slots')
            ->has('monthAvailability.2026-08-01')
        );
    }

    public function test_a_fully_booked_day_reports_zero_availability(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        $provider = Provider::factory()->withSchedule(540, 600, 0, 0, 0)->published()->create(); // 1hr window
        $service = Service::factory()->for($provider)->create(['duration_minutes' => 60]);

        $day = $provider->currentTime()->addDay()->startOfDay();
        Appointment::factory()->for($provider)->forService($service)->at($day->setTime(9, 0))->confirmed()->create();

        $this->get("/reservar/{$provider->slug}/{$service->id}?date=".$day->toDateString())
            ->assertInertia(fn (Assert $page) => $page
                ->where('slots', [])
                ->where("monthAvailability.{$day->toDateString()}", 0)
            );
    }

    public function test_requesting_a_specific_date_returns_that_dates_slots(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        $provider = Provider::factory()->published()->create();
        $service = Service::factory()->for($provider)->create(['duration_minutes' => 60]);

        $requestedDate = $provider->currentTime()->addDays(5)->toDateString();

        $this->get("/reservar/{$provider->slug}/{$service->id}?date={$requestedDate}")
            ->assertInertia(fn (Assert $page) => $page->where('selectedDate', $requestedDate));
    }
}
