<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Model;

/**
 * Una pieza del pack de contenido: una frase, un elemento, un marco, una
 * sombra o un adorno. Ver la migración `create_content_assets_table`.
 */
#[Fillable(['kind', 'trade', 'path', 'ink', 'text', 'slug', 'box_x', 'box_y', 'box_w', 'box_h', 'hue'])]
class ContentAsset extends Model
{
    /**
     * Las que sirven para este rubro: las suyas y las generales.
     *
     * Una manicurista no debería recibir un secador de peluquería, pero sí
     * un "Reserva tu cita", que no es de nadie en particular.
     */
    #[Scope]
    protected function forTrade(Builder $query, ?string $trade): void
    {
        $query->where(function (Builder $q) use ($trade): void {
            $q->whereNull('trade');

            if ($trade !== null) {
                $q->orWhere('trade', $trade);
            }
        });
    }

    /**
     * La tinta que se lee sobre un fondo de esta claridad.
     *
     * El pack trae cada frase en oscuro y en claro; elegir mal es la
     * diferencia entre un titular que se lee y uno que desaparece sobre
     * unas uñas blancas.
     */
    public static function inkFor(float $brightness): string
    {
        return $brightness > 0.55 ? 'dark' : 'light';
    }
}
