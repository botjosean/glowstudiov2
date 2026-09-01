<?php

namespace App\Actions\Content;

use App\Models\ContentAsset;
use App\Models\Provider;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Elige qué pieza del pack le toca a este post.
 *
 * Vive aparte de StampAsset a propósito: una cosa es decidir CUÁL —que
 * depende del rubro, del servicio y de lo que ya se usó— y otra es pintarla,
 * que depende de la foto. Mezclarlas hacía imposible probar la elección sin
 * generar una imagen entera.
 */
class PickAsset
{
    /**
     * Las frases que le sirven a esta proveedora, con la del servicio que de
     * verdad hizo primero si la hay.
     *
     * Es lo que vuelve al pack algo más que decoración: la app ya sabe que
     * el trabajo fue de acrílicas, y el pack trae "Acrílicas" escrito por un
     * diseñador. Estampar la que corresponde no es azar, es decir la verdad
     * con letra linda.
     *
     * @return Collection<int, ContentAsset>
     */
    public function phrases(Provider $provider, ?string $serviceSlug = null): Collection
    {
        $trade = $provider->business_category?->value;

        $slug = $serviceSlug === null ? null : Str::slug($serviceSlug);

        if ($slug !== null && $slug !== '') {
            // Primero la coincidencia exacta y, si no hay, la que empieza
            // igual: el modelo puede decir "acrílicas" y la pieza llamarse
            // "acrilicas rosas". Lo que NO se hace es buscar por dentro
            // ("like %x%"), porque "gel" encontraría "builder gel" y
            // "softgel" y estampar la técnica equivocada es peor que
            // estampar una frase general.
            $delServicio = ContentAsset::query()
                ->where('kind', 'frase')
                ->forTrade($trade)
                ->where(fn ($q) => $q->where('slug', $slug)->orWhere('slug', 'like', $slug.'-%'))
                ->get();

            // Las rotas del pack quedan fuera también acá: "Traicional" está
            // mal escrita y no se estampa nunca.
            $delServicio = $delServicio->filter(fn (ContentAsset $a): bool => PhraseSafety::autoSafe($a->slug));

            if ($delServicio->isNotEmpty()) {
                return $delServicio->values();
            }
        }

        // Sin técnica conocida quedan las generales, pero SOLO las que no
        // afirman un momento del trabajo. Ver PhraseSafety: el sistema no
        // sabe si la foto es el antes, el proceso o el resultado, y
        // adivinarlo produce posts que se contradicen.
        return ContentAsset::query()
            ->where('kind', 'frase')
            ->forTrade($trade)
            ->get()
            ->filter(fn (ContentAsset $a): bool => PhraseSafety::autoSafe($a->slug))
            ->values();
    }

    /**
     * Un elemento ilustrado del rubro — un esmalte, un secador, una pinza.
     *
     * Reemplaza a la foto decorativa que se generaba con IA para los rubros
     * que el pack cubre: acá ya está dibujada, es de ella, y no cuesta nada.
     * Ver GeneratePropImage, que sigue existiendo para los rubros sin pack.
     *
     * @return Collection<int, ContentAsset>
     */
    public function elements(Provider $provider): Collection
    {
        return ContentAsset::query()
            ->where('kind', 'elemento')
            ->forTrade($provider->business_category?->value)
            ->get();
    }

    /**
     * @return Collection<int, ContentAsset>
     */
    public function ofKind(string $kind): Collection
    {
        return ContentAsset::query()->where('kind', $kind)->get();
    }

    /** ¿Hay pack cargado para este rubro? Si no, cada plantilla sigue como antes. */
    public function hasPack(Provider $provider): bool
    {
        return ContentAsset::query()
            ->where('kind', 'frase')
            ->forTrade($provider->business_category?->value)
            ->exists();
    }
}
