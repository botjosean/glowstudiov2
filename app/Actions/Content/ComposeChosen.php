<?php

namespace App\Actions\Content;

use App\Models\ContentAsset;
use App\Models\Provider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

/**
 * Arma el post con las piezas que eligió ELLA, en el orden en que las eligió.
 *
 * **Por qué existe, y es lo más importante de todo el taller.** El sistema
 * puede saber de qué color es el trabajo y qué técnica se ve, pero no puede
 * saber si la foto es el antes, el proceso o el resultado. Ella lo dijo
 * mirando posts reales: «tú no sabes cuándo la foto está terminada o cuándo
 * está empezando el proceso [...] al otro le pones proceso y de repente no,
 * ya eso es terminado». Ese dato no está en la foto y no lo va a estar.
 *
 * Así que acá la app deja de adivinar: muestra las piezas, ella toca las que
 * quiere, y esto las pega. Lo automático sigue existiendo para cuando tenga
 * apuro; esto es para cuando quiera que diga exactamente lo que ella sabe.
 *
 * **Las piezas van donde el diseñador las puso.** No se recortan ni se
 * mueven — ver StampAsset, que aprendió eso a los golpes.
 */
class ComposeChosen
{
    private const CANVAS = 1080;

    public function __construct(
        private readonly StampAsset $stamp,
    ) {}

    /**
     * @param  Collection<int, ContentAsset>  $assets  en orden de abajo hacia arriba
     * @return string la clave de R2 del post
     */
    public function handle(Provider $provider, string $photoPath, Collection $assets): string
    {
        $canvas = ImageManager::imagick()
            ->read(Storage::disk('r2')->get($photoPath))
            ->cover(self::CANVAS, self::CANVAS);

        // En el orden que ella tocó: la última queda arriba, que es lo que
        // se espera de cualquier editor.
        foreach ($assets as $asset) {
            $this->stamp->handle($canvas, $asset);
        }

        $key = sprintf('providers/%d/content/%s.jpg', $provider->id, (string) Str::ulid());

        Storage::disk('r2')->put($key, (string) $canvas->toJpeg(quality: 90), [
            'ContentType' => 'image/jpeg',
            'CacheControl' => 'public, max-age=31536000, immutable',
        ]);

        return $key;
    }
}
