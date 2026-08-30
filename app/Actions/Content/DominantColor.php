<?php

namespace App\Actions\Content;

/**
 * El color que manda en una foto — el del esmalte, el del tinte.
 *
 * **Sin IA y sin costo.** Es una cuenta sobre los píxeles: se reduce la foto
 * a un puñado de colores y se elige el más vivo de entre los que ocupan más
 * lugar. Eso basta, y a diferencia del modelo de visión nunca se equivoca ni
 * cobra.
 *
 * Se busca el más VIVO y no el más frecuente a propósito: en una foto de
 * uñas lo que más superficie ocupa casi siempre es la piel de la mano o el
 * fondo, y un beige apagado no es el color del trabajo. El esmalte es lo
 * saturado.
 */
class DominantColor
{
    /** Lo ancho que se mira. Más no cambia el resultado y solo tarda. */
    private const SAMPLE = 96;

    /** Cuánto se redondea cada canal para agrupar tonos parecidos. */
    private const STEP = 24;

    /**
     * @return string  el color en #RRGGBB
     */
    public function handle(string $binary): string
    {
        $image = new \Imagick;
        $image->readImageBlob($binary);
        $image->setImageColorspace(\Imagick::COLORSPACE_SRGB);
        $image->resizeImage(self::SAMPLE, self::SAMPLE, \Imagick::FILTER_BOX, 1);

        // Los grupos se arman a mano, redondeando cada canal, en vez de con
        // quantizeImage(). Esa función DEFORMA los colores: un verde oliva
        // #5A6B32 salía como #1A2508 —casi negro— y entonces el detector lo
        // descartaba por oscuro. Lo cazó una prueba con colores conocidos.
        $pixels = $image->exportImagePixels(0, 0, self::SAMPLE, self::SAMPLE, 'RGB', \Imagick::PIXEL_CHAR);
        $total = self::SAMPLE * self::SAMPLE;

        /** @var array<string, array{n: int, r: int, g: int, b: int}> $grupos */
        $grupos = [];

        for ($i = 0; $i + 2 < count($pixels); $i += 3) {
            [$r, $g, $b] = [$pixels[$i], $pixels[$i + 1], $pixels[$i + 2]];

            $clave = intdiv($r, self::STEP).':'.intdiv($g, self::STEP).':'.intdiv($b, self::STEP);

            if (! isset($grupos[$clave])) {
                $grupos[$clave] = ['n' => 0, 'r' => 0, 'g' => 0, 'b' => 0];
            }

            $grupos[$clave]['n']++;
            $grupos[$clave]['r'] += $r;
            $grupos[$clave]['g'] += $g;
            $grupos[$clave]['b'] += $b;
        }

        $mejor = null;
        $mejorPuntaje = -1.0;

        foreach ($grupos as $grupo) {
            $peso = $grupo['n'] / $total;

            // Menos del 4% del cuadro es un detalle suelto —un anillo, un
            // brillo—, no el color del trabajo.
            if ($peso < 0.04) {
                continue;
            }

            // El color del grupo es el promedio de sus píxeles, no el centro
            // de la casilla: así el tono devuelto es uno que de verdad está
            // en la foto.
            $r = intdiv($grupo['r'], $grupo['n']);
            $g = intdiv($grupo['g'], $grupo['n']);
            $b = intdiv($grupo['b'], $grupo['n']);

            $puntaje = self::vividness($r, $g, $b) * (0.5 + $peso);

            if ($puntaje > $mejorPuntaje) {
                $mejorPuntaje = $puntaje;
                $mejor = [$r, $g, $b];
            }
        }

        if ($mejor === null) {
            return '#8a6a5a';
        }

        return sprintf('#%02X%02X%02X', ...$mejor);
    }

    /**
     * Cuánto "pesa" un color como color del trabajo.
     *
     * Lo que se descarta es una sola cosa: lo **claro y sin color** — la
     * piel, una sábana blanca, la pared del fondo. Todo lo demás compite.
     *
     * Y compite por dos caminos, no uno: la saturación (un esmalte rojo o
     * verde) **o** lo oscuro que sea (un esmalte negro azabache, que tiene
     * saturación cero y aun así es un color de verdad). Castigar lo oscuro,
     * como se hizo primero, hacía que con uñas negras ganara el tono de la
     * piel — lo cazó una prueba antes de llegar a un post.
     *
     * El peso de lo oscuro es modesto a propósito: si fuera alto, la sombra
     * bajo la mano le ganaría a un esmalte de color.
     */
    private static function vividness(int $r, int $g, int $b): float
    {
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);

        $saturacion = $max === 0 ? 0.0 : ($max - $min) / $max;
        $luz = $max / 255;

        // Claro y lavado: piel, papel, pared. No es el trabajo.
        if ($luz > 0.90 && $saturacion < 0.25) {
            return 0.05;
        }

        // Y lo casi-negro se castiga, porque en una foto de mano casi
        // siempre es la sombra y no el esmalte. Probado contra las ocho
        // fotos reales de Vane: sin este castigo, las ocho daban un
        // casi-negro que era la sombra. Con él, dan el vino y el espresso
        // que de verdad se ven.
        //
        // **Límite conocido:** un esmalte negro azabache sobre piel clara
        // cae del mismo lado que la sombra, y ahí puede salir el tono de la
        // piel. Separar los dos mirando solo los píxeles no se puede: son
        // el mismo color. Se prefiere fallar en el caso raro (uñas negras)
        // antes que en el común (uñas de color).
        if ($luz < 0.18) {
            return (0.20 + $saturacion) * 0.30;
        }

        return 0.20 + $saturacion;
    }
}
