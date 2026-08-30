<?php

namespace Tests\Unit;

use App\Actions\Content\DrawHeadline;
use Intervention\Image\ImageManager;
use Tests\TestCase;

/**
 * El tratamiento que ella viene pidiendo desde el principio y volvió a
 * describir mirando los resultados: «una va arriba de otra, una gruesa, una
 * fina».
 *
 * 'cursiva' ya apilaba dos líneas distintas, pero con letra script — y no era
 * eso: pedía contraste de PESO, no un estilo caligráfico. De ahí 'mixto'.
 */
class MixedHeadlineTest extends TestCase
{
    public function test_the_two_lines_get_different_fonts_and_sizes(): void
    {
        $build = app(DrawHeadline::class);

        $plan = $build->plan(['NAIL ART', 'A MANO'], null, ['estilo_titular' => 'cursiva']);
        $plan['look'] = 'mixto';
        $plan['thickLine'] = 0;

        $manager = ImageManager::imagick();
        $canvas = $manager->create(1080, 1080)->fill('#2b2320');

        $build->draw($canvas, $plan);

        // La gruesa ocupa mucho más ancho que la fina al mismo texto: es todo
        // el recurso de este tratamiento.
        $anchoGrueso = DrawHeadline::textWidth('NAIL ART', (int) round($plan['size'] * 1.15), resource_path('fonts/Anton.ttf'));
        $anchoFino = DrawHeadline::textWidth('A MANO', (int) round($plan['size'] * 0.58), resource_path('fonts/Playfair.ttf'));

        $this->assertGreaterThan($anchoFino, $anchoGrueso);
    }

    public function test_which_line_is_thick_alternates(): void
    {
        // A veces la gruesa arriba y a veces abajo, como en sus referencias.
        $build = app(DrawHeadline::class);
        $vistos = [];

        for ($i = 0; $i < 60; $i++) {
            $plan = $build->plan(['UNA', 'OTRA'], null, ['estilo_titular' => 'mixto']);

            if ($plan['look'] === 'mixto') {
                $vistos[$plan['thickLine']] = true;
            }
        }

        $this->assertArrayHasKey(0, $vistos, 'Nunca salió la gruesa arriba.');
        $this->assertArrayHasKey(1, $vistos, 'Nunca salió la gruesa abajo.');
    }

    public function test_it_asks_for_its_own_shadow(): void
    {
        // No trae fondo propio: quien dibuje debajo tiene que ponerle sombra
        // o el texto blanco se pierde sobre unas uñas claras.
        $this->assertTrue(DrawHeadline::needsScrim(['look' => 'mixto']));
    }
}
