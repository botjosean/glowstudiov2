<?php

namespace Tests\Feature\Admin;

use App\Models\Provider;
use App\Models\ProviderBusinessHour;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Nadie es reservable hasta que guarda su semana.
 *
 * Esto arregla lo que le estaba pasando a Patricia y a Vanessa: las dos
 * aparecían disponibles los siete días, domingos incluidos, sin haberlo
 * pedido nunca. Una clienta podía reservar un domingo a las nueve de la
 * mañana y a ellas no les avisaba nadie.
 */
class ScheduleGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_recien_registrada_no_es_reservable_ningun_dia(): void
    {
        $provider = Provider::factory()->published()->scheduleUnsaved()->create();
        $service = Service::factory()->for($provider)->create();

        // Provider::booted() le sembró los siete días ABIERTOS. Ese es
        // justamente el caso: las filas existen y están abiertas, pero las
        // puso la siembra, no ella.
        $this->assertCount(7, $provider->businessHours()->get());
        $this->assertTrue($provider->businessHours()->where('is_open', true)->count() === 7);

        $this->get("/reservar/{$provider->slug}/{$service->id}")
            ->assertInertia(fn (Assert $page) => $page->where('slots', []));
    }

    public function test_el_domingo_deja_de_estar_abierto_solo(): void
    {
        $provider = Provider::factory()->published()->scheduleUnsaved()->create();
        $service = Service::factory()->for($provider)->create();

        $hoy = $provider->currentTime()->startOfDay();

        // Los siete próximos días, todos vacíos. El bug se veía justamente
        // así: el mismo número de huecos cada día de la semana.
        for ($dia = 0; $dia < 7; $dia++) {
            $fecha = $hoy->addDays($dia)->toDateString();

            $this->get("/reservar/{$provider->slug}/{$service->id}?date={$fecha}")
                ->assertInertia(fn (Assert $page) => $page->where('slots', []));
        }
    }

    public function test_al_guardar_el_horario_empieza_a_recibir_citas(): void
    {
        $provider = Provider::factory()->published()->scheduleUnsaved()->create();
        $service = Service::factory()->for($provider)->create();

        $dias = [];
        for ($d = 0; $d < 7; $d++) {
            $dias[] = ['weekday' => $d, 'isOpen' => $d !== 0, 'workStart' => 9 * 60, 'workEnd' => 17 * 60];
        }

        $this->actingAs($provider->user)->put('/admin/horario', [
            'days' => $dias,
            'lunchStart' => 13 * 60,
            'lunchEnd' => 14 * 60,
            'bufferMinutes' => 15,
        ])->assertRedirect();

        $this->assertNotNull($provider->fresh()->schedule_saved_at);

        // Y ahora sí: el domingo cerrado, el resto abierto.
        $hoy = $provider->currentTime()->startOfDay();
        $algunDiaAbierto = false;

        for ($dia = 0; $dia < 7; $dia++) {
            $fecha = $hoy->addDays($dia);
            $respuesta = $this->get("/reservar/{$provider->slug}/{$service->id}?date={$fecha->toDateString()}");
            $huecos = $respuesta->viewData('page')['props']['slots'];

            if ($fecha->dayOfWeek === 0) {
                $this->assertSame([], $huecos, 'El domingo quedó cerrado y sigue ofreciendo horas.');
            } elseif ($huecos !== []) {
                $algunDiaAbierto = true;
            }
        }

        $this->assertTrue($algunDiaAbierto, 'Después de guardar no abrió ningún día.');
    }

    /**
     * Quien ya configuró su semana no puede quedarse sin citas por esto.
     */
    public function test_la_que_ya_lo_tenia_configurado_sigue_igual(): void
    {
        $provider = Provider::factory()->published()->create();
        $service = Service::factory()->for($provider)->create();

        $this->assertNotNull($provider->schedule_saved_at);

        $hoy = $provider->currentTime()->startOfDay();
        $encontroAlguno = false;

        for ($dia = 0; $dia < 8 && ! $encontroAlguno; $dia++) {
            $fecha = $hoy->addDays($dia)->toDateString();
            $huecos = $this->get("/reservar/{$provider->slug}/{$service->id}?date={$fecha}")
                ->viewData('page')['props']['slots'];
            $encontroAlguno = $huecos !== [];
        }

        $this->assertTrue($encontroAlguno, 'Una profesional ya configurada dejó de recibir citas.');
    }

    /**
     * Si no recibe citas tiene que enterarse, y no por un banner que quizás
     * cerró hace un mes.
     */
    public function test_le_sale_el_aviso_en_el_panel(): void
    {
        $provider = Provider::factory()->published()->scheduleUnsaved()->create();

        $this->actingAs($provider->user)->get('/admin/citas')
            ->assertInertia(fn (Assert $page) => $page->where('scheduleUnsaved', true));
    }

    public function test_el_aviso_desaparece_cuando_ya_guardo(): void
    {
        $provider = Provider::factory()->published()->create();

        $this->actingAs($provider->user)->get('/admin/citas')
            ->assertInertia(fn (Assert $page) => $page->where('scheduleUnsaved', false));
    }

    /**
     * La migración que creó la columna se la dedujo a las que ya tenían un
     * horario distinto de la siembra. Esta prueba fija ese criterio: un
     * horario editado cuenta como configurado.
     */
    public function test_un_horario_distinto_de_la_siembra_cuenta_como_configurado(): void
    {
        $provider = Provider::factory()->scheduleUnsaved()->create();

        ProviderBusinessHour::updateOrCreate(
            ['provider_id' => $provider->id, 'weekday' => 0],
            ['is_open' => false, 'work_start_minute' => 9 * 60, 'work_end_minute' => 17 * 60],
        );

        $horas = $provider->businessHours()->get();

        $esLaSiembra = $horas->count() === 7
            && $horas->every(fn ($h) => $h->is_open
                && $h->work_start_minute === $provider->work_start_minute
                && $h->work_end_minute === $provider->work_end_minute);

        $this->assertFalse($esLaSiembra, 'Un domingo cerrado a mano tiene que leerse como configurado.');
    }
}
