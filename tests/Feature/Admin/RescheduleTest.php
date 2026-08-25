<?php

namespace Tests\Feature\Admin;

use App\Actions\Booking\GenerateAvailableSlots;
use App\Actions\Booking\RescheduleAppointment;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Provider;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Mover una cita de hora en un solo paso.
 *
 * Antes esto era cancelar y volver a reservar, y en el hueco entre las dos la
 * hora vieja quedaba libre y la nueva sin apartar: otra clienta podía llevarse
 * cualquiera de las dos.
 */
class RescheduleTest extends TestCase
{
    use RefreshDatabase;

    private Provider $provider;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = Provider::factory()->published()->create([
            'lunch_start_minute' => 0,
            'lunch_end_minute' => 0,
            'buffer_minutes' => 0,
        ]);
        $this->service = Service::factory()->for($this->provider)->create(['duration_minutes' => 60]);
    }

    /**
     * Mañana, para no pelear con «ya pasó» ni con el borde de medianoche.
     */
    private function manana(int $hora): CarbonImmutable
    {
        return $this->provider->currentTime()->addDay()->startOfDay()->setTime($hora, 0);
    }

    private function citaA(int $hora): Appointment
    {
        $inicio = $this->manana($hora);

        return Appointment::create([
            'provider_id' => $this->provider->id,
            'service_id' => $this->service->id,
            'client_name' => 'Clienta '.$hora,
            'client_phone' => '5550100'.$hora,
            'service_name' => $this->service->name,
            'duration_minutes' => 60,
            'price' => $this->service->price,
            'starts_at' => $inicio->utc(),
            'ends_at' => $inicio->addHour()->utc(),
            'status' => AppointmentStatus::Pending->value,
        ]);
    }

    private function accion(): RescheduleAppointment
    {
        return new RescheduleAppointment(app(GenerateAvailableSlots::class));
    }

    public function test_una_cita_se_mueve_de_hora(): void
    {
        $cita = $this->citaA(10);

        $movida = $this->accion()->handle($cita, $this->manana(15));

        $this->assertSame(
            $this->manana(15)->utc()->toDateTimeString(),
            $movida->starts_at->utc()->toDateTimeString(),
        );
        $this->assertSame(
            $this->manana(16)->utc()->toDateTimeString(),
            $movida->ends_at->utc()->toDateTimeString(),
        );
    }

    /**
     * Sigue siendo la MISMA cita. Si esto creara una nueva y borrara la vieja,
     * la clienta perdería su sitio en la fila y el historial se rompería.
     */
    public function test_no_se_crea_una_cita_nueva(): void
    {
        $cita = $this->citaA(10);
        $antes = Appointment::count();

        $movida = $this->accion()->handle($cita, $this->manana(15));

        $this->assertSame($antes, Appointment::count());
        $this->assertSame($cita->id, $movida->id);
        $this->assertSame(AppointmentStatus::Pending, $movida->status);
    }

    /**
     * La prueba que da sentido a toda la tarea: la hora nueva se comprueba
     * dentro del candado, así que no se puede pisar una cita existente.
     */
    public function test_no_se_puede_mover_encima_de_otra_cita(): void
    {
        $mia = $this->citaA(10);
        $this->citaA(15);

        $this->expectException(ValidationException::class);

        try {
            $this->accion()->handle($mia, $this->manana(15));
        } finally {
            // Y la mía no se movió ni se rompió.
            $this->assertSame(
                $this->manana(10)->utc()->toDateTimeString(),
                $mia->fresh()->starts_at->utc()->toDateTimeString(),
            );
            $this->assertSame(2, Appointment::count());
        }
    }

    /**
     * El generador ve ocupada la hora VIEJA de la cita que se está moviendo.
     * Sin filtrarla, mover una cita una hora —o cualquier distancia dentro de
     * su propio colchón— saldría siempre «ocupado». Esta es la trampa.
     */
    public function test_una_cita_no_se_estorba_a_si_misma(): void
    {
        $cita = $this->citaA(10);

        // A las 11 en punto: pegado a donde termina ella misma.
        $movida = $this->accion()->handle($cita, $this->manana(11));

        $this->assertSame(
            $this->manana(11)->utc()->toDateTimeString(),
            $movida->starts_at->utc()->toDateTimeString(),
        );
    }

    public function test_una_cita_cancelada_no_se_mueve(): void
    {
        $cita = $this->citaA(10);
        $cita->update(['status' => AppointmentStatus::Cancelled->value, 'cancelled_at' => now()]);

        $this->expectException(ValidationException::class);

        $this->accion()->handle($cita->fresh(), $this->manana(15));
    }

    // =================================================================
    // La ruta
    // =================================================================

    public function test_la_profesional_puede_reagendar_desde_el_panel(): void
    {
        $cita = $this->citaA(10);
        $destino = $this->manana(15);

        $this->actingAs($this->provider->user)
            ->patch("/admin/citas/{$cita->id}/reagendar", [
                'date' => $destino->toDateString(),
                'time' => $destino->format('H:i'),
            ])
            ->assertRedirect();

        $this->assertSame(
            $destino->utc()->toDateTimeString(),
            $cita->fresh()->starts_at->utc()->toDateTimeString(),
        );
    }

    public function test_nadie_reagenda_la_cita_de_otra(): void
    {
        $cita = $this->citaA(10);
        $otra = Provider::factory()->published()->create();
        $destino = $this->manana(15);

        $this->actingAs($otra->user)
            ->patch("/admin/citas/{$cita->id}/reagendar", [
                'date' => $destino->toDateString(),
                'time' => $destino->format('H:i'),
            ])
            ->assertForbidden();

        $this->assertSame(
            $this->manana(10)->utc()->toDateTimeString(),
            $cita->fresh()->starts_at->utc()->toDateTimeString(),
        );
    }

    public function test_una_hora_con_mala_forma_se_rechaza(): void
    {
        $cita = $this->citaA(10);

        $this->actingAs($this->provider->user)
            ->patch("/admin/citas/{$cita->id}/reagendar", ['date' => 'mañana', 'time' => '25:99'])
            ->assertSessionHasErrors(['date', 'time']);
    }
}
