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
     * Cuánto ocupa de alto cada franja de fotos, sobre el lado del lienzo.
     *
     * **Franjas de borde a borde, no cuatro cuadraditos en las esquinas.**
     * Antes eran esquinas y dejaban una cruz de fondo vacío cruzando todo el
     * post. Ella lo vio de una: «hay muchos espacios de blanco en la cruz en
     * el medio y así no es, mirá la referencia». Tenía razón — en Mimosa las
     * fotos llenan el ancho completo arriba y abajo, y el color solo se ve en
     * la banda del medio donde va el nombre.
     */
    private const STRIP = 0.38;

    /** La franja del medio que queda libre, en píxeles. */
    private const BAND = (int) (self::CANVAS * (1 - 2 * self::STRIP));

    /** Dónde empieza esa franja. Todo el texto se ubica respecto a acá. */
    private const BAND_TOP = (int) (self::CANVAS * self::STRIP);

    public function __construct(
        private readonly FindOrCreateColorProp $props,
    ) {}

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

        $this->strips($canvas, $manager, array_values($paths), $provider, $colorName, $colorHex);
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
     * Las fotos en dos franjas de borde a borde: una arriba y otra abajo,
     * con la banda de color libre en el medio para el nombre.
     *
     * Es la estructura de sus referencias de Mimosa —«Butter yellow»,
     * «Strawberry Red», «Stripes»— donde las fotos llegan hasta los cuatro
     * bordes y el color solo respira en la banda del centro. Antes esto eran
     * cuatro cuadrados en las esquinas y dejaba una cruz de fondo vacío
     * cruzando el post entero; ella lo señaló mirando el resultado: «hay
     * muchos espacios de blanco en la cruz en el medio y así no es».
     *
     * Si sobra lugar en una franja —eligió dos fotos, no cuatro— se rellena
     * con una foto decorativa a juego con el color, como el limón de "Butter
     * yellow". Nunca es el trabajo real: eso sigue siendo siempre una foto de
     * verdad, la que ella subió.
     *
     * @param  list<string>  $paths
     */
    private function strips(ImageInterface $canvas, ImageManager $manager, array $paths, Provider $provider, string $colorName, string $colorHex): void
    {
        $alto = (int) round(self::CANVAS * self::STRIP);
        $usables = array_slice($paths, 0, 4);
        $total = count($usables);

        // Con una sola foto de verdad se estira arriba y la decorativa va
        // abajo; con dos, una en cada franja; con tres o cuatro, se reparten
        // mitad y mitad.
        $arriba = $total <= 1 ? $total : (int) ceil($total / 2);

        $filas = [
            ['fotos' => array_slice($usables, 0, $arriba), 'y' => 0],
            ['fotos' => array_slice($usables, $arriba), 'y' => self::CANVAS - $alto],
        ];

        // La decorativa se pide UNA vez y se reusa en los huecos que queden,
        // en vez de una llamada por hueco.
        $decoracion = null;
        $faltan = array_sum(array_map(static fn (array $f): int => $f['fotos'] === [] ? 1 : 0, $filas));

        if ($faltan > 0 && $provider->business_category !== null) {
            $prop = $this->props->handle($provider->business_category, $colorName, $colorHex);

            if ($prop !== null) {
                $decoracion = Storage::disk('r2')->get($prop);
            }
        }

        foreach ($filas as $fila) {
            $fotos = $fila['fotos'];

            // Una franja sin fotos de trabajo se llena con la decorativa a
            // todo el ancho; si tampoco hay, queda el fondo de color liso.
            if ($fotos === []) {
                if ($decoracion === null) {
                    continue;
                }

                $canvas->place(
                    $manager->read($decoracion)->cover(self::CANVAS, $alto),
                    'top-left',
                    0,
                    $fila['y'],
                );

                continue;
            }

            // Se reparten el ancho completo entre las que tocan: sin huecos
            // y sin bordes de fondo asomando entre foto y foto.
            $ancho = (int) ceil(self::CANVAS / count($fotos));

            foreach ($fotos as $i => $path) {
                $foto = $manager->read(Storage::disk('r2')->get($path))->cover($ancho, $alto);

                $canvas->place($foto, 'top-left', $i * $ancho, $fila['y']);
            }
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

        // Las tres piezas se apoyan en el borde de arriba de la banda, no en
        // el centro del lienzo: con el centro, la marca del negocio quedaba
        // pisada por el titular apenas el nombre del color era largo.
        $grande = $this->fits($arriba, min(104, (int) round(self::BAND * 0.40)), 900, $fuente);
        $yGrande = self::BAND_TOP + ($abajo === '' ? (int) round(self::BAND / 2) : 108);

        $canvas->text($arriba, $centro, $yGrande, function (FontFactory $f) use ($grande, $fuente, $tinta): void {
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

        $canvas->text($abajo, $centro, self::BAND_TOP + 194, function (FontFactory $f) use ($chico, $suave, $tinta): void {
            $f->filename($suave);
            $f->size($chico);
            $f->color($tinta);
            $f->align('center');
            $f->valign('middle');
        });
    }

    /**
     * El nombre del negocio, chico y espaciado, arriba de la franja libre.
     *
     * Arriba del todo no: ahí van las fotos y quedaba escrito sobre unas
     * uñas. Pegado al borde de arriba de la banda, para no chocar con el
     * titular cuando el nombre del color es largo.
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
            self::BAND_TOP + 32,
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
