<?php

namespace App\Actions\Content;

use App\Enums\PostLayout;
use App\Models\Provider;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;

/**
 * Arma el collage 4-en-1 a partir de las fotos que ella subió.
 *
 * **Regla que manda sobre todo lo demás: el trabajo de ella no se toca.**
 * En belleza la foto real ES el producto — si se le retoca una uña o el
 * color del cabello, la clienta llega esperando algo que no existe. Acá solo
 * se recorta y se encuadra; ningún filtro, ninguna corrección de color,
 * ninguna IA sobre el contenido de la foto.
 *
 * **El diseño sale de sus propias referencias, no de mi gusto.** La primera
 * versión era tímida: cuatro fotos flotando en un marco crema ancho con su
 * nombre chiquito abajo. Al mirar lo que ella misma guardó como referencia
 * —collages de uñas con "Trending / Nails / Verano" en bloques de color
 * encima— quedó claro qué faltaba, y ella lo dijo con estas palabras: «le
 * falta impacto, más texto, que se lea que se está haciendo una oferta».
 * De ahí salen las tres decisiones de acá:
 *
 *  1. Fotos a sangre, pegadas al borde, con una junta blanca fina. El marco
 *     ancho le quitaba tamaño justo a lo que se quiere mostrar.
 *  2. Un titular corto EN BLOQUES sobre el centro, cada línea con su propio
 *     fondo. Es lo que hace que el post se lea de un scroll.
 *  3. Anton para el titular: la Manrope que ya se incluye es variable y
 *     FreeType la dibuja siempre en su peso fino — imposible para esto.
 *
 * Todo pasa en el servidor con Imagick, que ya estaba instalado para las
 * fotos de perfil. No hace falta ningún servicio externo ni clave nueva.
 */
class BuildCollage
{
    /** Cuadrado de 1080: lo que Instagram sirve sin recomprimir de más. */
    private const CANVAS = 1080;

    /** La junta blanca entre fotos. Fina: separa sin robar imagen. */
    private const SEAM = 8;

    /** Alternan para que el titular no sea un bloque plano de un solo color. */
    private const BLOCK_COLORS = ['#111827', '#e11d63', '#111827'];

    /**
     * @param  list<string>  $paths  claves de R2 de las fotos, en orden
     * @param  list<string>  $headline  hasta 3 palabras/líneas para el titular
     * @return string la clave de R2 del collage generado
     */
    public function handle(Provider $provider, array $paths, array $headline = []): string
    {
        $manager = ImageManager::imagick();
        $paths = array_values($paths);

        $canvas = $manager->create(self::CANVAS, self::CANVAS)->fill('#ffffff');

        // Filas de distinto largo para no dejar huecos ni perder fotos: cada
        // fila se reparte el ancho entero entre las suyas. Ver rowSizes().
        $rowSizes = PostLayout::rowSizes(count($paths));
        $rows = count($rowSizes);
        $cellH = (int) ((self::CANVAS - (self::SEAM * ($rows - 1))) / $rows);

        $index = 0;

        foreach ($rowSizes as $row => $inRow) {
            $cellW = (int) ((self::CANVAS - (self::SEAM * ($inRow - 1))) / $inRow);
            $y = $row * ($cellH + self::SEAM);

            for ($col = 0; $col < $inRow; $col++) {
                $photo = $manager->read(Storage::disk('r2')->get($paths[$index]))->cover($cellW, $cellH);
                $canvas->place($photo, 'top-left', $col * ($cellW + self::SEAM), $y);
                $index++;
            }
        }

        $this->headline($canvas, $headline);
        $this->badge($canvas, $provider->public_name ?? Provider::DEFAULT_BUSINESS_NAME);

        $key = sprintf('providers/%d/content/%s.jpg', $provider->id, (string) Str::ulid());

        Storage::disk('r2')->put($key, (string) $canvas->toJpeg(quality: 90), [
            'ContentType' => 'image/jpeg',
            'CacheControl' => 'public, max-age=31536000, immutable',
        ]);

        return $key;
    }

