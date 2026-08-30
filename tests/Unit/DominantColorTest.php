<?php

namespace Tests\Unit;

use App\Actions\Content\ColorNames;
use App\Actions\Content\DominantColor;
use Tests\TestCase;

/**
 * El color del trabajo, sacado de la foto sin IA. Es lo que le da nombre a la
 * tarjeta de color de las plantillas — ver ColorNames.
 */
class DominantColorTest extends TestCase
{
    /**
     * Una foto de prueba: una franja de color sobre un fondo claro y una
     * sombra oscura, que es la forma real de una foto de uñas — piel y fondo
     * claros, sombra bajo la mano, y el esmalte ocupando poco.
     */
    private function foto(string $esmalte): string
    {
        $im = new \Imagick;
        $im->newImage(200, 200, new \ImagickPixel('#EFE6DE')); // piel y fondo
        $im->setImageFormat('png');

        $draw = new \ImagickDraw;
        $draw->setFillColor(new \ImagickPixel('#151011')); // sombra bajo la mano
        $draw->rectangle(0, 150, 199, 199);
        $draw->setFillColor(new \ImagickPixel($esmalte));
        $draw->rectangle(40, 40, 160, 130);
        $im->drawImage($draw);

        return $im->getImageBlob();
    }

    public function test_it_finds_the_polish_and_not_the_skin_or_the_shadow(): void
    {
        $hex = app(DominantColor::class)->handle($this->foto('#7B1E28'));

        [$r, $g, $b] = array_map('hexdec', str_split(ltrim($hex, '#'), 2));

        // Rojo dominante y ni casi-blanco ni casi-negro: la piel clara y la
        // sombra eran justo lo que ganaba antes de castigar los extremos.
        $this->assertGreaterThan($g, $r, "Salió {$hex}, que no es el rojo del esmalte.");
        $this->assertGreaterThan($b, $r, "Salió {$hex}, que no es el rojo del esmalte.");
        $this->assertGreaterThan(40, $r, "Salió {$hex}: agarró la sombra.");
        $this->assertLessThan(230, $r, "Salió {$hex}: agarró la piel.");
    }

    public function test_a_saturated_polish_wins_over_the_shadow(): void
    {
        // Lo que de verdad importa: que la sombra bajo la mano no le gane al
        // esmalte. Contra las ocho fotos reales de Vane, sin castigar lo
        // casi-negro las ocho daban la sombra.
        foreach (['#5A6B32' => 'Olive Green', '#A81D2D' => 'Cherry Red', '#D96BA0' => 'Fuchsia Pink'] as $esmalte => $esperado) {
            $hex = app(DominantColor::class)->handle($this->foto($esmalte));

            $this->assertSame(
                $esperado,
                ColorNames::nearest($hex),
                "Con esmalte {$esmalte} salió {$hex}, que no es el esmalte.",
            );
        }
    }

    public function test_every_colour_gets_a_name_in_both_languages(): void
    {
        foreach (['#7B1E28', '#5A6B32', '#F0DDD5', '#1A1A1A', '#C9A227'] as $hex) {
            $this->assertNotSame('', ColorNames::nearest($hex, 'en'), $hex.' sin nombre en inglés');
            $this->assertNotSame('', ColorNames::nearest($hex, 'es'), $hex.' sin nombre en español');
        }
    }

    public function test_the_nearest_name_is_the_obvious_one(): void
    {
        $this->assertSame('Olive Green', ColorNames::nearest('#5C6D35'));
        $this->assertSame('Wine Red', ColorNames::nearest('#7A1F29'));
        $this->assertSame('Verde Oliva', ColorNames::nearest('#5C6D35', 'es'));
    }
}
