<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un solo dominio, sin romper los enlaces que ya están en el WhatsApp de una
 * clienta.
 */
class CanonicalHostTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_old_subdomain_redirects_to_the_canonical_one(): void
    {
        config(['app.url' => 'https://glowstudios.vip']);

        $this->get('https://citas.glowstudios.vip/pati')
            ->assertRedirect('https://glowstudios.vip/pati')
            ->assertStatus(301);
    }

    public function test_the_query_string_survives_the_redirect(): void
    {
        config(['app.url' => 'https://glowstudios.vip']);

        $this->get('https://citas.glowstudios.vip/reservar/pati/3?date=2026-08-20')
            ->assertRedirect('https://glowstudios.vip/reservar/pati/3?date=2026-08-20');
    }

    /**
     * El interruptor es APP_URL: mientras siga siendo el subdominio, esto no
     * hace nada. Así el DNS y el código no pueden discrepar en ningún momento.
     */
    public function test_it_does_nothing_until_app_url_moves(): void
    {
        config(['app.url' => 'https://citas.glowstudios.vip']);

        $this->get('https://citas.glowstudios.vip/')->assertOk();
    }

    /**
     * El webhook de Kapso llega por POST a este mismo host. Un 301 le pediría
     * repetir la entrega contra otra URL y el bot dejaría de recibir mensajes.
     */
    public function test_the_whatsapp_webhook_is_never_redirected(): void
    {
        config(['app.url' => 'https://glowstudios.vip']);

        // Sin firma válida es un 401, no un 301: lo que importa es que la
        // petición llegue al middleware de la firma en vez de rebotar antes.
        $this->post('https://citas.glowstudios.vip/api/kapso/webhook', [])
            ->assertStatus(401);
    }

    public function test_the_health_check_is_never_redirected(): void
    {
        config(['app.url' => 'https://glowstudios.vip']);

        $this->get('https://citas.glowstudios.vip/up')->assertOk();
    }
}
