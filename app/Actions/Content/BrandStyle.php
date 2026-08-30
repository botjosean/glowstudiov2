<?php

namespace App\Actions\Content;

use App\Enums\BusinessCategory;

/**
 * Cómo se ve el cartel según a qué se dedica ella.
 *
 * Un salón de uñas y una barbería no se anuncian igual, y hasta ahora todo
 * salía con la misma paleta rosa y negra. Ella lo pidió así: «que detecte
 * quién es el cliente, que hay barbería, que hay uñas, para que el contenido
 * tenga más contexto».
 *
 * Las paletas salen de lo que de verdad usa cada rubro: las uñas van con
 * neón y contraste alto, la barbería con negro y dorado, el cabello con
 * tonos tierra y serif. No es decoración — un post de barbería en rosa
 * fluorescente no lo publica nadie.
 */
class BrandStyle
{
    /**
     * Los colores de los bloques del titular, en orden de aparición.
     *
     * @return list<string>
     */
    public static function blockColors(?BusinessCategory $category): array
    {
        return match ($category) {
            BusinessCategory::Nails => ['#111827', '#e11d63', '#7c3aed'],
            BusinessCategory::Barbershop => ['#0b0f19', '#b3852f', '#0b0f19'],
            BusinessCategory::Hair => ['#1c1917', '#a16207', '#1c1917'],
            BusinessCategory::LashesBrows => ['#1f1720', '#be185d', '#1f1720'],
            BusinessCategory::Braids => ['#111827', '#c2410c', '#111827'],
            BusinessCategory::Makeup => ['#18111b', '#db2777', '#18111b'],
            BusinessCategory::SpaMassage, BusinessCategory::Aesthetics => ['#14231f', '#0f766e', '#14231f'],
            BusinessCategory::Waxing => ['#1a1420', '#9333ea', '#1a1420'],
            BusinessCategory::TattooPiercing => ['#0a0a0a', '#dc2626', '#0a0a0a'],
            default => ['#111827', '#e11d63', '#111827'],
        };
    }

    /**
     * Con qué tipografía va el titular.
     *
     * Los oficios de golpe —uñas, trenzas, tatuajes— piden la condensada
     * gruesa; los que se venden por elegancia —cabello, spa, estética— piden
     * la serif. Es la misma diferencia que se ve entre las dos referencias
     * que ella guardó.
     */
    public static function headlineFont(?BusinessCategory $category): string
    {
        $elegant = [
            BusinessCategory::Hair,
            BusinessCategory::SpaMassage,
            BusinessCategory::Aesthetics,
            BusinessCategory::Makeup,
        ];

        return in_array($category, $elegant, true)
            ? resource_path('fonts/Playfair.ttf')
            : resource_path('fonts/Anton.ttf');
    }

    /**
     * El pie del post: dónde queda y cómo la llaman.
     *
     * Sale de sus propias referencias, que llevan "PLAZA FIESTA · ATLANTA ·
     * 404-451-8022" abajo. Es lo que convierte una foto bonita en un anuncio:
     * sin esto, quien la ve no sabe adónde ir.
     *
     * Devuelve cadena vacía cuando no hay nada que poner — un pie con un
     * separador suelto se ve peor que ninguno.
     */
    public static function contactLine(?string $address, ?string $phone): string
    {
        $partes = array_values(array_filter([
            self::shortAddress($address),
            self::prettyPhone($phone),
        ], static fn (?string $p): bool => $p !== null && $p !== ''));

        return implode('  ·  ', $partes);
    }

    /**
     * La dirección recortada a lo que cabe: calle y ciudad, sin el código
     * postal. Una dirección entera no entra a lo ancho del cuadrado.
     */
    private static function shortAddress(?string $address): ?string
    {
        if ($address === null || trim($address) === '') {
            return null;
        }

        $partes = array_map('trim', explode(',', $address));
        // Se descartan los trozos que son solo números: el código postal.
        $partes = array_values(array_filter($partes, static fn (string $p): bool => $p !== '' && preg_match('/^\d+$/', $p) !== 1));

        return mb_strtoupper(implode(', ', array_slice($partes, 0, 2)));
    }

