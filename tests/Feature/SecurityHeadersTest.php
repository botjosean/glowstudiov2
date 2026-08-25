<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Que las cabeceras no se caigan sin que nadie se entere.
 *
 * El 2026-08-25 producción no mandaba ni una. Estas pruebas existen para que
 * el día que alguien reordene el middleware, se entere aquí y no auditando el
 * servidor seis meses después.
 */
class SecurityHeadersTest extends TestCase
{
    public function test_una_pagina_publica_sale_con_sus_cabeceras(): void
    {
        $r = $this->get('/');

        $r->assertHeader('X-Content-Type-Options', 'nosniff');
        $r->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $r->assertHeader('X-Frame-Options', 'DENY');
    }

    /**
     * La que de verdad protege el panel: sin esto, el panel de una
     * profesional se puede meter en un iframe invisible y engañarla para que
     * toque botones que no ve.
     */
    public function test_el_panel_no_se_puede_meter_en_un_iframe(): void
    {
        $r = $this->get('/admin/citas');

        $r->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString(
            "frame-ancestors 'none'",
            (string) $r->headers->get('Content-Security-Policy'),
            'La CSP aplicada tiene que cerrar los marcos.',
        );
    }

    /**
     * La política completa va en Report-Only a propósito, para que no pueda
     * romper la app mientras se comprueba. Si alguien la pasa a aplicada, que
     * sea a sabiendas: esta prueba falla y le obliga a leer el porqué.
     */
    public function test_la_politica_completa_todavia_no_bloquea(): void
    {
        $r = $this->get('/');

        $aplicada = (string) $r->headers->get('Content-Security-Policy');
        $vigilando = (string) $r->headers->get('Content-Security-Policy-Report-Only');

        $this->assertSame("frame-ancestors 'none'", $aplicada);
        $this->assertStringContainsString("default-src 'self'", $vigilando);
        $this->assertStringContainsString("object-src 'none'", $vigilando);
        $this->assertStringContainsString('nonce-', $vigilando);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $vigilando);
    }

    /**
     * Un nonce que se repite entre peticiones no sirve de nada: quien lo
     * adivine una vez lo tiene para siempre.
     */
    public function test_el_nonce_cambia_en_cada_peticion(): void
    {
        $uno = $this->nonceDe($this->get('/')->headers->get('Content-Security-Policy-Report-Only'));
        $dos = $this->nonceDe($this->get('/')->headers->get('Content-Security-Policy-Report-Only'));

        $this->assertNotSame('', $uno);
        $this->assertNotSame($uno, $dos);
    }

    /**
     * HSTS sólo sobre HTTPS. Mandarlo por HTTP no hace nada, y en desarrollo
     * le fijaría al navegador el dominio local en HTTPS para siempre.
     */
    public function test_hsts_no_sale_sobre_http(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_sale_sobre_https_y_sin_preload(): void
    {
        $r = $this->get('https://localhost/');

        $hsts = (string) $r->headers->get('Strict-Transport-Security');

        $this->assertStringContainsString('max-age=31536000', $hsts);
        $this->assertStringContainsString('includeSubDomains', $hsts);
        // `preload` es una puerta de una sola dirección y la decide el dueño.
        $this->assertStringNotContainsString('preload', $hsts);
    }

    private function nonceDe(?string $politica): string
    {
        if ($politica === null || preg_match("/'nonce-([^']+)'/", $politica, $m) !== 1) {
            return '';
        }

        return $m[1];
    }
}
