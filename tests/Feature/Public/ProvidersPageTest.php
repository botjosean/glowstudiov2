<?php

namespace Tests\Feature\Public;

use App\Models\Provider;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProvidersPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_unpublished_providers_are_excluded(): void
    {
        Provider::factory()->published()->create(['public_name' => 'Visible Barber']);
        Provider::factory()->unpublished()->create(['public_name' => 'Hidden Barber']);

        $this->get('/proveedores')->assertInertia(fn (Assert $page) => $page
            ->component('Public/Providers')
            ->has('providers', 1)
            ->where('providers.0.name', 'Visible Barber')
        );
    }

    public function test_services_count_only_includes_active_services(): void
    {
        $provider = Provider::factory()->published()->create();
        Service::factory()->count(2)->for($provider)->create(['is_active' => true]);
        Service::factory()->for($provider)->inactive()->create();

        $this->get('/proveedores')->assertInertia(fn (Assert $page) => $page
            ->where('providers.0.servicesCount', 2)
        );
    }

    public function test_available_now_providers_are_listed_first(): void
    {
        Provider::factory()->published()->create(['public_name' => 'Not Available', 'is_available_now' => false]);
        Provider::factory()->published()->availableNow()->create(['public_name' => 'Available Now']);

        $this->get('/proveedores')->assertInertia(fn (Assert $page) => $page
            ->where('providers.0.name', 'Available Now')
            ->where('providers.0.availableNow', true)
        );
    }
}
