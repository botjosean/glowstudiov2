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
     * Un juego de láminas: portada, collages en el medio y cierre.
     *
     * Es lo que ella quería desde el principio cuando hablaba de carruseles.
     * Ver BuildCarousel para el reparto.
     */
    case Carousel = 'carousel';

    /**
     * Fondo del color del trabajo, las fotos en las esquinas y el nombre del
     * color grande en el medio.
     *
     * Es la forma que ella trajo una y otra vez —Mimosa Studio, «Butter
     * yellow», «Milky Pink», «Mocha Mousse»— y la resumió así: «agarra la
     * foto, la pone alrededor de la pantalla, pone algo en el medio, el fondo
     * lo pone de un color; esto debería ser una plantilla, está demasiado
     * fácil». Ver BuildColorBlock.
     */
    case ColorBlock = 'color';

    /**
     * Dos fotos, una arriba y otra abajo, con una tarjetita de dos colores
     * en el medio: el nombre y el hex de cada uno, apilados.
     *
     * Es la que trajo de @metanoia.espaciodeestetica: «Combinaciones ¿sí o
     * no?», dos colores de esmalte usados juntos en el mismo trabajo. No es
     * un descuido de tanda mezclada —es justo lo contrario de eso—, así que
     * usa la misma lectura de color de cada foto pero para lo opuesto:
     * requiere DOS colores distintos, no uno solo repetido. Ver
     * BuildColorCombo.
     */
    case ColorCombo = 'combo';

    /** Cuántas fotos necesita como mínimo. */
    public function minPhotos(): int
    {
        return match ($this) {
            self::Hero => 1,
            self::Collage, self::Carousel, self::ColorBlock, self::ColorCombo => 2,
        };
    }

    /**
     * Tope por post. No es una limitación técnica: pasadas doce, cada foto
     * queda tan chica en un cuadrado de Instagram que no se distingue el
     * trabajo — que es justamente lo que se quiere mostrar.
     */
    public function maxPhotos(): int
    {
        return match ($this) {
            self::Hero => 1,
            self::Collage => 12,
            // Portada + una foto por lámina, sin pasar las diez que acepta
            // Instagram.
            self::Carousel => 10,
            // Cuatro esquinas y ni una más: la gracia es que el centro quede
            // libre para el nombre del color.
            self::ColorBlock => 4,
            // Dos colores, dos fotos: una por color.
            self::ColorCombo => 2,
        };
    }

    /** Cuántas usa, dado lo que ella tiene esperando: todas las que quepan. */
    public function photosToUse(int $available): int
    {
        return $available < $this->minPhotos() ? 0 : min($available, $this->maxPhotos());
    }

    public function fits(int $available): bool
    {
        return $this->photosToUse($available) > 0;
    }

    /**
     * Cuántas fotos van en cada fila.
     *
     * Antes solo se aceptaban las cantidades que cuadraban en un rectángulo
     * exacto (2, 3, 4, 6, 9) y con cinco fotos se descartaba una. Ella lo dijo
     * claro: «se deben poder subir muchas y que arme algo con todas».
     *
     * Con filas de distinto largo se usan TODAS sin dejar huecos: cada fila se
     * reparte el ancho completo entre las suyas, así que cinco salen 3 + 2 y
     * siete salen 3 + 2 + 2. Las fotos de una fila más corta quedan un poco
     * más anchas, que es mucho mejor que un hueco blanco o una foto perdida.
     *
     * @return list<int>
     */
    public static function rowSizes(int $count): array
    {
        if ($count <= 1) {
            return [max($count, 0)];
        }

        $rows = max(1, (int) round(sqrt($count)));
        $base = intdiv($count, $rows);
        $extra = $count % $rows;

        $sizes = [];

        for ($i = 0; $i < $rows; $i++) {
            // Las filas de arriba se llevan la foto de más, para que la más
            // llena quede primero.
            $sizes[] = $base + ($i < $extra ? 1 : 0);
        }

        return $sizes;
    }
}
