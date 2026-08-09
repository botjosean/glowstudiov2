<?php

namespace Tests\Feature\Public;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Provider;
use App\Models\Service;
use App\Notifications\NewAppointmentRequest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CreateAppointmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The booking rate limiter keys on IP, which is constant across the
        // test client — flush so tests don't bleed into each other's quota.
        Cache::flush();
    }

    private function provider(): Provider
    {
        return Provider::factory()->published()->create();
    }

    public function test_happy_path_creates_a_pending_appointment_with_correct_snapshots(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        $provider = $this->provider();
        $service = Service::factory()->for($provider)->create([
            'name' => 'Signature Cut',
            'duration_minutes' => 45,
            'price' => 38,
        ]);
        $tomorrow = $provider->currentTime()->addDay();

        $response = $this->post("/reservar/{$provider->slug}/{$service->id}", [
            'date' => $tomorrow->toDateString(),
            'time' => '09:00',
            'fullName' => 'Jane Client',
            'phone' => '(305) 555-0123',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $appointment = Appointment::sole();
        $this->assertSame('Jane Client', $appointment->client_name);
        $this->assertSame('3055550123', $appointment->client_phone);
        $this->assertSame('Signature Cut', $appointment->service_name);
        $this->assertSame(45, $appointment->duration_minutes);
        $this->assertSame(38, $appointment->price);
        $this->assertSame(AppointmentStatus::Pending, $appointment->status);
        $this->assertTrue($appointment->ends_at->equalTo($appointment->starts_at->addMinutes(45)));
    }

    public function test_a_successful_booking_notifies_the_provider(): void
    {
        Notification::fake();
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        $provider = $this->provider();
        $service = Service::factory()->for($provider)->create();
        $tomorrow = $provider->currentTime()->addDay();

        $this->post("/reservar/{$provider->slug}/{$service->id}", [
            'date' => $tomorrow->toDateString(),
            'time' => '09:00',
            'fullName' => 'Jane Client',
            'phone' => '3055550123',
        ]);

        $appointment = Appointment::sole();

        Notification::assertSentTo(
            $provider->user,
            NewAppointmentRequest::class,
            fn (NewAppointmentRequest $notification) => $notification->toMail($provider->user)
                ->subject === "Nueva solicitud de cita — {$appointment->client_name}"
        );
    }

    public function test_a_rejected_booking_does_not_notify_the_provider(): void
    {
        Notification::fake();
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        $provider = $this->provider();
        $service = Service::factory()->for($provider)->create(['duration_minutes' => 60]);
        $tomorrow = $provider->currentTime()->addDay();

        Appointment::factory()->for($provider)->forService($service)
            ->at($tomorrow->setTime(9, 0))->confirmed()->create();

        $this->post("/reservar/{$provider->slug}/{$service->id}", [
            'date' => $tomorrow->toDateString(),
            'time' => '09:00',
            'fullName' => 'Late Comer',
            'phone' => '3055550199',
        ]);

        Notification::assertNothingSent();
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function phoneFormatProvider(): iterable
    {
        yield 'formatted' => ['(305) 555-0123'];
        yield 'dashed' => ['305-555-0123'];
        yield 'e164' => ['+13055550123'];
    }

    #[DataProvider('phoneFormatProvider')]
    public function test_phone_is_accepted_in_multiple_formats_and_normalized(string $phone): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        $provider = $this->provider();
        $service = Service::factory()->for($provider)->create(['duration_minutes' => 40]);
        $tomorrow = $provider->currentTime()->addDay();

        $response = $this->post("/reservar/{$provider->slug}/{$service->id}", [
            'date' => $tomorrow->toDateString(),
            'time' => '09:00',
            'fullName' => 'Jane Client',
            'phone' => $phone,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('3055550123', Appointment::sole()->client_phone);
    }

    public function test_double_booking_the_same_slot_is_rejected(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        $provider = $this->provider();
        $service = Service::factory()->for($provider)->create(['duration_minutes' => 60]);
        $tomorrow = $provider->currentTime()->addDay();

        Appointment::factory()->for($provider)->forService($service)
            ->at($tomorrow->setTime(9, 0))->confirmed()->create();

        $response = $this->post("/reservar/{$provider->slug}/{$service->id}", [
            'date' => $tomorrow->toDateString(),
            'time' => '09:00',
            'fullName' => 'Late Comer',
            'phone' => '3055550199',
        ]);

        $response->assertSessionHasErrors('time');
        $this->assertSame(1, Appointment::count());
    }

    public function test_a_cancelled_appointment_does_not_block_the_same_slot(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        $provider = $this->provider();
        $service = Service::factory()->for($provider)->create(['duration_minutes' => 60]);
        $tomorrow = $provider->currentTime()->addDay();

        Appointment::factory()->for($provider)->forService($service)
            ->at($tomorrow->setTime(9, 0))->cancelled()->create();

        $response = $this->post("/reservar/{$provider->slug}/{$service->id}", [
            'date' => $tomorrow->toDateString(),
            'time' => '09:00',
            'fullName' => 'New Client',
            'phone' => '3055550199',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(2, Appointment::count());
    }

    public function test_buffer_collision_is_rejected_and_the_boundary_slot_is_accepted(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        $provider = $this->provider(); // buffer_minutes default 15
        $existingService = Service::factory()->for($provider)->create(['duration_minutes' => 60]);
        $newService = Service::factory()->for($provider)->create(['duration_minutes' => 40]);
        $tomorrow = $provider->currentTime()->addDay();

        Appointment::factory()->for($provider)->forService($existingService)
            ->at($tomorrow->setTime(9, 0))->confirmed()->create(); // 9:00-10:00

        $rejected = $this->post("/reservar/{$provider->slug}/{$newService->id}", [
            'date' => $tomorrow->toDateString(),
            'time' => '10:00',
            'fullName' => 'Too Soon',
            'phone' => '3055550199',
        ]);
        $rejected->assertSessionHasErrors('time');

        $accepted = $this->post("/reservar/{$provider->slug}/{$newService->id}", [
            'date' => $tomorrow->toDateString(),
            'time' => '10:15',
            'fullName' => 'Just Right',
            'phone' => '3055550198',
        ]);
        $accepted->assertSessionHasNoErrors();
        $this->assertSame(2, Appointment::count());
    }

    public function test_a_past_date_is_rejected(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-10 00:00:00', 'America/New_York'));

        $provider = $this->provider();
        $service = Service::factory()->for($provider)->create();
        $yesterday = $provider->currentTime()->subDay();

        $response = $this->post("/reservar/{$provider->slug}/{$service->id}", [
            'date' => $yesterday->toDateString(),
            'time' => '09:00',
            'fullName' => 'Time Traveler',
            'phone' => '3055550199',
        ]);

        $response->assertSessionHasErrors('date');
        $this->assertSame(0, Appointment::count());
    }

    public function test_a_date_beyond_the_horizon_is_rejected(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        $provider = $this->provider();
        $service = Service::factory()->for($provider)->create();
        $tooFar = $provider->currentTime()->addDays(200);

        $response = $this->post("/reservar/{$provider->slug}/{$service->id}", [
            'date' => $tooFar->toDateString(),
            'time' => '09:00',
            'fullName' => 'Way Ahead',
            'phone' => '3055550199',
        ]);

        $response->assertSessionHasErrors('date');
        $this->assertSame(0, Appointment::count());
    }

    public function test_a_time_not_on_the_slot_grid_is_rejected(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        $provider = $this->provider();
        $service = Service::factory()->for($provider)->create();
        $tomorrow = $provider->currentTime()->addDay();

        $response = $this->post("/reservar/{$provider->slug}/{$service->id}", [
            'date' => $tomorrow->toDateString(),
            'time' => '09:07',
            'fullName' => 'Off Grid',
            'phone' => '3055550199',
        ]);

        $response->assertSessionHasErrors('time');
    }

    public function test_missing_full_name_is_rejected(): void
    {
        $provider = $this->provider();
        $service = Service::factory()->for($provider)->create();

        $response = $this->post("/reservar/{$provider->slug}/{$service->id}", [
            'date' => now()->addDay()->toDateString(),
            'time' => '09:00',
            'fullName' => '',
            'phone' => '3055550199',
        ]);

        $response->assertSessionHasErrors('fullName');
    }

    public function test_a_service_from_another_provider_404s(): void
    {
        $provider = $this->provider();
        $otherProvider = $this->provider();
        $otherService = Service::factory()->for($otherProvider)->create();

        $response = $this->post("/reservar/{$provider->slug}/{$otherService->id}", [
            'date' => now()->addDay()->toDateString(),
            'time' => '09:00',
            'fullName' => 'Sneaky',
            'phone' => '3055550199',
        ]);

        $response->assertNotFound();
        $this->assertSame(0, Appointment::count());
    }

    public function test_an_inactive_service_404s(): void
    {
        $provider = $this->provider();
        $service = Service::factory()->for($provider)->inactive()->create();

        $response = $this->post("/reservar/{$provider->slug}/{$service->id}", [
            'date' => now()->addDay()->toDateString(),
            'time' => '09:00',
            'fullName' => 'Nope',
            'phone' => '3055550199',
        ]);

        $response->assertNotFound();
    }

    public function test_the_booking_endpoint_is_rate_limited(): void
    {
        $provider = $this->provider();
        $service = Service::factory()->for($provider)->create();
        $payload = [
            'date' => now()->addDay()->toDateString(),
            'time' => '09:00',
            'fullName' => 'Repeat Offender',
            'phone' => '3055550199',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->post("/reservar/{$provider->slug}/{$service->id}", $payload);
        }

        $response = $this->post("/reservar/{$provider->slug}/{$service->id}", $payload);

        $response->assertStatus(429);
    }

    public function test_a_home_visit_books_pending_with_the_address(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        $provider = Provider::factory()->published()->create(['home_service' => true, 'is_mobile' => false]);
        $service = Service::factory()->for($provider)->create(['home_available' => true]);

        $response = $this->post("/reservar/{$provider->slug}/{$service->id}", [
            'date' => $provider->currentTime()->addDay()->toDateString(),
            'time' => '09:00',
            'fullName' => 'Jane Client',
            'phone' => '(305) 555-0123',
            'atHome' => true,
            'address' => '742 Evergreen Terrace, Apt 2',
        ]);

        $response->assertSessionHasNoErrors();
        $appointment = Appointment::sole();
        $this->assertTrue($appointment->at_home);
        $this->assertSame('742 Evergreen Terrace, Apt 2', $appointment->client_address);
        $this->assertSame(AppointmentStatus::Pending, $appointment->status);
    }

    public function test_a_home_visit_requires_an_address(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        $provider = Provider::factory()->published()->create(['home_service' => true, 'is_mobile' => false]);
        $service = Service::factory()->for($provider)->create(['home_available' => true]);

        $this->post("/reservar/{$provider->slug}/{$service->id}", [
            'date' => $provider->currentTime()->addDay()->toDateString(),
            'time' => '09:00',
            'fullName' => 'Jane Client',
            'phone' => '(305) 555-0123',
            'atHome' => true,
        ])->assertSessionHasErrors('address');

        $this->assertSame(0, Appointment::count());
    }

    /**
     * The per-service safety switch is the entire point: a crafted POST must
     * not force a home visit onto a service the professional never marked,
     * nor onto a provider who doesn't travel at all.
     */
    public function test_a_home_visit_is_rejected_when_service_or_provider_never_offered_it(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 00:00:00', 'America/New_York'));

        $homeProvider = Provider::factory()->published()->create(['home_service' => true, 'is_mobile' => false]);
        $unmarkedService = Service::factory()->for($homeProvider)->create(['home_available' => false]);

        $studioProvider = Provider::factory()->published()->create(['home_service' => false, 'is_mobile' => false]);
        $markedService = Service::factory()->for($studioProvider)->create(['home_available' => true]);

        foreach ([[$homeProvider, $unmarkedService], [$studioProvider, $markedService]] as [$provider, $service]) {
            $this->post("/reservar/{$provider->slug}/{$service->id}", [
                'date' => $provider->currentTime()->addDay()->toDateString(),
                'time' => '09:00',
                'fullName' => 'Jane Client',
                'phone' => '(305) 555-0123',
                'atHome' => true,
                'address' => 'Somewhere 123',
            ])->assertSessionHasErrors('atHome');
        }

        $this->assertSame(0, Appointment::count());
    }
}
