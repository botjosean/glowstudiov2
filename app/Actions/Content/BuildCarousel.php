<?php

namespace App\Actions\Content;

use App\Models\Provider;

/**
 * Reparte las fotos en las láminas de un carrusel.
 *
 * **Ésta es la pieza que faltaba.** Diez fotos no son un collage de diez
 * cuadraditos donde no se distingue nada: son una portada con titular, uno o
 * dos collages en el medio y un cierre que invita a agendar. Ella lo dijo
 * así: «no es que si sube 10 fotos arme algo de 10 fotos, la idea es armar
 * collage, armar el carrusel, armar todo ese tipo de cosas», y sus propias
 * referencias son carruseles de tres láminas (1/3, 2/3, 3/3).
 *
 * La receta es fija a propósito. Dejar que el modelo invente una estructura
 * distinta cada vez da resultados dispares y ella termina rechazándolos; lo
 * que cambia entre un post y otro son las fotos y el texto, no el armado.
 */
class BuildCarousel
{
    /** Instagram no acepta más de diez. */
    private const MAX_SLIDES = 10;

    /** Cuántas fotos entran en cada collage del medio. */
    private const PER_COLLAGE = 4;

    public function __construct(
        private readonly BuildHero $hero,
        private readonly BuildCollage $collage,
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

        $slides = [];

        // Portada: la primera foto a pantalla completa con el titular. Es la
        // única que se ve en el muro, así que se lleva la foto de entrada.
        $slides[] = $this->hero->handle($provider, [$paths[0]], $headline);

        // El medio: el resto repartido en collages de a cuatro. Se salta la
        // portada para no repetirla de inmediato.
        $rest = array_slice($paths, 1);

        foreach (array_chunk($rest, self::PER_COLLAGE) as $chunk) {
            if (count($slides) >= self::MAX_SLIDES - 1) {
                break;
            }

            // Un collage de una sola foto no es un collage: va como lámina
            // entera, sin titular encima para no tapar dos veces lo mismo.
            $slides[] = count($chunk) === 1
                ? $this->hero->handle($provider, $chunk, [])
                : $this->collage->handle($provider, $chunk, []);
        }

        // Cierre: vuelve la foto de portada con la invitación. Repetirla es lo
        // normal en un carrusel — cierra por donde abrió.
        if (count($slides) < self::MAX_SLIDES) {
            $slides[] = $this->hero->handle($provider, [$paths[0]], ['AGENDA', 'TU CITA']);
        }

        return $slides;
    }
}
