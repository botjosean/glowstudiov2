<?php

namespace Tests\Feature\Admin;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Provider;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The panel's manual booking flow (walk-ins and phone bookings). The key
 * invariant under test: a manual appointment goes through the same
 * CreateAppointment/GenerateAvailableSlots pair as every other channel, so
 * it blocks slots for the bot and the public page, and a taken slot rejects
 * it the same way.
 */
class ManualAppointmentTest extends TestCase
{
    use RefreshDatabase;

    private Provider $provider;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-08-10 09:00:00', 'America/New_York'));

        $this->provider = Provider::factory()->withSchedule(9 * 60, 20 * 60, 0, 0, 0)->published()->create();
        $this->service = Service::factory()->for($this->provider)->create(['duration_minutes' => 60, 'price' => 50]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'serviceId' => $this->service->id,
            'fecha' => '2026-08-11',
            'hora' => '10:00',
            'clientName' => 'Sandra Ríos',
            'clientPhone' => '(404) 555-0123',
        ], $overrides);
    }

    public function test_a_manual_appointment_is_born_confirmed(): void
    {
        $response = $this->actingAs($this->provider->user)->post('/admin/citas', $this->payload());

        $response->assertRedirect('/admin/citas');
        $response->assertSessionHas('success', 'admin.appointmentCreated');

        $appointment = Appointment::sole();
        $this->assertSame(AppointmentStatus::Confirmed, $appointment->status);
        $this->assertNotNull($appointment->confirmed_at);
        $this->assertSame('Sandra Ríos', $appointment->client_name);
        $this->assertSame('4045550123', $appointment->client_phone);
        $this->assertSame($this->service->name, $appointment->service_name);
        $this->assertSame('2026-08-11 14:00', $appointment->starts_at->format('Y-m-d H:i')); // 10:00 EDT = 14:00 UTC
    }

    public function test_the_phone_is_optional_and_stored_empty(): void
    {
        $this->actingAs($this->provider->user)
            ->post('/admin/citas', $this->payload(['clientPhone' => '']))
            ->assertSessionHasNoErrors();

        $this->assertSame('', Appointment::sole()->client_phone);
    }

    public function test_a_malformed_phone_is_rejected_not_silently_mangled(): void
    {
        $this->actingAs($this->provider->user)
            ->post('/admin/citas', $this->payload(['clientPhone' => '55512']))
            ->assertSessionHasErrors('clientPhone');

        $this->assertSame(0, Appointment::count());
    }

    public function test_a_taken_slot_is_rejected_like_any_other_channel(): void
    {
        Appointment::factory()
            ->for($this->provider)
            ->forService($this->service)
            ->at(CarbonImmutable::parse('2026-08-11 10:00', $this->provider->timezone))
            ->confirmed()
            ->create();

        $this->actingAs($this->provider->user)
            ->post('/admin/citas', $this->payload())
            ->assertSessionHasErrors('time');

        $this->assertSame(1, Appointment::count());
    }

    public function test_someone_elses_service_cannot_be_booked(): void
    {
        $foreign = Service::factory()->for(Provider::factory()->published()->create())->create();

        $this->actingAs($this->provider->user)
            ->post('/admin/citas', $this->payload(['serviceId' => $foreign->id]))
            ->assertSessionHasErrors('serviceId');

        $this->assertSame(0, Appointment::count());
    }

    public function test_the_slots_endpoint_speaks_both_formats_and_omits_taken_hours(): void
    {
        Appointment::factory()
            ->for($this->provider)
            ->forService($this->service)
            ->at(CarbonImmutable::parse('2026-08-11 10:00', $this->provider->timezone))
            ->confirmed()
            ->create();

        $response = $this->actingAs($this->provider->user)
            ->getJson("/admin/citas/horas?serviceId={$this->service->id}&fecha=2026-08-11");

        $response->assertOk();
        $horas = $response->json('horas');

        $values = array_column($horas, 'value');
        $this->assertContains('09:00', $values);
        $this->assertNotContains('10:00', $values); // taken
        $this->assertNotContains('10:30', $values); // inside the taken hour

        $nine = collect($horas)->firstWhere('value', '09:00');
        $this->assertSame('9:00 AM', $nine['label']);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->post('/admin/citas', $this->payload())->assertRedirect('/iniciar-sesion');
    }
}
