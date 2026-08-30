<?php

namespace App\Actions\Content;

use App\Models\Provider;

/**
 * Reparte las fotos en las láminas de un carrusel.
 *
 * **La receta: portada con frase, y después el trabajo solo.** Es la forma
 * que se repite en todos los carruseles de salón que ella trajo como
 * referencia, y lo dijo en una línea mirando uno: «una portada con letras
 * bonitas, buena frase, seguido de fotos».
 *
 * La primera versión metía collages de cuatro fotitos en el medio, cada uno
 * con su pie de contacto encima. A esa altura del carrusel eso solo tapa lo
 * que se vino a ver: quien ya deslizó hasta la lámina 3 no necesita que le
 * repitan el teléfono, necesita ver las uñas.
 *
 * La receta es fija a propósito. Dejar que el modelo invente una estructura
 * distinta cada vez da resultados dispares y ella termina rechazándolos; lo
 * que cambia entre un post y otro son las fotos y el texto, no el armado.
 */
class BuildCarousel
{
    /** Instagram no acepta más de diez. */
    private const MAX_SLIDES = 10;

    public function __construct(
        private readonly BuildHero $hero,
    ) {}

    /**
     * @param  list<string>  $paths
     * @param  list<string>  $headline
     * @return list<string> las claves de R2 de cada lámina, en orden
     */
    public function handle(Provider $provider, array $paths, array $headline = []): array
    {
        $paths = array_values($paths);

        // Una sola foto no es un carrusel: es una portada y ya.
        if (count($paths) === 1) {
            return [$this->hero->handle($provider, $paths, $headline)];
        }

        // Portada: la primera foto a pantalla completa con la frase. Es la
        // única que se ve en el muro, así que se lleva la foto de entrada.
        $slides = [$this->hero->handle($provider, [$paths[0]], $headline)];

        // Y después, el trabajo solo. Sin texto, sin sello, sin pie: la
        // portada ya dijo lo que había que decir y a partir de ahí lo único
        // que importa son las uñas o el cabello.
        //
        // Antes acá iban collages de cuatro fotitos con su propio pie de
        // contacto encima, y a esa altura del carrusel eso solo tapa el
        // trabajo. Ella lo mostró con un ejemplo y lo dijo en una línea:
        // «una portada con letras bonitas, buena frase, seguido de fotos».
        foreach (array_slice($paths, 1) as $path) {
            if (count($slides) >= self::MAX_SLIDES) {
                break;
            }

            $slides[] = $this->hero->plain($provider, $path);
        }

        return $slides;
    }
}