    /** Los diez dígitos como los lee alguien: (470) 886-7197. */
    private static function prettyPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone) ?? '';

        if (strlen($digits) !== 10) {
            return $digits === '' ? null : $digits;
        }

        return sprintf('(%s) %s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6));
    }

    /**
     * El color de acento del rubro: el que lleva la última línea del titular
     * cuando toca destacarla.
     *
     * Es el segundo de la paleta, que es justamente el que rompe con el
     * oscuro de las otras dos.
     */
    public static function accent(?BusinessCategory $category): string
    {
        return self::blockColors($category)[1];
    }

    /**
     * El antetítulo: la línea chica en mayúsculas que va encima del titular.
     *
     * Sale de una referencia suya —«TRANSFORMACIÓN REAL · SIN FILTROS» sobre
     * un balayage— y funciona porque promete algo antes de que la clienta lea
     * el titular. Se elige según el rubro para que no diga una cosa de
     * cabello sobre unas uñas.
     *
     * @return list<string>
     */
    public static function eyebrows(?BusinessCategory $category): array
    {
        return match ($category) {
            BusinessCategory::Nails => [
                'TRABAJO REAL · SIN FILTROS', 'HECHO A MANO', 'DISEÑO A MEDIDA',
                'ESMALTE QUE DURA', 'SET NUEVO', 'DETALLE POR DETALLE',
            ],
            BusinessCategory::Barbershop => [
                'CORTE A NAVAJA', 'SIN CITA PERDIDA', 'ESTILO PROPIO',
                'DEGRADADO LIMPIO', 'TOALLA CALIENTE', 'PRECISIÓN',
            ],
            BusinessCategory::Hair => [
                'TRANSFORMACIÓN REAL', 'COLOR A MEDIDA', 'SIN FILTROS',
                'CABELLO SANO', 'DE RAÍZ A PUNTAS', 'LUZ NATURAL',
            ],
            BusinessCategory::LashesBrows => [
                'MIRADA A MEDIDA', 'TRABAJO REAL', 'PELO POR PELO',
                'SIN MAQUILLAJE', 'DISEÑO PARA TU ROSTRO',
            ],
            BusinessCategory::Braids => [
                'HECHO A MANO', 'HORAS DE TRABAJO', 'RAÍZ PROTEGIDA',
                'DURA SEMANAS', 'TRENZA POR TRENZA',
            ],
            BusinessCategory::Makeup => [
                'PARA TU DÍA', 'SIN FILTROS', 'A PRUEBA DE FOTOS',
                'PIEL PRIMERO', 'DURA TODA LA NOCHE',
            ],
            BusinessCategory::SpaMassage => [
                'TU MOMENTO', 'RESULTADOS REALES', 'UNA HORA PARA VOS',
                'SIN PRISA', 'CUERPO Y CABEZA',
            ],
            BusinessCategory::Aesthetics => [
                'RESULTADOS REALES', 'PIEL PRIMERO', 'PROTOCOLO A MEDIDA',
                'SIN RETOQUE DIGITAL', 'CONSTANCIA',
            ],
            BusinessCategory::Waxing => [
                'PIEL LISA', 'SIN DOLOR INNECESARIO', 'TRABAJO PROLIJO',
                'DURA SEMANAS',
            ],
            BusinessCategory::TattooPiercing => [
                'TINTA PROPIA', 'DISEÑO ÚNICO', 'A UNA SOLA SESIÓN',
                'MATERIAL ESTÉRIL', 'HECHO A MANO',
            ],
            default => ['TRABAJO REAL · SIN FILTROS', 'HECHO A MANO', 'DETALLE POR DETALLE'],
        };
    }

    /**
     * Los armados que le pegan a este rubro, de entre los que caben con esa
     * cantidad de fotos.
     *
     * Cada oficio muestra distinto: en cabello y estética manda el antes y
     * después, en uñas se muestran muchos diseños a la vez, y en barbería se
     * destaca un corte. Sin esto, un salón de uñas y un spa recibían
     * exactamente el mismo reparto.
     *
     * Siempre queda al menos un armado: si el rubro no tiene preferencia
     * disponible, se usan todos los que caben.
     *
     * @param  list<string>  $available
     * @return list<string>
     */
    public static function preferredArrangements(?BusinessCategory $category, array $available): array
    {
        $preferred = match ($category) {
            // Muchos diseños de una: la rejilla y la revista lucen el catálogo.
            BusinessCategory::Nails, BusinessCategory::Braids => ['even', 'magazine', 'center-stage', 'banner-top'],
            // Un corte destacado, no un muestrario.
            BusinessCategory::Barbershop => ['feature-left', 'feature-right', 'before-after', 'banner-top'],
            // El resultado manda; el antes y después es el formato del rubro.
            BusinessCategory::Hair, BusinessCategory::Aesthetics => ['before-after', 'feature-top', 'feature-left', 'banner-top'],
            BusinessCategory::LashesBrows, BusinessCategory::Waxing => ['before-after', 'feature-left', 'even'],
            // Aire y una imagen grande, no densidad.
            BusinessCategory::SpaMassage => ['feature-top', 'banner-top', 'banner-bottom'],
            BusinessCategory::Makeup => ['feature-left', 'even', 'magazine'],
            BusinessCategory::TattooPiercing => ['feature-left', 'center-stage', 'even'],
            default => [],
        };

        $fits = array_values(array_intersect($preferred, $available));

        return $fits === [] ? $available : $fits;
    }

    /**
     * Los estilos de titular que le pegan a este rubro.
     *
     * La barbería y los tatuajes no usan bloques de color de revista: van con
     * franja o con texto limpio. Restringirlo por rubro evita justo el post
     * que ella no publicaría.
     *
     * @return list<string>
     */
    public static function headlineStyles(?BusinessCategory $category): array
    {
        return match ($category) {
            BusinessCategory::Barbershop, BusinessCategory::TattooPiercing => ['band', 'clean'],
            BusinessCategory::Hair, BusinessCategory::SpaMassage, BusinessCategory::Aesthetics => ['clean', 'band'],
            default => ['blocks', 'band', 'clean'],
        };
    }
}
