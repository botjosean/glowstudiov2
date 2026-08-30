<?php

namespace App\Actions\Content;

use App\Models\Provider;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;

/**
 * Fondo del color del trabajo, las fotos en las esquinas y el nombre del
 * color grande en el medio.
 *
 * Es la plantilla que ella trajo una y otra vez —Mimosa Studio, «Butter
 * yellow», «Milky Pink», «Mocha Mousse»— y la describió sola: «agarra la
 * foto, la pone alrededor de la pantalla, pone algo en el medio, el fondo lo
 * pone de un color; esto debería ser una plantilla, está demasiado fácil».
 *
 * Tenía razón en que es fácil, pero solo porque antes se resolvió lo difícil:
 * saber de qué color es el trabajo. Eso no sale de los píxeles —las uñas son
 * una parte chica del cuadro y gana la ropa del fondo— sino de lo que ella
 * misma confirma al subir. Ver ReadPhotoColor.
 *
 * **El titular es el nombre del color**, no una frase aparte: «MOCHA /
 * mousse», «STRAWBERRY / red». Por eso esta plantilla no usa la biblioteca de
 * frases y no le pide nada al modelo de texto.
 */
class BuildColorBlock
{
    private const CANVAS = 1080;

    /**
     * Cuánto ocupa cada foto de esquina, sobre el lado del lienzo.
     *
     * 0.36 y no más: con 0.46 las cuatro fotos casi se tocaban y el fondo
     * quedaba en una cruz fina, con el texto montado encima de las uñas.
     * Acá dos esquinas suman 0.72, así que queda una franja libre de casi un
     * tercio del alto para el nombre — que es la proporción que tienen sus
     * referencias.
     */
    private const CORNER = 0.36;

    /** La franja del medio que queda libre, en píxeles. */
    private const BAND = (int) (self::CANVAS * (1 - 2 * self::CORNER));

    /**
     * @param  list<string>  $paths  hasta cuatro; las de más se ignoran
     * @return string la clave de R2 del post generado
     */
    public function handle(Provider $provider, array $paths, string $colorName, string $colorHex): string
    {
        $manager = ImageManager::imagick();

        $fondo = ColorNames::tint($colorHex, 0.82);
        $tinta = ColorNames::shade($colorHex, 0.45);

        $canvas = $manager->create(self::CANVAS, self::CANVAS)->fill($fondo);

        $this->corners($canvas, $manager, array_values($paths));
        $this->name($canvas, $colorName, $tinta);
        $this->wordmark($canvas, $provider, $tinta);

        $key = sprintf('providers/%d/content/%s.jpg', $provider->id, (string) Str::ulid());

        Storage::disk('r2')->put($key, (string) $canvas->toJpeg(quality: 90), [
            'ContentType' => 'image/jpeg',
            'CacheControl' => 'public, max-age=31536000, immutable',
        ]);

        return $key;
    }

    /**
     * Las fotos en las esquinas, sangrando por el borde.
     *
     * En las esquinas y no en una rejilla a propósito: así queda libre la
     * franja del medio, que es donde va el nombre. Con dos fotos van en
     * diagonal —arriba a la izquierda y abajo a la derecha— para que el
     * cuadro no quede desbalanceado.
     *
     * @param  list<string>  $paths
     */
    private function corners(ImageInterface $canvas, ImageManager $manager, array $paths): void
    {
        $lado = (int) round(self::CANVAS * self::CORNER);

        // Orden de llenado: diagonal primero, después las otras dos.
        $esquinas = ['top-left', 'bottom-right', 'bottom-left', 'top-right'];

        foreach (array_slice($paths, 0, 4) as $i => $path) {
            $foto = $manager->read(Storage::disk('r2')->get($path))->cover($lado, $lado);

            $canvas->place($foto, $esquinas[$i], 0, 0);
        }
    }

    /**
     * El nombre del color, en dos líneas: la primera en mayúsculas gruesas y
     * la segunda debajo, más chica.
     *
     * Es el corte que hacen todas sus referencias: «MOCHA / mousse»,
     * «STRAWBERRY / red», «Butter / yellow». Si el nombre es de una sola
     * palabra va entero en la línea grande.
     */
    private function name(ImageInterface $canvas, string $colorName, string $tinta): void
    {
        $palabras = DrawHeadline::cleanLines([$colorName]);

        if ($palabras === []) {
            return;
        }

        $partes = preg_split('/\s+/u', $palabras[0]) ?: [];

        // La primera palabra arriba y el resto abajo: "ROSA / DEGRADADO A
        // BLANCO" se lee mejor que las cuatro palabras en un solo renglón.
        $arriba = array_shift($partes) ?? '';
        $abajo = implode(' ', $partes);

        $centro = (int) round(self::CANVAS / 2);
        $fuente = resource_path('fonts/Anton.ttf');
        $suave = resource_path('fonts/Manrope.ttf');

        // Todo tiene que caber en la franja libre del medio, y no solo a lo
        // ancho: si se pasa de alto, el texto termina sobre las uñas. De ahí
        // que el cuerpo salga de la franja y no de un número fijo.
        $grande = $this->fits($arriba, min(126, (int) round(self::BAND * 0.46)), 900, $fuente);

        $canvas->text($arriba, $centro, $abajo === '' ? $centro : $centro - (int) round($grande * 0.34), function (FontFactory $f) use ($grande, $fuente, $tinta): void {
            $f->filename($fuente);
            $f->size($grande);
            $f->color($tinta);
            $f->align('center');
            $f->valign('middle');
        });

        if ($abajo === '') {
            return;
        }

        $chico = $this->fits($abajo, (int) round($grande * 0.42), 880, $suave);

        $canvas->text($abajo, $centro, $centro + (int) round($grande * 0.46), function (FontFactory $f) use ($chico, $suave, $tinta): void {
            $f->filename($suave);
            $f->size($chico);
            $f->color($tinta);
            $f->align('center');
            $f->valign('middle');
        });
    }

    /**
     * El nombre del negocio, chico y espaciado, dentro de la franja libre.
     *
     * Arriba del todo no: ahí están las fotos de las esquinas y quedaba
     * escrito sobre unas uñas.
     */
    private function wordmark(ImageInterface $canvas, Provider $provider, string $tinta): void
    {
        $nombre = trim($provider->public_name ?? '');

        if ($nombre === '') {
            return;
        }

        $canvas->text(
            implode("\u{2009}", mb_str_split(Str::upper($nombre))),
            (int) round(self::CANVAS / 2),
            (int) round(self::CANVAS / 2 - self::BAND * 0.36),
            function (FontFactory $f) use ($tinta): void {
                $f->filename(resource_path('fonts/Manrope.ttf'));
                $f->size(19);
                $f->color($tinta);
                $f->align('center');
                $f->valign('middle');
            },
        );
    }

    /** El cuerpo más grande con el que ese texto entra en ese ancho. */
    private function fits(string $text, int $desde, int $ancho, string $fuente): int
    {
        $size = $desde;

        while ($size > 22 && DrawHeadline::textWidth($text, $size, $fuente) > $ancho) {
            $size -= 2;
        }

        return $size;
    }
}
