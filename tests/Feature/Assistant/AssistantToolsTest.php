<?php

namespace Tests\Feature\Assistant;

use App\Enums\AppointmentStatus;
use App\Events\AppointmentRequested;
use App\Models\Appointment;
use App\Models\Provider;
use App\Models\Service;
use App\Notifications\HumanHandoffRequested;
use App\Support\Assistant\AssistantTools;
use App\Support\Assistant\ToolContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AssistantToolsTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_WHATSAPP = '12056455856';

    private const CLIENT_STORED = '2056455856';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-08-10 09:00:00', 'America/New_York'));
    }

    public function test_it_lists_only_this_providers_active_services(): void
    {
        $provider = $this->provider();
        $mine = Service::factory()->for($provider)->create(['name' => 'Balayage', 'price' => 200, 'duration_minutes' => 180]);
        Service::factory()->for($provider)->create(['name' => 'Retirado', 'is_active' => false]);
        Service::factory()->for($this->provider())->create(['name' => 'De otra profesional']);

        $result = $this->tools()->run('listar_servicios', [], $this->context($provider));

        $this->assertCount(1, $result['servicios']);
        $this->assertSame($mine->id, $result['servicios'][0]['id']);
        $this->assertSame('Balayage', $result['servicios'][0]['nombre']);
        $this->assertSame(200, $result['servicios'][0]['precio_usd']);
        $this->assertSame('3h', $result['servicios'][0]['duracion']);
    }

    /**
     * Vanessa's real situation today: a connected number and no services yet.
     * An empty list is exactly when a model invents a price list, so the tool
     * has to say what to do instead.
     */
    public function test_a_provider_without_services_gets_an_explicit_warning_not_just_an_empty_list(): void
    {
        $result = $this->tools()->run('listar_servicios', [], $this->context($this->provider()));

        $this->assertSame([], $result['servicios']);
        $this->assertStringContainsString('No inventes', $result['aviso']);
    }

    public function test_it_returns_real_availability_from_the_existing_slot_engine(): void
    {
        $provider = $this->provider();
        $service = Service::factory()->for($provider)->create(['duration_minutes' => 60]);

        $result = $this->tools()->run('buscar_disponibilidad', [
            'servicio_id' => $service->id,
            'fecha' => '2026-08-11',
        ], $this->context($provider));

        $this->assertSame('2026-08-11', $result['fecha']);
        $this->assertContains('09:00', $result['horas_disponibles']);
        // Work day ends at 20:00, so a 60 minute service cannot start at 19:15.
        $this->assertNotContains('19:15', $result['horas_disponibles']);
    }

    public function test_it_refuses_a_service_belonging_to_another_provider(): void
    {
        $someoneElse = Service::factory()->for($this->provider())->create();

        $result = $this->tools()->run('buscar_disponibilidad', [
            'servicio_id' => $someoneElse->id,
            'fecha' => '2026-08-11',
        ], $this->context($this->provider()));

        $this->assertStringContainsString('no existe', $result['error']);
    }

    public function test_invalid_arguments_are_rejected_before_anything_runs(): void
    {
        $provider = $this->provider();
        $service = Service::factory()->for($provider)->create();

        $result = $this->tools()->run('buscar_disponibilidad', [
            'servicio_id' => $service->id,
            'fecha' => 'el viernes',
        ], $this->context($provider));

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Argumentos inválidos', $result['error']);
    }

    public function test_an_invented_tool_name_is_answered_rather_than_thrown(): void
    {
        $result = $this->tools()->run('borrar_todo', [], $this->context($this->provider()));

        $this->assertStringContainsString('no existe', $result['error']);
    }

    public function test_it_creates_a_pending_appointment_and_notifies_the_professional(): void
    {
        Event::fake([AppointmentRequested::class]);

        $provider = $this->provider();
        $service = Service::factory()->for($provider)->create(['name' => 'Clásico', 'duration_minutes' => 45, 'price' => 40]);

        $result = $this->tools()->run('crear_cita', [
            'servicio_id' => $service->id,
            'fecha' => '2026-08-11',
            'hora' => '10:00',
            'nombre_completo' => 'Josean Sosa',
        ], $this->context($provider));

        $appointment = Appointment::sole();
        $this->assertSame($appointment->id, $result['cita_id']);
        $this->assertSame(AppointmentStatus::Pending, $appointment->status);
        $this->assertSame('Josean Sosa', $appointment->client_name);
        // Stored the way the website stores it: ten digits, no country code.
        $this->assertSame(self::CLIENT_STORED, $appointment->client_phone);
        $this->assertStringContainsString('11/08/2026', $result['resumen']);
        $this->assertStringContainsString('10:00', $result['resumen']);

        // The website fires this from its controller, so a caller that bypasses
        // the controller must fire it too or nobody is told.
        Event::assertDispatched(AppointmentRequested::class);
    }

    /**
     * A retried job, or a model that calls the tool twice, must not produce two
     * appointments for one client at one time.
     */
    public function test_booking_the_same_slot_twice_returns_the_first_appointment(): void
    {
        $provider = $this->provider();
        $service = Service::factory()->for($provider)->create(['duration_minutes' => 45]);
        $arguments = [
            'servicio_id' => $service->id,
            'fecha' => '2026-08-11',
            'hora' => '10:00',
            'nombre_completo' => 'Josean Sosa',
        ];

        $first = $this->tools()->run('crear_cita', $arguments, $this->context($provider));
        $second = $this->tools()->run('crear_cita', $arguments, $this->context($provider));

        $this->assertSame(1, Appointment::count());
        $this->assertSame($first['cita_id'], $second['cita_id']);
        $this->assertTrue($second['ya_estaba_reservada']);
    }

    public function test_a_slot_taken_by_somebody_else_is_reported_not_forced(): void
    {
        $provider = $this->provider();
        $service = Service::factory()->for($provider)->create(['duration_minutes' => 45]);

        $this->appointmentFor($provider, $service, '2026-08-11 10:00', phone: '4045551111');

        $result = $this->tools()->run('crear_cita', [
            'servicio_id' => $service->id,
            'fecha' => '2026-08-11',
            'hora' => '10:00',
            'nombre_completo' => 'Josean Sosa',
        ], $this->context($provider));

        $this->assertStringContainsString('ya se ocupó', $result['error']);
        $this->assertSame(1, Appointment::count());
    }

    public function test_it_lists_only_the_writing_clients_own_appointments(): void
    {
        $provider = $this->provider();
        $service = Service::factory()->for($provider)->create(['duration_minutes' => 45]);

        $mine = $this->appointmentFor($provider, $service, '2026-08-11 10:00', phone: self::CLIENT_STORED);
        $this->appointmentFor($provider, $service, '2026-08-11 14:00', phone: '4045551111');

        $result = $this->tools()->run('listar_mis_citas', [], $this->context($provider));

        $this->assertCount(1, $result['citas']);
        $this->assertSame($mine->id, $result['citas'][0]['cita_id']);
    }

    /**
     * The authorisation test that matters most: anyone with WhatsApp could send
     * an id, so the tool must scope by the phone the webhook established and
     * never by the argument alone.
     */
    public function test_it_refuses_to_cancel_somebody_elses_appointment(): void
    {
        $provider = $this->provider();
        $service = Service::factory()->for($provider)->create(['duration_minutes' => 45]);
        $someoneElses = $this->appointmentFor($provider, $service, '2026-08-11 10:00', phone: '4045551111');

        $result = $this->tools()->run('cancelar_cita', ['cita_id' => $someoneElses->id], $this->context($provider));

        $this->assertArrayHasKey('error', $result);
        $this->assertSame(AppointmentStatus::Pending, $someoneElses->fresh()->status);
    }

    public function test_a_missing_appointment_and_somebody_elses_are_indistinguishable(): void
    {
        $provider = $this->provider();
        $service = Service::factory()->for($provider)->create(['duration_minutes' => 45]);
        $someoneElses = $this->appointmentFor($provider, $service, '2026-08-11 10:00', phone: '4045551111');

        $theirs = $this->tools()->run('cancelar_cita', ['cita_id' => $someoneElses->id], $this->context($provider));
        $missing = $this->tools()->run('cancelar_cita', ['cita_id' => 999999], $this->context($provider));

        // Identical wording on purpose: a different answer would let anyone
        // probe for which appointment ids exist.
        $this->assertSame($theirs['error'], $missing['error']);
    }

    public function test_it_cancels_the_clients_own_appointment(): void
    {
        $provider = $this->provider();
        $service = Service::factory()->for($provider)->create(['duration_minutes' => 45]);
        $mine = $this->appointmentFor($provider, $service, '2026-08-11 10:00', phone: self::CLIENT_STORED);

        $result = $this->tools()->run('cancelar_cita', ['cita_id' => $mine->id], $this->context($provider));

        $this->assertTrue($result['cancelada']);
        $this->assertSame(AppointmentStatus::Cancelled, $mine->fresh()->status);
        $this->assertNotNull($mine->fresh()->cancelled_at);
    }

    public function test_a_cancelled_appointment_cannot_be_cancelled_again(): void
    {
        $provider = $this->provider();
        $service = Service::factory()->for($provider)->create(['duration_minutes' => 45]);
        $mine = $this->appointmentFor($provider, $service, '2026-08-11 10:00', phone: self::CLIENT_STORED);
        $mine->update(['status' => AppointmentStatus::Cancelled->value]);

        $result = $this->tools()->run('cancelar_cita', ['cita_id' => $mine->id], $this->context($provider));

        $this->assertArrayHasKey('error', $result);
    }

    public function test_asking_for_a_human_notifies_the_professional(): void
    {
        Notification::fake();

        $provider = $this->provider();

        $result = $this->tools()->run(
            'solicitar_atencion_humana',
            ['motivo' => 'Quiere reclamar por un servicio anterior'],
            $this->context($provider),
        );

        $this->assertTrue($result['registrado']);
        Notification::assertSentTo($provider->user, HumanHandoffRequested::class);
    }

    /**
     * The requirement the whole booking design exists for: two clients wanting
     * the same slot, only one gets it.
     *
     * Sequential here because PHPUnit cannot really run two requests at once,
     * but the guarantee does not rest on ordering: CreateAppointment revalidates
     * inside a row lock on the provider, and the appointments table carries a
     * Postgres exclusion constraint that makes an overlap structurally
     * impossible whatever order the writes arrive in.
     *
     * This was also seen for real in production, when two models under test
     * raced for the same 14:00 slot and the second was correctly refused and
     * offered the remaining hours instead.
     */
    public function test_two_clients_cannot_take_the_same_slot(): void
    {
        $provider = $this->provider();
        $service = Service::factory()->for($provider)->create(['duration_minutes' => 45]);

        $arguments = [
            'servicio_id' => $service->id,
            'fecha' => '2026-08-11',
            'hora' => '11:00',
            'nombre_completo' => 'Quien Llegue Primero',
        ];

        $first = $this->tools()->run('crear_cita', $arguments, $this->context($provider));

        // A different WhatsApp identity asking for the very same slot.
        $second = $this->tools()->run(
            'crear_cita',
            [...$arguments, 'nombre_completo' => 'Quien Llegue Segundo'],
            ToolContext::for($provider, '14045551234', 'Otra Clienta'),
        );

        $this->assertArrayHasKey('cita_id', $first);
        $this->assertArrayHasKey('error', $second);
        $this->assertStringContainsString('ya se ocupó', $second['error']);
        $this->assertSame(1, Appointment::count());
    }

    /**
     * And the same slot on a *different* provider is a different resource, so
     * both must succeed — otherwise the two professionals would be sharing one
     * calendar.
     */
    public function test_the_same_hour_with_a_different_professional_is_still_free(): void
    {
        $pati = $this->provider();
        $vane = $this->provider();
        $hair = Service::factory()->for($pati)->create(['duration_minutes' => 45]);
        $nails = Service::factory()->for($vane)->create(['duration_minutes' => 45]);

        $first = $this->tools()->run('crear_cita', [
            'servicio_id' => $hair->id, 'fecha' => '2026-08-11',
            'hora' => '11:00', 'nombre_completo' => 'Una Clienta',
        ], $this->context($pati));

        $second = $this->tools()->run('crear_cita', [
            'servicio_id' => $nails->id, 'fecha' => '2026-08-11',
            'hora' => '11:00', 'nombre_completo' => 'Una Clienta',
        ], $this->context($vane));

        $this->assertArrayHasKey('cita_id', $first);
        $this->assertArrayHasKey('cita_id', $second);
        $this->assertSame(2, Appointment::count());
    }

    private function tools(): AssistantTools
    {
        return app(AssistantTools::class);
    }

    private function provider(): Provider
    {
        return Provider::factory()->published()->create([
            'timezone' => 'America/New_York',
            'work_start_minute' => 540,
            'work_end_minute' => 1200,
            'lunch_start_minute' => 0,
            'lunch_end_minute' => 0,
            'buffer_minutes' => 15,
        ]);
    }

    private function context(Provider $provider): ToolContext
    {
        return ToolContext::for($provider, self::CLIENT_WHATSAPP, 'Josean Sosa');
    }

    private function appointmentFor(Provider $provider, Service $service, string $localStart, string $phone): Appointment
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d H:i', $localStart, $provider->timezone);

        return Appointment::create([
            'provider_id' => $provider->id,
            'service_id' => $service->id,
            'client_name' => 'Alguien',
            'client_phone' => $phone,
            'service_name' => $service->name,
            'duration_minutes' => $service->duration_minutes,
            'price' => $service->price,
            'starts_at' => $start->utc(),
            'ends_at' => $start->addMinutes($service->duration_minutes)->utc(),
            'status' => AppointmentStatus::Pending->value,
        ]);
    }
}
