<?php

namespace Tests\Unit;

use App\Actions\Content\DrawHeadline;
use Intervention\Image\ImageManager;
use Tests\TestCase;

/**
 * Otra referencia de video que ella señaló directo (30-ago): en varios de
 * sus subtítulos el texto es blanco grueso con una sola palabra o frase
 * resaltada en un color de acento, sin recuadro de fondo. Ver
 * DrawHeadline::handle().
 */
class CollageResaltadoTest extends TestCase
{
    public function test_the_highlighted_word_and_the_rest_of_the_line_use_different_colors(): void
    {
        $manager = ImageManager::imagick();
        $canvas = $manager->create(1080, 1080)->fill('#2b2320');

        $build = app(DrawHeadline::class);
        $style = ['colores' => ['#111827', '#e11d63', '#7c3aed'], 'estilo_titular' => 'resaltado', 'posicion_texto' => 'centro'];

        $build->handle($canvas, ['PALABRA CLAVE'], null, $style);

        $foundWhite = false;
        $foundAccent = false;

        // Un barrido horizontal a la altura del renglón: no hace falta saber
        // el píxel exacto de cada letra, alcanza con que los dos colores
        // aparezcan en algún lado de la línea.
        for ($x = 50; $x < 1030; $x += 4) {
            $hex = strtolower($canvas->pickColor($x, 540)->toHex());

            $foundWhite = $foundWhite || $hex === 'ffffff';
            $foundAccent = $foundAccent || $hex === 'e11d63';
        }

        $this->assertTrue($foundWhite, 'Debería haber texto blanco en la línea.');
        $this->assertTrue($foundAccent, 'Debería haber una palabra en el color de acento.');
    }

    public function test_a_single_word_highlighted_line_is_painted_entirely_in_the_accent_color(): void
    {
        // Sin "resto" que dejar en blanco: la línea entera va en acento.
        $manager = ImageManager::imagick();
        $canvas = $manager->create(1080, 1080)->fill('#2b2320');

        $build = app(DrawHeadline::class);
        $style = ['colores' => ['#111827', '#e11d63', '#7c3aed'], 'estilo_titular' => 'resaltado', 'posicion_texto' => 'centro'];

        $build->handle($canvas, ['PROFESIONAL'], null, $style);

        $foundAccent = false;

        for ($x = 50; $x < 1030; $x += 4) {
            if (strtolower($canvas->pickColor($x, 540)->toHex()) === 'e11d63') {
                $foundAccent = true;

                break;
            }
        }

        $this->assertTrue($foundAccent, 'Una sola palabra resaltada debería pintarse en el color de acento.');
    }
}
