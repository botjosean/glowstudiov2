<?php

namespace Tests\Feature\Admin;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Provider;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CitasPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_appointments_expose_a_raw_iso_timestamp_and_integer_duration(): void
    {
        $provider = Provider::factory()->published()->create(['public_name' => 'Pati Barber']);
        $appointment = Appointment::factory()->for($provider)->create([
            'client_name' => 'John Smith',
            'client_phone' => '3055550199',
            'service_name' => 'Haircut',
            'duration_minutes' => 40,
            'price' => 30,
            'status' => AppointmentStatus::Confirmed->value,
            'starts_at' => CarbonImmutable::parse('2026-08-02 15:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-08-02 15:40:00', 'UTC'),
        ]);

        $this->actingAs($provider->user)->get('/admin/citas')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Citas')
            ->where('providerName', 'Pati Barber')
            ->where('appointments.0.id', $appointment->id)
            ->where('appointments.0.clientName', 'John Smith')
            ->where('appointments.0.clientPhone', '(305) 555-0199')
            ->where('appointments.0.clientPhoneDigits', '3055550199')
            ->where('appointments.0.durationMinutes', 40)
            ->where('appointments.0.price', 30)
            ->where('appointments.0.status', 'confirmed')
            ->where('appointments.0.startsAt', '2026-08-02T15:00:00+00:00')
            ->missing('appointments.0.dateLabel')
            ->missing('appointments.0.timeLabel')
        );
    }

    public function test_client_phone_digits_are_normalized_for_click_to_chat(): void
    {
        $provider = Provider::factory()->published()->create();
        Appointment::factory()->for($provider)->create([
            'client_phone' => '(305) 555-0199',
        ]);

        $this->actingAs($provider->user)->get('/admin/citas')->assertInertia(fn (Assert $page) => $page
            ->where('appointments.0.clientPhone', '(305) 555-0199')
            ->where('appointments.0.clientPhoneDigits', '3055550199')
        );
    }

    public function test_appointments_older_than_the_window_are_excluded(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 12:00:00', 'UTC'));

        $provider = Provider::factory()->published()->create();
        Appointment::factory()->for($provider)->create([
            'starts_at' => now()->subDays(61),
            'ends_at' => now()->subDays(61)->addMinutes(30),
        ]);
        $recent = Appointment::factory()->for($provider)->create([
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDays(10)->addMinutes(30),
        ]);

        $this->actingAs($provider->user)->get('/admin/citas')->assertInertia(fn (Assert $page) => $page
            ->has('appointments', 1)
            ->where('appointments.0.id', $recent->id)
        );
    }
}
