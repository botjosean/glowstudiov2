<?php

namespace App\Enums;

/**
 * Los modelos de post que la app sabe armar.
 *
 * Ella elige cuál quiere; el sistema no lo adivina. Por ahora solo vive
 * Collage4 — los demás se agregan cuando existan de verdad, no antes: un
 * modelo que aparece en la lista y no funciona es peor que no ofrecerlo.
 */
enum PostLayout: string
{
    /** Cuatro fotos en una sola imagen cuadrada, con su marco. */
    case Collage4 = 'collage_4';

    /** Cuántas fotos hacen falta para armar este modelo. */
    public function photoCount(): int
    {
        return match ($this) {
            self::Collage4 => 4,
        };
    }
}
