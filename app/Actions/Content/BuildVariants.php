<?php

namespace App\Actions\Content;

use App\Models\ContentAsset;
use App\Models\ContentUpload;
use App\Models\Provider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

/**
 * Arma varias opciones del mismo trabajo para que ella elija mirando.
 *
 * **Es el corazón del taller, y sale de una idea de ella.** Antes había que
 * elegir un modelo sin ver qué iba a salir, después elegir referencia,
 * después confirmar el color, después los stickers. Cuatro decisiones a
 * ciegas antes de ver una sola imagen. Ella lo dijo así: «la persona sube la
 * imagen y ya de una vez ofrece varias opciones, mirá cuál te gusta, esta
 * no, esta sí, y vamos así seleccionando».
 *
 * Tiene razón y es más barato de lo que parece: componer una imagen es
 * trabajo local, no cuesta llamadas. La descripción se escribe UNA vez y
 * vale para la que elija, así que seis opciones cuestan lo mismo que una.
 *
 * Lo que NO hace: inventar. Cada opción usa solo lo que se sabe cierto — la
 * técnica leída de la foto, el color confirmado, las frases que no afirman
 * un momento del trabajo. Ver PhraseSafety.
 */
class BuildVariants
{
    private const CANVAS = 1080;

    /** Cuántas ofrecer. Seis entra en dos filas de tres sin desplazar. */
    public const HOW_MANY = 6;

    public function __construct(
        private readonly StampAsset $stamp,
        private readonly PickAsset $pick,
        private readonly BuildCollage $collage,
        private readonly BuildColorBlock $colorBlock,
    ) {}

    /**
     * @param  Collection<int, ContentUpload>  $photos
     * @return list<array{key: string, kind: string}>
     */
    public function handle(Provider $provider, Collection $photos, ?string $technique = null): array
    {
        if ($photos->isEmpty()) {
            return [];
        }

        $frases = $this->pick->phrases($provider, $technique)
            // Una por texto: seis veces "agenda abierta" en distinta letra no
            // son seis opciones, son la misma repetida.
            ->groupBy(fn (ContentAsset $a): string => $a->slug ?? $a->path)
            ->shuffle();

        $variantes = [];
        $paths = $photos->pluck('path')->all();

        foreach ($frases as $grupo) {
            if (count($variantes) >= self::HOW_MANY) {
                break;
            }

            // Se reparten las fotos entre las opciones: si subió cuatro, no
            // salen seis veces la misma.
            $foto = $paths[count($variantes) % count($paths)];
            $key = $this->stamped($provider, $foto, $grupo);

            if ($key !== null) {
                $variantes[] = ['key' => $key, 'kind' => 'hero'];
            }
        }

        // Con varias fotos entran también los armados que las usan todas.
        if ($photos->count() >= 2 && count($variantes) < self::HOW_MANY) {
            $variantes[] = ['key' => $this->collage->handle($provider, $paths, []), 'kind' => 'collage'];
        }

        $conColor = $photos->first(fn (ContentUpload $u): bool => $u->color_hex !== null);

        if ($conColor !== null && $photos->count() >= 2 && count($variantes) < self::HOW_MANY) {
            $variantes[] = [
                'key' => $this->colorBlock->handle($provider, $paths, (string) $conColor->color_name, (string) $conColor->color_hex),
                'kind' => 'color',
            ];
        }

        return $variantes;
    }

    /**
     * Una foto con una frase encima, elegida la tinta que contrasta.
     *
     * @param  Collection<int, ContentAsset>  $grupo  las versiones de la misma frase
     */
    private function stamped(Provider $provider, string $photoPath, Collection $grupo): ?string
    {
        try {
            $canvas = ImageManager::imagick()
                ->read(Storage::disk('r2')->get($photoPath))
                ->cover(self::CANVAS, self::CANVAS);
        } catch (\Throwable) {
            return null;
        }

        $elegida = $this->stamp->pick($canvas, $grupo);

        if ($elegida === null) {
            return null;
        }

        // Con una foto de brillo intermedio ninguna de las dos tintas se
        // despega: ahí, y solo ahí, se apoya una sombra neutra debajo.
        $brillo = $this->stamp->brightness($canvas, $elegida);

        if ($brillo > 0.40 && $brillo < 0.66) {
            $sombra = $this->pick->ofKind('sombra')->where('ink', 'dark');

            if ($sombra->isNotEmpty()) {
                $this->stamp->wash($canvas, $sombra->random(), $elegida);
            }
        }

        $this->stamp->handle($canvas, $elegida);

        // Van a una carpeta aparte: son borradores hasta que ella elija uno.
        $key = sprintf('providers/%d/variants/%s.jpg', $provider->id, (string) Str::ulid());

        Storage::disk('r2')->put($key, (string) $canvas->toJpeg(quality: 88), [
            'ContentType' => 'image/jpeg',
            'CacheControl' => 'public, max-age=31536000, immutable',
        ]);

        return $key;
    }
}
