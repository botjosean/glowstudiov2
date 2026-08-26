<?php

namespace Tests\Unit\Design;

use PHPUnit\Framework\TestCase;

/**
 * Cuándo sale la pantalla de carga.
 *
 * Esto ya cambió tres veces —una vez por sesión, luego en cada navegación, y
 * ahora la regla de en medio— y las tres las pidió el dueño. Esta prueba no
 * defiende una opinión: defiende la ÚLTIMA que él pidió, para que la cuarta
 * vuelta sea a sabiendas.
 *
 * **Y defiende sobre todo el DÓNDE de una variable**, que es lo que rompió
 * esto el 2026-08-26. La bandera se escribió dentro de `<script setup>`, donde
 * parece de módulo pero no lo es: Vue compila todo ese bloque dentro de la
 * función de montaje, así que se reiniciaba en cada cambio de pestaña y la
 * corona siguió saliendo en todas partes.
 *
 * La primera versión de esta prueba no lo atrapó porque comprobaba que el
 * texto empezara en una línea, no dónde acababa al compilar. Una prueba que
 * mide la forma y no el comportamiento da confianza falsa, que es peor que no
 * tener prueba. Ahora parte el archivo por bloques y mira en cuál cae.
 */
class SplashRuleTest extends TestCase
{
    private const SPLASH = __DIR__.'/../../../resources/js/Components/ui/SplashScreen.vue';

    private const LAYOUT = __DIR__.'/../../../resources/js/Layouts/AdminLayout.vue';

    /**
     * @return array{modulo: string, setup: string}
     */
    private function bloques(): array
    {
        $fuente = file_get_contents(self::SPLASH);

        // Anclados a principio de línea: las etiquetas de verdad están solas
        // en su línea, y las menciones en los comentarios van en medio de una
        // frase. Sin el ancla, el comentario del bloque de módulo —que habla
        // justamente de `<script setup>`— se hacía pasar por la etiqueta.
        preg_match('/^<script>$(.*?)^<\/script>$/ms', $fuente, $m1);
        preg_match('/^<script setup>$(.*?)^<\/script>$/ms', $fuente, $m2);

        return [
            'modulo' => $m1[1] ?? '',
            'setup' => $m2[1] ?? '',
        ];
    }

    /**
     * El fallo que costó un despliegue: dentro de `<script setup>` la bandera
     * se reinicia en cada montaje, o sea en cada pestaña.
     */
    public function test_la_bandera_vive_fuera_de_script_setup(): void
    {
        ['modulo' => $modulo, 'setup' => $setup] = $this->bloques();

        $this->assertNotSame(
            '',
            $modulo,
            'Desapareció el bloque <script> normal. Sin él no hay ámbito de módulo y la corona vuelve a salir en cada pestaña.',
        );

        $this->assertStringContainsString(
            'let yaSalioEnEstaCarga = false;',
            $modulo,
            'La bandera tiene que declararse en el bloque <script> normal, no en <script setup>.',
        );

        $this->assertStringNotContainsString(
            'let yaSalioEnEstaCarga',
            $setup,
            'La bandera volvió a <script setup>: allí Vue la mete dentro de setup() y se reinicia en cada montaje.',
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
            $this->bloques()['setup'],
            'Se perdió el caso de "primera vez desde que cargó la página".',
        );
    }

    /**
     * Y se marca aunque no llegue a verse: lo que cuenta es si esta carga del
     * navegador ya montó la pantalla, no si se mostró.
     */
    public function test_la_bandera_se_marca_en_cada_montaje(): void
    {
        $this->assertStringContainsString(
            'yaSalioEnEstaCarga = true;',
            $this->bloques()['setup'],
            'Si no se marca, la bandera no sirve de nada.',
        );
    }

    /**
     * Sólo Ajustes. Ni Citas, ni Clientas, ni Ventas, ni Perfil: son sitios
     * por los que se pasa todo el día.
     */
    public function test_solo_ajustes_la_ensena_al_cambiar_de_pestana(): void
    {
        $layout = file_get_contents(self::LAYOUT);

        $this->assertMatchesRegularExpression(
            '/:also-on="\[\'\/admin\/ajustes\'\]"/',
            $layout,
            'La lista de pantallas que enseñan la corona cambió sin querer.',
        );

        foreach (['/admin/citas', '/admin/ventas', '/admin/clientas', '/admin/perfil'] as $trabajo) {
            $this->assertStringNotContainsString(
                "'{$trabajo}'\]",
                $layout,
                "{$trabajo} no debe enseñar la corona: se pasa por ahí todo el día.",
            );
        }
    }

    /**
     * Y quien pidió menos movimiento en su teléfono no la ve nunca.
     */
    public function test_se_respeta_quien_pidio_menos_movimiento(): void
    {
        $this->assertStringContainsString(
            'prefers-reduced-motion: reduce',
            $this->bloques()['setup'],
            'Se perdió el respeto a "reducir movimiento".',
        );
    }
}
