<?php

namespace App\Actions\Content;

use App\Models\Provider;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
 * Todo pasa en el servidor con Imagick, que ya estaba instalado para las
 * fotos de perfil. No hace falta ningún servicio externo ni clave nueva.
 */
class BuildCollage
{
    /** Cuadrado de 1080: lo que Instagram sirve sin recomprimir de más. */
    private const CANVAS = 1080;

    /**
     * Margen igual por los cuatro lados, y la firma vive dentro del margen
     * de abajo.
     *
     * Un margen inferior más grande que el resto (estilo Polaroid) queda
     * descartado por geometría, no por gusto: con una rejilla cuadrada
     * centrada en un lienzo cuadrado, el margen de abajo siempre termina
     * igual al de arriba. Forzarlo dejaba el borde superior en 15 px contra
     * 53 px a los lados, y eso se lee como un descuido en vez de un marco.
     */
    private const PAD = 64;

    private const GAP = 14;

    /** Lado de cada foto, deducido para que la rejilla llene el marco. */
    private const CELL = (self::CANVAS - (2 * self::PAD) - self::GAP) / 2;

    /**
     * @param  list<string>  $paths  claves de R2 de las 4 fotos, en orden
     * @return string la clave de R2 del collage generado
     */
    public function handle(Provider $provider, array $paths): string
    {
        $manager = ImageManager::imagick();

        // Crema muy suave en vez de blanco puro: sobre blanco, una foto con
        // fondo claro se derrama fuera de su celda y el collage deja de
        // leerse como cuatro piezas.
        $canvas = $manager->create(self::CANVAS, self::CANVAS)->fill('#faf3e3');

        $cell = (int) self::CELL;

        foreach (array_values($paths) as $index => $path) {
            $photo = $manager->read(Storage::disk('r2')->get($path))->cover($cell, $cell);

            $canvas->place(
                $photo,
                'top-left',
                self::PAD + (($index % 2) * ($cell + self::GAP)),
                self::PAD + (intdiv($index, 2) * ($cell + self::GAP)),
            );
        }

        $this->signature($canvas, $provider->public_name ?? Provider::DEFAULT_BUSINESS_NAME);

        $key = sprintf('providers/%d/content/%s.jpg', $provider->id, (string) Str::ulid());

        Storage::disk('r2')->put($key, (string) $canvas->toJpeg(quality: 88), [
            'ContentType' => 'image/jpeg',
            'CacheControl' => 'public, max-age=31536000, immutable',
        ]);

        return $key;
    }

    /**
     * El nombre abajo, centrado y espaciado.
     *
     * Sale en peso fino porque el Manrope que se incluye es una fuente
     * variable y FreeType renderiza su instancia por defecto — el peso que
     * se le pida se ignora. Se deja así a propósito: un nombre fino y
     * espaciado sobre crema lee como firma de marca de belleza, no como
     * texto por descuido. Cuando existan sus plantillas reales, esto se
     * reemplaza por el logo de cada una.
     */
    private function signature(\Intervention\Image\Interfaces\ImageInterface $canvas, string $name): void
    {
        $canvas->text(
            Str::upper($this->spaced($name)),
            (int) round(self::CANVAS / 2),
            self::CANVAS - (int) round(self::PAD / 2),
            function (FontFactory $font) {
                $font->filename(resource_path('fonts/Manrope.ttf'));
                $font->size(23);
                $font->color('#8a6a25');
                $font->align('center');
                $font->valign('middle');
            },
        );
    }

    /**
     * Espaciado a mano con espacios finos: Intervention no expone
     * letter-spacing, y sin él un nombre corto en mayúsculas se ve apretado.
     */
    private function spaced(string $text): string
    {
        return implode("\u{2009}", mb_str_split($text));
    }
}
