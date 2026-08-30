<?php

namespace App\Actions\Content;

use App\Models\Provider;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;

/**
 * Dos colores usados juntos en el mismo trabajo: una foto arriba, otra abajo,
 * y en el medio una tarjeta con los dos tonos apilados —nombre y hex de cada
 * uno—, como pregunta una carta de colores.
 *
 * Es la que trajo de @metanoia.espaciodeestetica: «Combinaciones ¿sí o no?»,
 * dos fotos con una tarjetita "blush #E36887 / morning butter #F3D98F"
 * flotando entre ellas.
 *
 * Es lo opuesto de "Fondo de color" (ver BuildColorBlock), no una variante:
 * ahí dos fotos de colores distintos en la misma selección es un error —una
 * tanda mezclada por accidente—; acá es el pedido. La misma lectura de color
 * por foto (ver ReadPhotoColor) sirve para las dos cosas, según lo que se le
 * pida hacer con ella.
 */
class BuildColorCombo
{
    private const CANVAS = 1080;

    /**
     * @param  array{0: string, 1: string}  $paths  una foto por color
     * @param  array{0: array{nombre: string, hex: string}, 1: array{nombre: string, hex: string}}  $colores
     * @return string la clave de R2 del post generado
     */
    public function handle(Provider $provider, array $paths, array $colores): string
    {
        $manager = ImageManager::imagick();
        $mitad = (int) (self::CANVAS / 2);

        $canvas = $manager->create(self::CANVAS, self::CANVAS);

        $arriba = $manager->read(Storage::disk('r2')->get($paths[0]))->cover(self::CANVAS, $mitad);
        $abajo = $manager->read(Storage::disk('r2')->get($paths[1]))->cover(self::CANVAS, self::CANVAS - $mitad);

        $canvas->place($arriba, 'top-left', 0, 0);
        $canvas->place($abajo, 'top-left', 0, $mitad);

        $this->wordmark($canvas, $provider);
        $this->card($canvas, $manager, $colores);

        $key = sprintf('providers/%d/content/%s.jpg', $provider->id, (string) Str::ulid());

        Storage::disk('r2')->put($key, (string) $canvas->toJpeg(quality: 90), [
            'ContentType' => 'image/jpeg',
            'CacheControl' => 'public, max-age=31536000, immutable',
        ]);

        return $key;
    }

    /** El nombre del negocio, chico y semitransparente, arriba del todo. */
    private function wordmark(ImageInterface $canvas, Provider $provider): void
    {
        $nombre = trim($provider->public_name ?? '');

        if ($nombre === '') {
            return;
        }

        $canvas->text(
            implode("\u{2009}", mb_str_split(Str::upper($nombre))),
            (int) round(self::CANVAS / 2),
            48,
            function (FontFactory $f): void {
                $f->filename(resource_path('fonts/Manrope.ttf'));
                $f->size(19);
                $f->color('rgba(255,255,255,0.85)');
                $f->align('center');
                $f->valign('middle');
            },
        );
    }

    /**
     * La tarjeta blanca a caballo entre las dos fotos, con los dos colores
     * apilados adentro. Blanca y no del color de cada tono: tiene que leerse
     * igual de bien encima de cualquier foto, clara u oscura.
     *
     * @param  array{0: array{nombre: string, hex: string}, 1: array{nombre: string, hex: string}}  $colores
     */
    private function card(ImageInterface $canvas, ImageManager $manager, array $colores): void
    {
        $ancho = 420;
        $altoBloque = 108;
        $alto = $altoBloque * 2;
        $x = (int) round((self::CANVAS - $ancho) / 2);
        $y = (int) round((self::CANVAS - $alto) / 2);

        // La sombra primero, un poco más grande y desplazada: sin ella la
        // tarjeta blanca se funde con fotos claras.
        $canvas->place(
            $manager->create($ancho + 16, $alto + 16)->fill('rgba(0,0,0,0.18)'),
            'top-left',
            $x - 8,
            $y - 4,
        );
        $canvas->place($manager->create($ancho, $alto)->fill('#FFFFFF'), 'top-left', $x, $y);

        foreach ($colores as $i => $color) {
            $this->swatch($canvas, $manager, $color, $x, $y + $i * $altoBloque, $ancho, $altoBloque);
        }
    }

    /**
     * @param  array{nombre: string, hex: string}  $color
     */
    private function swatch(ImageInterface $canvas, ImageManager $manager, array $color, int $x, int $y, int $ancho, int $alto): void
    {
        $lado = 96;
        $relleno = (int) round(($alto - $lado) / 2);

        $canvas->place($manager->create($lado, $lado)->fill($color['hex']), 'top-left', $x + $relleno, $y + $relleno);

        // La tarjeta es blanca, así que la tinta del texto es siempre
        // oscura, sin importar el color del esmalte que está al lado.
        $tinta = '#1A1A1A';
        $centroTexto = $x + $lado + $relleno * 2;
        $anchoTexto = $ancho - $lado - $relleno * 3;
        $fuente = resource_path('fonts/Playfair.ttf');
        $cuerpo = $this->fits($color['nombre'], 34, $anchoTexto, $fuente);

        $canvas->text($color['nombre'], $centroTexto, $y + (int) round($alto * 0.38), function (FontFactory $f) use ($tinta, $fuente, $cuerpo): void {
            $f->filename($fuente);
            $f->size($cuerpo);
            $f->color($tinta);
            $f->valign('middle');
        });

        $canvas->text(strtoupper($color['hex']), $centroTexto, $y + (int) round($alto * 0.68), function (FontFactory $f): void {
            $f->filename(resource_path('fonts/Manrope.ttf'));
            $f->size(20);
            $f->color('#8A8A8A');
            $f->valign('middle');
        });
    }

    /** El cuerpo más grande con el que el nombre del color entra en la tarjeta. */
    private function fits(string $text, int $desde, int $ancho, string $fuente): int
    {
        $size = $desde;

        while ($size > 18 && DrawHeadline::textWidth($text, $size, $fuente) > $ancho) {
            $size -= 2;
        }

        return $size;
    }
}
