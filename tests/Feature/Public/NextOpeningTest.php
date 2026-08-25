<?php

namespace Tests\Feature\Public;

use App\Models\Provider;
use App\Models\ProviderBusinessHour;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * «Hoy 3:30 PM» en la tarjeta de cada servicio.
 *
 * Lo que más importa aquí no es que salga la hora, sino que salga LA MISMA
 * que al reservar. Si la vitrina promete una hora que el calendario no tiene,
 * la clienta toca y se encuentra otra cosa — y eso es peor que no prometer
 * nada. Por eso FindNextOpening no cuenta huecos: se los pide al generador.
 */
class NextOpeningTest extends TestCase
{
    use RefreshDatabase;

    /**
     * updateOrCreate y no create en todo el archivo: la factoría de Provider
     * ya deja horario sembrado para los siete días, y (provider, weekday) es
     * único en la tabla.
     */
    private function horario(Provider $provider, callable $abierto): void
    {
        for ($dia = 0; $dia < 7; $dia++) {
            ProviderBusinessHour::updateOrCreate(
                ['provider_id' => $provider->id, 'weekday' => $dia],
                [
                    'is_open' => $abierto($dia),
                    'work_start_minute' => 9 * 60,
                    'work_end_minute' => 17 * 60,
                ],
            );
        }
    }

    public function test_la_ficha_dice_cuando_hay_el_proximo_hueco(): void
    {
        $provider = Provider::factory()->published()->create();
        Service::factory()->for($provider)->create();

        $this->get("/{$provider->slug}")->assertInertia(fn (Assert $page) => $page
            ->has('provider.services.0.nextOpening', fn (Assert $hueco) => $hueco
                ->where('daysAway', fn ($d) => is_int($d) && $d >= 0)
                ->where('h', fn ($h) => is_int($h) && $h >= 0 && $h < 24)
                ->where('m', fn ($m) => is_int($m) && $m >= 0 && $m < 60)
                ->has('date')
            )
        );
    }

    /**
     * La hora que se enseña tiene que existir de verdad en el calendario de
     * ese día. Esta es la prueba que evita prometer humo.
     */
    public function test_la_hora_prometida_es_una_de_las_reservables(): void
    {
        $provider = Provider::factory()->published()->create();
        $service = Service::factory()->for($provider)->create();

        $ficha = $this->get("/{$provider->slug}");
        $hueco = $ficha->viewData('page')['props']['provider']['services'][0]['nextOpening'];

        $this->assertNotNull($hueco, 'Sin hueco no hay nada que comprobar.');

        $calendario = $this->get("/reservar/{$provider->slug}/{$service->id}?date={$hueco['date']}");
        $reservables = $calendario->viewData('page')['props']['slots'];

        $this->assertContains(
            ['h' => $hueco['h'], 'm' => $hueco['m']],
            $reservables,
            'La vitrina prometió una hora que el calendario de ese día no ofrece.',
        );
    }

    /**
     * Un día cerrado no cuenta: con hoy cerrado, el primer hueco cae mañana
     * o más adelante.
     */
    public function test_un_dia_cerrado_no_se_ofrece(): void
    {
        $provider = Provider::factory()->published()->create();
        Service::factory()->for($provider)->create();

        $hoy = $provider->currentTime()->dayOfWeek;
        $this->horario($provider, fn (int $dia) => $dia !== $hoy);

        $this->get("/{$provider->slug}")->assertInertia(fn (Assert $page) => $page
            ->where('provider.services.0.nextOpening.daysAway', fn ($d) => $d >= 1)
        );
    }

    /**
     * Toda la semana cerrada: no hay nada que ofrecer y la ficha manda `null`.
     * Profile.vue no pinta nada en ese caso a propósito — «sin
     * disponibilidad» ahuyenta, y una agenda llena es buena noticia.
     */
    public function test_sin_horario_abierto_no_se_promete_nada(): void
    {
        $provider = Provider::factory()->published()->create();
        Service::factory()->for($provider)->create();

        $this->horario($provider, fn (int $dia) => false);

        $this->get("/{$provider->slug}")->assertInertia(fn (Assert $page) => $page
            ->where('provider.services.0.nextOpening', null)
        );
    }

    /**
     * Con cinco servicios, la ficha no puede disparar una consulta por
     * servicio y por día: serían setenta para pintar una pantalla. Las citas
     * de toda la ventana se traen de una vez.
     */
    public function test_cinco_servicios_no_multiplican_las_consultas(): void
    {
        $provider = Provider::factory()->published()->create();
        Service::factory()->count(5)->for($provider)->create();

        DB::enableQueryLog();
        $this->get("/{$provider->slug}")->assertOk();
        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Holgado a propósito: lo que se vigila es que no crezca con el
        // número de servicios ni con los catorce días, no el número exacto.
        $this->assertLessThan(
            25,
            $consultas,
            "La ficha hizo {$consultas} consultas; con cinco servicios eso huele a una por servicio y día.",
        );
    }
}
