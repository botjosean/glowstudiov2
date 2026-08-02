<?php

namespace Tests\Feature\Admin;

use App\Enums\ServiceCategory;
use App\Models\Provider;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ServiciosPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_services_expose_an_integer_duration_and_category(): void
    {
        $provider = Provider::factory()->published()->create();
        Service::factory()->for($provider)->create([
            'name' => 'VIP Haircut + Beard',
            'duration_minutes' => 60,
            'price' => 40,
            'category' => ServiceCategory::Fade->value,
            'position' => 1,
        ]);

        $this->actingAs($provider->user)->get('/admin/servicios')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Servicios')
            ->where('services.0.name', 'VIP Haircut + Beard')
            ->where('services.0.durationMinutes', 60)
            ->where('services.0.price', 40)
            ->where('services.0.category', 'fade')
        );
    }

    public function test_inactive_services_are_still_shown_on_the_management_page(): void
    {
        $provider = Provider::factory()->published()->create();
        Service::factory()->for($provider)->inactive()->create(['name' => 'Retired Service']);

        $this->actingAs($provider->user)->get('/admin/servicios')->assertInertia(fn (Assert $page) => $page
            ->has('services', 1)
            ->where('services.0.name', 'Retired Service')
        );
    }

    public function test_services_are_ordered_by_position(): void
    {
        $provider = Provider::factory()->published()->create();
        Service::factory()->for($provider)->create(['name' => 'Second', 'position' => 2]);
        Service::factory()->for($provider)->create(['name' => 'First', 'position' => 1]);

        $this->actingAs($provider->user)->get('/admin/servicios')->assertInertia(fn (Assert $page) => $page
            ->where('services.0.name', 'First')
            ->where('services.1.name', 'Second')
        );
    }
}