    /**
     * El titular, en bloques apilados sobre el centro.
     *
     * El ancho de cada bloque se mide con el texto ya renderizado en vez de
     * estimarlo con un factor por carácter: en Anton una "i" y una "M" no se
     * parecen en nada, y calcularlo a ojo dejaba el fondo corto en unas
     * líneas y larguísimo en otras.
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

        $size = 96;
        $lineHeight = 118;
        $padX = 26;
        $padY = 12;

        $blockHeight = count($lines) * $lineHeight;
        $top = (int) round((self::CANVAS - $blockHeight) / 2);

        foreach ($lines as $i => $line) {
            $centerY = $top + ($i * $lineHeight) + (int) round($lineHeight / 2);
            $width = $this->textWidth($line, $size);

            // El fondo primero, el texto encima.
            $canvas->drawRectangle(
                (int) round((self::CANVAS - $width) / 2) - $padX,
                $centerY - (int) round($size / 2) - $padY,
                function ($rect) use ($width, $size, $padX, $padY, $i): void {
                    $rect->size($width + ($padX * 2), $size + ($padY * 2));
                    $rect->background(self::BLOCK_COLORS[$i % count(self::BLOCK_COLORS)]);
                },
            );

            $canvas->text($line, (int) round(self::CANVAS / 2), $centerY, function (FontFactory $font) use ($size): void {
                $font->filename(resource_path('fonts/Anton.ttf'));
                $font->size($size);
                $font->color('#ffffff');
                $font->align('center');
                $font->valign('middle');
            });
        }
    }

    /**
     * Cuánto mide de ancho ese texto, medido de verdad: se dibuja en un
     * lienzo aparte y se pregunta por su caja.
     */
    private function textWidth(string $text, int $size): int
    {
        $draw = new \ImagickDraw;
        $draw->setFont(resource_path('fonts/Anton.ttf'));
        $draw->setFontSize($size);

        $metrics = (new \Imagick)->queryFontMetrics($draw, $text);

        return (int) round($metrics['textWidth']);
    }

    /**
     * El sello redondo abajo, como el de sus referencias: un círculo blanco
     * con su nombre adentro. Reemplaza al nombre suelto de la primera
     * versión, que se perdía contra las fotos.
     */
    private function badge(ImageInterface $canvas, string $name): void
    {
        $radius = 78;
        $centerX = (int) round(self::CANVAS / 2);
        $centerY = self::CANVAS - $radius - 38;

        $canvas->drawCircle($centerX, $centerY, function ($circle) use ($radius): void {
            $circle->radius($radius);
            $circle->background('#ffffff');
        });

        // Dos líneas si el nombre tiene apellido: en un círculo, una sola
        // línea larga se sale por los lados.
        $parts = preg_split('/\s+/u', trim($name)) ?: [$name];
        $first = Str::upper($parts[0]);
        $rest = count($parts) > 1 ? Str::upper(implode(' ', array_slice($parts, 1))) : '';

        $canvas->text($first, $centerX, $centerY - ($rest === '' ? 0 : 14), function (FontFactory $font): void {
            $font->filename(resource_path('fonts/Manrope.ttf'));
            $font->size(26);
            $font->color('#8a6a25');
            $font->align('center');
            $font->valign('middle');
        });

        if ($rest !== '') {
            $canvas->text($this->spaced($rest), $centerX, $centerY + 19, function (FontFactory $font): void {
                $font->filename(resource_path('fonts/Manrope.ttf'));
                $font->size(13);
                $font->color('#b3852f');
                $font->align('center');
                $font->valign('middle');
            });
        }
    }

    /**
     * Espaciado a mano con espacios finos: Intervention no expone
     * letter-spacing, y sin él una palabra corta en mayúsculas se ve apretada.
     */
    private function spaced(string $text): string
    {
        return implode("\u{2009}", mb_str_split($text));
    }
}
