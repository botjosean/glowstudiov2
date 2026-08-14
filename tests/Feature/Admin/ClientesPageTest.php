<?php

namespace Tests\Feature\Admin;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Provider;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_list_shows_only_the_providers_own_clients(): void
    {
        $provider = Provider::factory()->published()->create(['public_name' => 'Pati Barber']);
        $mine = Client::factory()->for($provider)->create(['name' => 'Ana Sosa', 'phone' => '3055550199']);
        Client::factory()->create(['name' => 'Ajena']);

        $this->actingAs($provider->user)->get('/admin/clientes')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Clientes')
            ->where('providerName', 'Pati Barber')
            ->has('clients', 1)
            ->where('clients.0.id', $mine->id)
            ->where('clients.0.name', 'Ana Sosa')
            ->where('clients.0.phone', '(305) 555-0199')
        );
    }

    public function test_the_list_counts_upcoming_appointments_by_phone(): void
    {
        $provider = Provider::factory()->published()->create();
        $client = Client::factory()->for($provider)->create(['phone' => '3055550199']);
        Appointment::factory()->for($provider)->create([
            'client_phone' => '3055550199',
            'status' => AppointmentStatus::Confirmed->value,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addMinutes(30),
        ]);
        // Past and cancelled must not count.
        Appointment::factory()->for($provider)->create([
            'client_phone' => '3055550199',
            'status' => AppointmentStatus::Cancelled->value,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addMinutes(30),
        ]);

        $this->actingAs($provider->user)->get('/admin/clientes')->assertInertia(fn (Assert $page) => $page
            ->where('clients.0.id', $client->id)
            ->where('clients.0.upcomingCount', 1)
        );
    }

    public function test_the_card_links_history_by_phone_and_splits_it(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-13 12:00:00', 'America/New_York'));

        $provider = Provider::factory()->published()->create();
        $client = Client::factory()->for($provider)->create(['phone' => '3055550199']);

        Appointment::factory()->for($provider)->create([
            'client_phone' => '3055550199',
            'service_name' => 'Balayage',
            'status' => AppointmentStatus::Confirmed->value,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addMinutes(180),
        ]);
        Appointment::factory()->for($provider)->create([
            'client_phone' => '3055550199',
            'status' => AppointmentStatus::Closed->value,
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDays(3)->addMinutes(45),
        ]);
        // Someone else's history stays out even on the same provider.
        Appointment::factory()->for($provider)->create([
            'client_phone' => '3055550111',
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addMinutes(30),
        ]);

        $this->actingAs($provider->user)->get("/admin/clientes/{$client->id}")->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Cliente')
            ->where('client.id', $client->id)
            ->has('upcoming', 1)
            ->where('upcoming.0.service', 'Balayage')
            ->has('past', 1)
            ->where('stats.upcoming', 1)
            ->where('stats.completed', 1)
            ->where('stats.cancelled', 0)
        );
    }

    public function test_a_phoneless_card_claims_no_history(): void
    {
        $provider = Provider::factory()->published()->create();
        $client = Client::factory()->phoneless()->for($provider)->create();
        Appointment::factory()->for($provider)->create([
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addMinutes(30),
        ]);

        $this->actingAs($provider->user)->get("/admin/clientes/{$client->id}")->assertInertia(fn (Assert $page) => $page
            ->has('upcoming', 0)
            ->has('past', 0)
        );
    }

    public function test_another_providers_card_is_forbidden(): void
    {
        $provider = Provider::factory()->published()->create();
        $foreign = Client::factory()->create();

        $this->actingAs($provider->user)->get("/admin/clientes/{$foreign->id}")->assertForbidden();
    }
}
