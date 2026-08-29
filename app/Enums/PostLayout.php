<?php

namespace App\Enums;

/**
 * Los modelos de post que la app sabe armar.
 *
 * Ella elige cuál quiere; el sistema no lo adivina. Arrancó con un solo
 * modelo a propósito —hacer uno bien antes que cinco a medias—, y se abrió
 * cuando ella pidió libertad para armar con las fotos que tenga: «¿por qué
 * solo ofrece collage y solo 4 fotos?».
 *
 * Lo que NO se hace, y es deliberado: dejar que el modelo invente un diseño
 * distinto cada vez. Eso sale inconsistente y termina rechazado. Las
 * plantillas son fijas; lo que cambia es la foto y el titular.
 */
enum PostLayout: string
{
    /** Una foto a pantalla completa con un titular grande encima. */
    case Hero = 'hero';

    /** Varias fotos en rejilla, con el titular en bloques al centro. */
    case Collage = 'collage';

    /**
     * Las cantidades de fotos con las que este modelo se ve bien.
     *
     * Cerrado y no un rango: 5 fotos en rejilla dejan un hueco vacío, y 7 u 8
     * quedan igual de torcidas. Mejor ofrecer las que cuadran.
     *
     * @return list<int>
     */
    public function photoCounts(): array
    {
        return match ($this) {
            self::Hero => [1],
            self::Collage => [2, 3, 4, 6, 9],
        };
    }

    /** Cuántas fotos usa, dado lo que ella tiene esperando. */
    public function photosToUse(int $available): int
    {
        $usable = array_filter($this->photoCounts(), static fn (int $n): bool => $n <= $available);

        return $usable === [] ? 0 : max($usable);
    }

    /** Si se puede armar con esa cantidad de fotos. */
    public function fits(int $available): bool
    {
        return $this->photosToUse($available) > 0;
    }

    /**
     * Columnas y filas de la rejilla para esa cantidad.
     *
     * @return array{int, int}
     */
    public static function grid(int $count): array
    {
        return match ($count) {
            2 => [2, 1],
            3 => [3, 1],
            4 => [2, 2],
            6 => [3, 2],
            9 => [3, 3],
            default => [1, 1],
        };
    }
}
