<?php

namespace App\Actions\Content;

/**
 * Le pone nombre a un color, como hace la tarjeta de una carta de colores.
 *
 * Nace de una referencia que ella trajo: un carrusel donde cada lámina lleva
 * la foto del trabajo y, abajo a la derecha, una tarjeta con el color y su
 * nombre — «Chili Pepper», «Whisper Pink», «Jet Black», «Caramel Café». Es
 * lo que convierte una foto de uñas en algo que parece de revista.
 *
 * **Nombres propios, no de Pantone.** Pantone es una marca registrada y su
 * catálogo con sus códigos es suyo; poner "PANTONE 19-1557" en los posts que
 * ella vende sería usar una marca ajena. Estos son nombres de color de uso
 * común en belleza —los que ya usan las marcas de esmalte— y la tarjeta que
 * los muestra no lleva ninguna marca.
 *
 * Se elige el más cercano por distancia en RGB. No es la métrica más fina que
 * existe, pero con una lista de este tamaño y colores tan separados entre sí,
 * acierta — y se puede leer de un vistazo, que vale más acá.
 */
class ColorNames
{
    /**
     * El catálogo. Nombre en español y en inglés: el nombre en inglés es el
     * que se lee en estos posts, pero el panel es bilingüe.
     *
     * @var array<string, array{0: string, 1: string}>  hex => [español, inglés]
     */
    private const CATALOG = [
        '#7B1E28' => ['Rojo Vino', 'Wine Red'],
        '#A81D2D' => ['Rojo Cereza', 'Cherry Red'],
        '#C3423F' => ['Rojo Chili', 'Chili Red'],
        '#D65A5A' => ['Coral Suave', 'Soft Coral'],
        '#E38A7A' => ['Melocotón', 'Peach'],
        '#B5651D' => ['Terracota', 'Terracotta'],
        '#A9763F' => ['Caramelo', 'Caramel'],
        '#6F4E2E' => ['Café Espresso', 'Espresso Brown'],
        '#8B6F4E' => ['Moka', 'Mocha'],
        '#C8A882' => ['Arena', 'Sand'],
        '#E8D5C4' => ['Nude Cálido', 'Warm Nude'],
        '#F0DDD5' => ['Rosa Susurro', 'Whisper Pink'],
        '#E9B8C4' => ['Rosa Bebé', 'Baby Pink'],
        '#D96BA0' => ['Rosa Fucsia', 'Fuchsia Pink'],
        '#A84B8C' => ['Ciruela', 'Plum'],
        '#6B4A8A' => ['Lavanda Oscura', 'Deep Lavender'],
        '#4A5B8C' => ['Azul Denim', 'Denim Blue'],
        '#2F4156' => ['Azul Medianoche', 'Midnight Blue'],
        '#4E6E5D' => ['Verde Salvia', 'Sage Green'],
        '#5A6B32' => ['Verde Oliva', 'Olive Green'],
        '#2E4A3D' => ['Verde Bosque', 'Forest Green'],
        '#C9A227' => ['Dorado', 'Gold'],
        '#B8B8B8' => ['Plata', 'Silver'],
        '#F2F0EB' => ['Blanco Hueso', 'Off White'],
        '#1A1A1A' => ['Negro Azabache', 'Jet Black'],
        '#4A4A4A' => ['Gris Humo', 'Smoke Grey'],
    ];

    /**
     * El nombre del color más cercano del catálogo.
     *
     * @param  string  $locale  'es' o 'en'
     */
    public static function nearest(string $hex, string $locale = 'en'): string
    {
        [$r, $g, $b] = self::rgb($hex);

        $mejor = null;
        $menorDistancia = PHP_INT_MAX;

        foreach (self::CATALOG as $candidato => $nombres) {
            [$cr, $cg, $cb] = self::rgb($candidato);

            // Al cuadrado y sin raíz: para comparar da lo mismo y ahorra la
            // raíz en cada vuelta.
            $distancia = ($r - $cr) ** 2 + ($g - $cg) ** 2 + ($b - $cb) ** 2;

            if ($distancia < $menorDistancia) {
                $menorDistancia = $distancia;
                $mejor = $nombres;
            }
        }

        return $mejor === null ? '' : ($locale === 'es' ? $mejor[0] : $mejor[1]);
    }

    /**
     * El hex del catálogo más cercano —no el hex exacto que se le pasó—,
     * para agrupar por "cajón" de color. Lo usa FindOrCreateColorProp: con
     * ~26 colores fijos, cachear por el más cercano evita generar de nuevo
     * una foto decorativa por cada tono ligeramente distinto de rosa.
     */
    public static function nearestKey(string $hex): string
    {
        [$r, $g, $b] = self::rgb($hex);

        $mejor = null;
        $menorDistancia = PHP_INT_MAX;

        foreach (array_keys(self::CATALOG) as $candidato) {
            [$cr, $cg, $cb] = self::rgb($candidato);
            $distancia = ($r - $cr) ** 2 + ($g - $cg) ** 2 + ($b - $cb) ** 2;

            if ($distancia < $menorDistancia) {
                $menorDistancia = $distancia;
                $mejor = $candidato;
            }
        }

        return $mejor ?? $hex;
    }

    /**
     * ¿Son dos colores lo bastante distintos como para no ser el mismo
     * trabajo?
     *
     * Red de seguridad para "Fondo de color": si a pesar del selector ella
     * junta fotos de colores bien distintos, mejor avisar que rotular mal.
     * El umbral es generoso a propósito —dos tonos rosados con distinta luz
     * no deben disparar la alarma— y solo agarra choques claros, como rosa
     * pálido contra negro.
     */
    public static function farApart(string $a, string $b): bool
    {
        [$ar, $ag, $ab] = self::rgb($a);
        [$br, $bg, $bb] = self::rgb($b);

        $distancia = ($ar - $br) ** 2 + ($ag - $bg) ** 2 + ($ab - $bb) ** 2;

        return $distancia > 14000;
    }

    /**
     * El mismo color, aclarado hacia el blanco. Para pintar un fondo: con el
     * tono puro, el texto y las fotos encima se pierden.
     */
    public static function tint(string $hex, float $amount): string
    {
        [$r, $g, $b] = self::rgb($hex);

        return sprintf(
            '#%02X%02X%02X',
            (int) round($r + (255 - $r) * $amount),
            (int) round($g + (255 - $g) * $amount),
            (int) round($b + (255 - $b) * $amount),
        );
    }

    /** El mismo color, oscurecido hacia el negro. Para la tinta del texto. */
    public static function shade(string $hex, float $amount): string
    {
        [$r, $g, $b] = self::rgb($hex);

        return sprintf(
            '#%02X%02X%02X',
            (int) round($r * (1 - $amount)),
            (int) round($g * (1 - $amount)),
            (int) round($b * (1 - $amount)),
        );
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) !== 6) {
            return [0, 0, 0];
        }

        return array_map(static fn (string $c): int => (int) hexdec($c), str_split($hex, 2));
    }
}
