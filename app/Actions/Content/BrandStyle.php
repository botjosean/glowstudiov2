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
