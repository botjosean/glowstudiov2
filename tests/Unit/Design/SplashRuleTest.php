<?php

namespace Tests\Unit\Design;

use PHPUnit\Framework\TestCase;

/**
 * Cuándo sale la pantalla de carga.
 *
 * Esto ya cambió tres veces —una vez por sesión, luego en cada navegación,
 * y ahora la regla de en medio— y las tres veces lo pidió el dueño. Esta
 * prueba no defiende una opinión: defiende la ÚLTIMA que él pidió, para que
 * la cuarta vuelta sea a sabiendas y no por descuido de alguien que pasaba.
 *
 * Lee el fuente porque el proyecto no tiene pruebas de JavaScript y montar
 * un corredor entero para esto sería una dependencia nueva por una regla de
 * tres líneas. Lo mismo hace ColorContrastTest con la hoja de estilos.
 */
class SplashRuleTest extends TestCase
{
    private const SPLASH = __DIR__.'/../../../resources/js/Components/ui/SplashScreen.vue';

    private const LAYOUT = __DIR__.'/../../../resources/js/Layouts/AdminLayout.vue';

    /**
     * La bandera tiene que vivir en el MÓDULO. En el componente se reiniciaría
     * en cada navegación de Inertia —que es justo lo que hay que distinguir— y
     * la pantalla volvería a salir en todas partes.
     */
    public function test_la_bandera_vive_en_el_modulo_y_no_en_el_componente(): void
    {
        $fuente = file_get_contents(self::SPLASH);

        $this->assertMatchesRegularExpression(
            '/^let yaSalioEnEstaCarga = false;$/m',
            $fuente,
            'La bandera dejó de estar en el módulo: la pantalla de carga volverá a salir en cada pestaña.',
        );

        $this->assertStringNotContainsString(
            'ref(false); // yaSalio',
            $fuente,
            'La bandera no puede ser reactiva del componente.',
        );
    }

    /**
     * Una carga de verdad —abrir, recargar, volver de iniciar sesión— siempre
     * la enseña. Es la mitad de la regla que el dueño no quiso perder cuando
     * pidió que saliera menos.
     */
    public function test_una_carga_de_verdad_siempre_la_ensena(): void
    {
        $this->assertStringContainsString(
            'if (!yaSalioEnEstaCarga) {',
            file_get_contents(self::SPLASH),
            'Se perdió el caso de "primera vez desde que cargó la página".',
        );
    }

    /**
     * Perfil y Ajustes sí. Citas, Clientas y Ventas no: son las pantallas de
     * trabajo, y ella entra a cobrar veinte veces al día.
     */
    public function test_solo_perfil_y_ajustes_la_ensenan_al_cambiar_de_pestana(): void
    {
        $layout = file_get_contents(self::LAYOUT);

        $this->assertMatchesRegularExpression(
            '/:also-on="\[\'\/admin\/perfil\', \'\/admin\/ajustes\'\]"/',
            $layout,
            'La lista de pantallas que enseñan la corona cambió sin querer.',
        );

        foreach (['/admin/citas', '/admin/ventas', '/admin/clientas'] as $trabajo) {
            $this->assertStringNotContainsString(
                "'{$trabajo}'\]",
                $layout,
                "{$trabajo} es una pantalla de trabajo: la corona ahí estorba.",
            );
        }
    }

    /**
     * Y quien pidió menos movimiento en su teléfono no la ve nunca.
     */
    public function test_se_respeta_quien_pidio_menos_movimiento(): void
    {
        $this->assertStringContainsString(
            "prefers-reduced-motion: reduce",
            file_get_contents(self::SPLASH),
            'Se perdió el respeto a "reducir movimiento".',
        );
    }
}
