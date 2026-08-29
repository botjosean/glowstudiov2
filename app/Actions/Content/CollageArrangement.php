<?php

namespace App\Actions\Content;

/**
 * Dónde va cada foto dentro del cuadrado.
 *
 * Existe porque el collage salía SIEMPRE igual —la misma rejilla pareja— y
 * ella lo dijo sin rodeos: «quiero que me dé opciones, no siempre la misma
 * de 1 foto dividida en 4». Cada armado devuelve rectángulos en fracciones
 * del lienzo, y quien llama elige uno al azar entre los que sirven para esa
 * cantidad de fotos.
 *
 * Fracciones y no píxeles para que el mismo armado valga en cualquier
 * tamaño de lienzo, y para poder leer las proporciones de un vistazo.
 */
class CollageArrangement
{
    /**
     * Los armados que sirven para esa cantidad de fotos.
     *
     * @return list<string>
     */
    public static function optionsFor(int $count): array
    {
        $options = ['even'];

        // Destacar una necesita al menos una que destacar y otras al lado.
        if ($count >= 3) {
            $options[] = 'feature-left';
            $options[] = 'feature-top';
        }

        if ($count >= 3 && $count <= 5) {
            $options[] = 'feature-right';
        }

        // Una franja ancha arriba con el resto abajo.
        if ($count >= 3) {
            $options[] = 'banner-top';
        }

        return $options;
    }

    /**
     * Los rectángulos de cada foto, en fracciones (x, y, ancho, alto).
     *
     * @return list<array{float, float, float, float}>
     */
    public static function rects(string $name, int $count): array
    {
        return match ($name) {
            'feature-left' => self::feature($count, 'left'),
            'feature-right' => self::feature($count, 'right'),
            'feature-top' => self::feature($count, 'top'),
            'banner-top' => self::bannerTop($count),
            default => self::even($count),
        };
    }

    /**
     * Filas parejas, de distinto largo para no dejar huecos: cinco fotos
     * salen 3 + 2 y siete salen 3 + 2 + 2.
     *
     * @return list<array{float, float, float, float}>
     */
    private static function even(int $count): array
    {
        $rows = max(1, (int) round(sqrt($count)));
        $base = intdiv($count, $rows);
        $extra = $count % $rows;

        $rects = [];
        $h = 1 / $rows;

        for ($row = 0; $row < $rows; $row++) {
            $inRow = $base + ($row < $extra ? 1 : 0);
            $w = 1 / $inRow;

            for ($col = 0; $col < $inRow; $col++) {
                $rects[] = [$col * $w, $row * $h, $w, $h];
            }
        }

        return $rects;
    }

    /**
     * Una grande y el resto en una tira al lado (o debajo).
     *
     * @return list<array{float, float, float, float}>
     */
    private static function feature(int $count, string $side): array
    {
        $others = $count - 1;
        $big = 0.62;

        if ($side === 'top') {
            $w = 1 / $others;
            $rects = [[0, 0, 1, $big]];

            for ($i = 0; $i < $others; $i++) {
                $rects[] = [$i * $w, $big, $w, 1 - $big];
            }

            return $rects;
        }

        $h = 1 / $others;

        if ($side === 'left') {
            $rects = [[0, 0, $big, 1]];

            for ($i = 0; $i < $others; $i++) {
                $rects[] = [$big, $i * $h, 1 - $big, $h];
            }

            return $rects;
        }

        $rects = [[1 - $big, 0, $big, 1]];

        for ($i = 0; $i < $others; $i++) {
            $rects[] = [0, $i * $h, 1 - $big, $h];
        }

        return $rects;
    }

    /**
     * Una franja ancha arriba y el resto repartido abajo.
     *
     * @return list<array{float, float, float, float}>
     */
    private static function bannerTop(int $count): array
    {
        $others = $count - 1;
        $top = 0.44;
        $w = 1 / $others;

        $rects = [[0, 0, 1, $top]];

        for ($i = 0; $i < $others; $i++) {
            $rects[] = [$i * $w, $top, $w, 1 - $top];
        }

        return $rects;
    }
}
