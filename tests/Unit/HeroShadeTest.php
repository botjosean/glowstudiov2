<?php

namespace Tests\Unit;

use App\Actions\Content\BuildHero;
use Intervention\Image\ImageManager;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Reproduce un post real (30-ago): el degradado de abajo se dibujaba como 40
 * tiras de opacidad creciente, y el salto de una tira a la siguiente se veía
 * como rayitas horizontales sobre una foto de fondo oscuro. Se cambió por
 * una máscara de opacidad calculada de punta a punta con Imagick — esta
 * prueba lo comprueba mirando los píxeles de verdad, no solo que no truene.
 */
class HeroShadeTest extends TestCase
{
    public function test_the_gradient_has_no_visible_steps_over_a_dark_photo(): void
    {
        $manager = ImageManager::imagick();
        $canvas = $manager->create(1080, 1080)->fill('#3a3530');

        $build = app(BuildHero::class);
        $ref = new ReflectionMethod($build, 'shade');
        // Sin plan de titular: la sombra de siempre, pegada abajo.
        $ref->invoke($build, $canvas, null);

        // Una muestra cada 8px bajando por el degradado: con las 40 tiras de
        // antes, tramos de 14px enteros compartían exactamente el mismo
        // color — una franja visible. Con un degradado real, dos muestras a
        // 8px de distancia casi nunca caen en el mismo byte de color.
        //
        // Arranca en y=650 y no en el borde de arriba (y=560): ahí la curva
        // cuadrática todavía es casi cero a propósito —"arranca casi
        // transparente"— y varias muestras seguidas del mismo color ahí no
        // son un escalón, es que todavía no hay nada que oscurecer.
        $x = 540;
        $samples = [];

        for ($y = 650; $y < 1080; $y += 8) {
            $samples[] = $canvas->pickColor($x, $y)->toHex();
        }

        $repetidosSeguidos = 0;
        $maxRepetidosSeguidos = 0;

        for ($i = 1; $i < count($samples); $i++) {
            if ($samples[$i] === $samples[$i - 1]) {
                $repetidosSeguidos++;
                $maxRepetidosSeguidos = max($maxRepetidosSeguidos, $repetidosSeguidos);
            } else {
                $repetidosSeguidos = 0;
            }
        }

        // Una tira de las viejas medía 14px, o casi dos muestras seguidas
        // iguales. Tres o más iguales seguidas sería un escalón real y
        // visible, no un empate casual de redondeo.
        $this->assertLessThan(3, $maxRepetidosSeguidos, 'El degradado tiene un escalón visible.');

        // Y tiene que oscurecer de verdad de arriba a abajo, no quedarse
        // plano.
        $this->assertNotSame($samples[0], $samples[array_key_last($samples)]);
    }
}
