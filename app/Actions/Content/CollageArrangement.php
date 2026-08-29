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

        // Antes y después: la composición con más tracción en un salón, y
        // solo tiene sentido con exactamente dos fotos.
        if ($count === 2) {
            $options[] = 'before-after';
        }

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
            $options[] = 'banner-bottom';
        }

        // Dos columnas de distinto ancho: la revista clásica.
        if ($count >= 4) {
            $options[] = 'magazine';
        }

        // Una al centro más alta, flanqueada. Necesita impar y al menos tres.
        if ($count >= 5 && $count % 2 === 1) {
            $options[] = 'center-stage';
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
            'banner-top' => self::banner($count, true),
            'banner-bottom' => self::banner($count, false),
            'magazine' => self::magazine($count),
            // Dos mitades exactas. Las etiquetas las pone BuildCollage.
            'before-after' => [[0, 0, 0.5, 1], [0.5, 0, 0.5, 1]],
            'center-stage' => self::centerStage($count),
            default => self::even($count),
        };
    }

    /**
     * Dos columnas de distinto ancho, la de la izquierda más generosa.
     *
     * @return list<array{float, float, float, float}>
     */
    private static function magazine(int $count): array
    {
        $wide = 0.58;
        $left = (int) ceil($count / 2);
        $right = $count - $left;

        $rects = [];
        $lh = 1 / $left;

        for ($i = 0; $i < $left; $i++) {
            $rects[] = [0, $i * $lh, $wide, $lh];
        }

        $rh = 1 / max($right, 1);

        for ($i = 0; $i < $right; $i++) {
            $rects[] = [$wide, $i * $rh, 1 - $wide, $rh];
        }

        return $rects;
    }

    /**
     * Una columna central más ancha con las demás repartidas a los lados.
     *
     * Solo con cantidad impar: con par queda un lado con una foto de más y
     * se ve desbalanceado.
     *
     * @return list<array{float, float, float, float}>
     */
    private static function centerStage(int $count): array
    {
        $middle = 0.46;
        $side = (1 - $middle) / 2;
        $perSide = intdiv($count - 1, 2);
        $h = 1 / $perSide;

        $rects = [[$side, 0, $middle, 1]];

        for ($i = 0; $i < $perSide; $i++) {
            $rects[] = [0, $i * $h, $side, $h];
        }

        for ($i = 0; $i < $perSide; $i++) {
            $rects[] = [$side + $middle, $i * $h, $side, $h];
        }

        return $rects;
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
     * Una franja ancha —arriba o abajo— y el resto repartido en la otra mitad.
     *
     * @return list<array{float, float, float, float}>
     */
    private static function banner(int $count, bool $onTop): array
    {
        $others = $count - 1;
        $band = 0.44;
        $w = 1 / $others;

        if ($onTop) {
            $rects = [[0, 0, 1, $band]];

            for ($i = 0; $i < $others; $i++) {
                $rects[] = [$i * $w, $band, $w, 1 - $band];
            }

            return $rects;
        }

        $rects = [[0, 1 - $band, 1, $band]];

        for ($i = 0; $i < $others; $i++) {
            $rects[] = [$i * $w, 0, $w, 1 - $band];
        }

        return $rects;
    }
}
