<?php

namespace App\Actions\Content;

use App\Models\Provider;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;

/**
 * Una sola foto a pantalla completa con el titular abajo, estilo revista.
 *
 * Copiado de una referencia que ella misma guardó: foto a sangre, la marca
 * arriba al centro en versalitas doradas, y abajo un antetítulo chico en
 * mayúsculas sobre un titular grande. El degradado oscuro de abajo no es
 * decoración — sin él, el texto blanco desaparece sobre una foto clara, que
 * en belleza es la mitad de las fotos.
 *
 * **El trabajo de ella no se toca**: la foto solo se recorta al cuadrado.
 * Ningún filtro ni corrección de color. Lo único que se dibuja encima es el
 * degradado y el texto.
 */
class BuildHero
{
    private const CANVAS = 1080;

    /** Alto del degradado, desde abajo. */
    private const SHADE = 560;

    /**
     * @param  list<string>  $paths  se usa la primera
     * @param  list<string>  $headline  hasta 3 líneas
     */
    public function handle(Provider $provider, array $paths, array $headline = []): string
    {
        $manager = ImageManager::imagick();

        $canvas = $manager->read(Storage::disk('r2')->get(array_values($paths)[0]))
            ->cover(self::CANVAS, self::CANVAS);

        $this->shade($canvas);
        $this->wordmark($canvas, $provider->public_name ?? Provider::DEFAULT_BUSINESS_NAME);
        $this->headline($canvas, $headline);

        $key = sprintf('providers/%d/content/%s.jpg', $provider->id, (string) Str::ulid());

        Storage::disk('r2')->put($key, (string) $canvas->toJpeg(quality: 90), [
            'ContentType' => 'image/jpeg',
            'CacheControl' => 'public, max-age=31536000, immutable',
        ]);

        return $key;
    }

    /**
     * El degradado de abajo, dibujado como tiras de opacidad creciente.
     *
     * Intervention no expone degradados, así que se hace a mano. Cuarenta
     * tiras: menos se ven como escalones sobre un fondo liso, más no aporta
     * nada visible y solo cuesta tiempo.
     */
    private function shade(ImageInterface $canvas): void
    {
        $bands = 40;
        $height = (int) ceil(self::SHADE / $bands);

        for ($i = 0; $i < $bands; $i++) {
            $y = self::CANVAS - self::SHADE + ($i * $height);
            // Curva cuadrática: arranca casi transparente y se cierra rápido
            // abajo, que es donde va el texto.
            $alpha = ($i / $bands) ** 2 * 0.88;

            $canvas->drawRectangle(0, $y, function ($rect) use ($height, $alpha): void {
                $rect->size(self::CANVAS, $height + 1);
                $rect->background(sprintf('rgba(8, 10, 16, %.3f)', $alpha));
            });
        }
    }

    /** La marca arriba al centro, chica y espaciada. */
    private function wordmark(ImageInterface $canvas, string $name): void
    {
        $canvas->text($this->spaced(Str::upper($name)), (int) round(self::CANVAS / 2), 62, function (FontFactory $font): void {
            $font->filename(resource_path('fonts/Manrope.ttf'));
            $font->size(19);
            $font->color('#e8c877');
            $font->align('center');
            $font->valign('middle');
        });
    }

    /**
     * El antetítulo y el titular, alineados a la izquierda sobre el degradado.
     *
     * @param  list<string>  $lines
     */
    private function headline(ImageInterface $canvas, array $lines): void
    {
        $lines = array_values(array_filter(array_map(
            static fn (string $line): string => Str::upper(trim($line)),
            array_slice($lines, 0, 3),
        )));

        if ($lines === []) {
            return;
        }

        $size = 82;
        $lineHeight = 96;
        $left = 74;
        $bottom = self::CANVAS - 96;

        $canvas->text('SU TRABAJO · SIN FILTROS', $left, $bottom - (count($lines) * $lineHeight) - 26, function (FontFactory $font): void {
            $font->filename(resource_path('fonts/Manrope.ttf'));
            $font->size(17);
            $font->color('#e8c877');
            $font->align('left');
            $font->valign('middle');
        });

        foreach ($lines as $i => $line) {
            $y = $bottom - ((count($lines) - 1 - $i) * $lineHeight);

            $canvas->text($line, $left, $y, function (FontFactory $font) use ($size): void {
                $font->filename(resource_path('fonts/Anton.ttf'));
                $font->size($size);
                $font->color('#ffffff');
                $font->align('left');
                $font->valign('middle');
            });
        }
    }

    private function spaced(string $text): string
    {
        return implode("\u{2009}", mb_str_split($text));
    }
}
