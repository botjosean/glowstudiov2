<?php

namespace Tests\Feature\Public;

use App\Enums\ServiceCategory;
use App\Enums\ServiceIcon;
use App\Models\Provider;
use App\Models\Service;
use App\Models\ServiceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_reflect_active_service_type_and_published_provider_counts(): void
    {
        ServiceType::factory()->count(3)->create();
        Provider::factory()->published()->count(2)->create();
        Provider::factory()->unpublished()->create();

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->component('Public/Home')
            ->where('stats.services', 3)
            ->where('stats.providers', 2)
        );
    }

    public function test_providers_count_is_distinct_not_a_raw_row_count(): void
    {
        // One provider offering TWO services of the SAME type must count as
        // 1 provider, not 2 — a plain withCount() would emit count(*) and
        // double-count this.
        $type = ServiceType::factory()->create();
        $provider = Provider::factory()->published()->create();
        Service::factory()->count(2)->for($provider)->ofType($type)->create();

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('services.0.providersCount', 1)
        );
    }

    public function test_services_expose_a_formatted_duration_string_and_integer_price(): void
    {
        $type = ServiceType::factory()->icon(ServiceIcon::Sparkles)->create();
        $provider = Provider::factory()->published()->create();
        Service::factory()->for($provider)->ofType($type)->create([
            'duration_minutes' => 90,
            'price' => 55,
            'category' => ServiceCategory::Color->value,
        ]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('services.0.duration', '1h 30 min')
            ->where('services.0.price', 55)
            ->where('services.0.icon', 'sparkles')
        );
    }

    public function test_only_the_four_lowest_position_types_with_services_are_shown(): void
    {
        $provider = Provider::factory()->published()->create();

        foreach (range(1, 6) as $position) {
            $type = ServiceType::factory()->create(['position' => $position]);
            Service::factory()->for($provider)->ofType($type)->create();
        }
        // A type with no services at all must never appear, regardless of position.
        ServiceType::factory()->create(['position' => 0]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('services', 4));
    }

    public function test_inactive_service_types_are_excluded(): void
    {
        $provider = Provider::factory()->published()->create();
        $inactiveType = ServiceType::factory()->inactive()->create();
        Service::factory()->for($provider)->ofType($inactiveType)->create();

        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('services', 0));
    }
}
