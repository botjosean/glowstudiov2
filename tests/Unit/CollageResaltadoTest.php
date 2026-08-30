<?php

namespace Tests\Unit;

use App\Actions\Content\DrawHeadline;
use Intervention\Image\Interfaces\ImageInterface;
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
    /**
     * "Resaltado" pesa más en la bolsa de estilos cuando la ficha lo pide,
     * pero ya no es el único que puede salir —ella pidió justo eso, variedad
     * real, ver BrandStyle::headlineStylesFor—, así que un intento solo
     * puede caer en otro tratamiento sin que eso sea un fallo de verdad.
     * Se reintenta hasta pintar de nuevo sobre un lienzo limpio.
     */
    private function drawResaltado(array $lines): ImageInterface
    {
        $manager = ImageManager::imagick();
        $build = app(DrawHeadline::class);
        $style = ['colores' => ['#111827', '#e11d63', '#7c3aed'], 'estilo_titular' => 'resaltado', 'posicion_texto' => 'centro'];

        for ($intento = 0; $intento < 15; $intento++) {
            $plan = $build->plan($lines, null, $style);

            if ($plan['look'] === 'resaltado') {
                $canvas = $manager->create(1080, 1080)->fill('#2b2320');
                $build->draw($canvas, $plan);

                return $canvas;
            }
        }

        $this->fail('"resaltado" no salió sorteado en quince intentos.');
    }

    public function test_the_highlighted_word_and_the_rest_of_the_line_use_different_colors(): void
    {
        $canvas = $this->drawResaltado(['PALABRA CLAVE']);

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
        $canvas = $this->drawResaltado(['PROFESIONAL']);

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
