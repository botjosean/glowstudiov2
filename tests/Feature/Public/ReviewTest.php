<?php

namespace Tests\Feature\Public;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Provider;
use App\Models\Review;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Las reseñas con estrellas.
 *
 * Lo que hace que estas estrellas valgan algo no es la pantalla, son dos
 * reglas: una reseña cuelga de una CITA, y una cita sólo puede tener una. Sin
 * eso, cualquiera infla su promedio contestando diez veces, y unas estrellas
 * que se pueden inflar no le sirven de nada a quien las lee.
 */
class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private function citaCerrada(Provider $provider): Appointment
    {
        $service = Service::factory()->for($provider)->create();
        $inicio = CarbonImmutable::now()->subDay();

        return Appointment::create([
            'provider_id' => $provider->id,
            'service_id' => $service->id,
            'client_name' => 'Maria Fernandez',
            'client_phone' => '5550100999',
            'service_name' => $service->name,
            'duration_minutes' => 60,
            'price' => $service->price,
            'starts_at' => $inicio->utc(),
            'ends_at' => $inicio->addHour()->utc(),
            'status' => AppointmentStatus::Closed->value,
        ]);
    }

    private function invitacion(Provider $provider): Review
    {
        $cita = $this->citaCerrada($provider);

        return Review::factory()->create([
            'provider_id' => $provider->id,
            'appointment_id' => $cita->id,
            'client_name' => $cita->client_name,
        ]);
    }

    // =================================================================
    // Dejar la reseña
    // =================================================================

    public function test_una_clienta_deja_sus_estrellas_con_el_enlace(): void
    {
        $provider = Provider::factory()->published()->create();
        $invitacion = $this->invitacion($provider);

        $this->get("/resena/{$invitacion->token}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Review')
                ->where('alreadyAnswered', false)
            );

        $this->post("/resena/{$invitacion->token}", [
            'rating' => 5,
            'comment' => 'Quedé feliz con mis uñas.',
        ])->assertRedirect();

        $invitacion->refresh();

        $this->assertSame(5, $invitacion->rating);
        $this->assertSame('Quedé feliz con mis uñas.', $invitacion->comment);
        $this->assertNotNull($invitacion->answered_at);
    }

    public function test_sin_el_token_no_se_llega(): void
    {
        $this->get('/resena/estonoesuntoken')->assertNotFound();
        $this->post('/resena/estonoesuntoken', ['rating' => 5])->assertNotFound();
    }

    /**
     * La regla que sostiene todo lo demás: una cita, una reseña. Sin esto,
     * quien conserve el enlace se sube la nota cuando quiera.
     */
    public function test_no_se_puede_contestar_dos_veces(): void
    {
        $provider = Provider::factory()->published()->create();
        $invitacion = $this->invitacion($provider);

        $this->post("/resena/{$invitacion->token}", ['rating' => 5]);
        $this->post("/resena/{$invitacion->token}", ['rating' => 1, 'comment' => 'me arrepentí']);

        $invitacion->refresh();

        $this->assertSame(5, $invitacion->rating, 'La segunda respuesta pisó la primera.');
        $this->assertNull($invitacion->comment);
    }

    public function test_una_nota_fuera_de_rango_se_rechaza(): void
    {
        $provider = Provider::factory()->published()->create();
        $invitacion = $this->invitacion($provider);

        $this->post("/resena/{$invitacion->token}", ['rating' => 9])->assertSessionHasErrors('rating');
        $this->post("/resena/{$invitacion->token}", ['rating' => 0])->assertSessionHasErrors('rating');

        $this->assertNull($invitacion->fresh()->answered_at);
    }

    // =================================================================
    // Las estrellas en la ficha
    // =================================================================

    public function test_la_ficha_enseña_el_promedio_y_las_ultimas(): void
    {
        $provider = Provider::factory()->published()->create();
        Service::factory()->for($provider)->create();

        foreach ([5, 4] as $nota) {
            $cita = $this->citaCerrada($provider);
            Review::factory()->answered($nota, 'Muy bien')->create([
                'provider_id' => $provider->id,
                'appointment_id' => $cita->id,
                'client_name' => 'Ana Perez',
            ]);
        }

        $this->get("/{$provider->slug}")->assertInertia(fn (Assert $page) => $page
            ->where('provider.rating.average', 4.5)
            ->where('provider.rating.count', 2)
            ->has('provider.rating.recent', 2)
            // Sólo el nombre de pila: nadie pidió salir con apellido en una
            // página pública.
            ->where('provider.rating.recent.0.name', 'Ana')
        );
    }

    /**
     * Una invitación sin contestar existe en la tabla —es la que guarda el
     * token— pero no es una reseña y no puede mover el promedio.
     */
    public function test_una_invitacion_sin_contestar_no_cuenta(): void
    {
        $provider = Provider::factory()->published()->create();
        Service::factory()->for($provider)->create();

        $this->invitacion($provider);

        $this->get("/{$provider->slug}")->assertInertia(fn (Assert $page) => $page
            ->where('provider.rating.count', 0)
            ->where('provider.rating.average', null)
        );
    }

    /**
     * Una profesional nueva no empieza en cero estrellas: empieza sin datos.
     * Un cero grande la hunde sin haber hecho nada.
     */
    public function test_sin_resenas_no_se_enseña_un_cero(): void
    {
        $provider = Provider::factory()->published()->create();
        Service::factory()->for($provider)->create();

        $this->get("/{$provider->slug}")->assertInertia(fn (Assert $page) => $page
            ->where('provider.rating.average', null)
            ->where('provider.rating.count', 0)
        );
    }

    // =================================================================
    // El pedido automático
    // =================================================================

    public function test_al_cerrar_una_cita_se_crea_la_invitacion(): void
    {
        $provider = Provider::factory()->published()->create();
        $service = Service::factory()->for($provider)->create();
        $inicio = CarbonImmutable::now()->subHours(3);

        $cita = Appointment::create([
            'provider_id' => $provider->id,
            'service_id' => $service->id,
            'client_name' => 'Luisa Gomez',
            'client_phone' => '5550100888',
            'service_name' => $service->name,
            'duration_minutes' => 60,
            'price' => $service->price,
            'starts_at' => $inicio->utc(),
            'ends_at' => $inicio->addHour()->utc(),
            'status' => AppointmentStatus::Confirmed->value,
        ]);

        $this->artisan('appointments:close-finished')->assertSuccessful();

        $this->assertSame(AppointmentStatus::Closed, $cita->fresh()->status);

        $invitacion = Review::where('appointment_id', $cita->id)->first();

        $this->assertNotNull($invitacion, 'Al cerrar la cita no se creó la invitación a reseñar.');
        $this->assertNull($invitacion->answered_at);
        $this->assertSame('Luisa Gomez', $invitacion->client_name);
        $this->assertNotEmpty($invitacion->token);
    }

    /**
     * El comando corre cada hora. Si volviera a pasar por la misma cita, la
     * clienta recibiría el mismo enlace otra vez.
     */
    public function test_correr_el_comando_dos_veces_no_duplica_la_invitacion(): void
    {
        $provider = Provider::factory()->published()->create();
        $cita = $this->citaCerrada($provider);
        $cita->update(['status' => AppointmentStatus::Confirmed->value]);

        $this->artisan('appointments:close-finished');
        $this->artisan('appointments:close-finished');

        $this->assertSame(1, Review::where('appointment_id', $cita->id)->count());
    }

    /**
     * Una cita pendiente que nadie confirmó no se cierra sola, y por lo tanto
     * tampoco se le pide reseña: no hay prueba de que la clienta fuera.
     */
    public function test_una_cita_pendiente_no_pide_resena(): void
    {
        $provider = Provider::factory()->published()->create();
        $cita = $this->citaCerrada($provider);
        $cita->update(['status' => AppointmentStatus::Pending->value]);

        $this->artisan('appointments:close-finished');

        $this->assertSame(0, Review::where('appointment_id', $cita->id)->count());
    }
}
