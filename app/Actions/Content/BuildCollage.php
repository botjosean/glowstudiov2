<?php

namespace App\Actions\Content;

use App\Enums\BusinessCategory;
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

    public function __construct(
        private readonly DrawHeadline $drawHeadline,
    ) {}

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

        // El armado se sortea entre los que sirven para esta cantidad, en vez
        // de ser siempre la misma rejilla pareja. Ver CollageArrangement.
        // De los armados que caben, se sortea entre los que le pegan al rubro:
        // un spa quiere aire y una imagen grande, un salón de uñas quiere
        // mostrar muchos diseños de una. Ver BrandStyle.
        $options = BrandStyle::preferredArrangements(
            $provider->business_category,
            CollageArrangement::optionsFor(count($paths)),
        );

        $arrangement = $options[array_rand($options)];
        $rects = CollageArrangement::rects($arrangement, count($paths));

        $half = (int) round(self::SEAM / 2);

        foreach ($rects as $i => [$fx, $fy, $fw, $fh]) {
            if (! isset($paths[$i])) {
                break;
            }

            // La junta se descuenta por dentro de cada rectángulo, así las
            // fotos del borde llegan al filo y solo se separan entre ellas.
            $x = (int) round($fx * self::CANVAS) + ($fx > 0 ? $half : 0);
            $y = (int) round($fy * self::CANVAS) + ($fy > 0 ? $half : 0);
            $w = (int) round($fw * self::CANVAS) - ($fx > 0 ? $half : 0) - ($fx + $fw < 1 ? $half : 0);
            $h = (int) round($fh * self::CANVAS) - ($fy > 0 ? $half : 0) - ($fy + $fh < 1 ? $half : 0);

            $photo = $manager->read(Storage::disk('r2')->get($paths[$i]))->cover(max($w, 1), max($h, 1));
            $canvas->place($photo, 'top-left', $x, $y);
        }

        if ($arrangement === 'before-after') {
            $this->beforeAfterLabels($canvas, $provider->business_category);
        } else {
            // El titular se salta en antes/después: las dos etiquetas ya son
            // el mensaje, y un titular encima tapa justo lo que se compara.
            $this->drawHeadline->handle($canvas, $headline, $provider->business_category, $provider->content_style, self::CANVAS);
        }

        // El pie primero: devuelve cuánto alto ocupó, para que el sello se
        // apoye encima y no quede montado sobre el texto de contacto.
        $footer = $this->contactFooter($canvas, $provider);
        $this->badge($canvas, $provider, $footer);

        $key = sprintf('providers/%d/content/%s.jpg', $provider->id, (string) Str::ulid());

        Storage::disk('r2')->put($key, (string) $canvas->toJpeg(quality: 90), [
            'ContentType' => 'image/jpeg',
            'CacheControl' => 'public, max-age=31536000, immutable',
        ]);

        return $key;
    }

    /**
     * Dónde queda y cómo la llaman, en una franja al pie.
     *
     * Es lo que sus referencias llevan abajo y lo que faltaba: sin esto la
     * foto es bonita pero nadie sabe adónde ir. La franja oscura no es
     * adorno — sobre una foto clara el texto chico desaparece.
     *
     * El sello va a la derecha, así que el texto se corre a la izquierda
     * para no quedar debajo.
     */
    private function contactFooter(ImageInterface $canvas, Provider $provider): int
    {
        $linea = BrandStyle::contactLine($provider->address_line, $provider->loadMissing('user')->user?->phone);

        if ($linea === '') {
            return 0;
        }

        $alto = 74;
        $y = self::CANVAS - $alto;

        $canvas->drawRectangle(0, $y, function ($rect) use ($alto): void {
            $rect->size(self::CANVAS, $alto);
            $rect->background('rgba(8, 10, 16, 0.82)');
        });

        // Se centra y se encoge hasta que entre. Antes iba espaciado y
        // alineado a la izquierda, y con una dirección larga el teléfono
        // quedaba cortado contra el borde — visto en una prueba real.
        $margen = 40;
        $disponible = self::CANVAS - ($margen * 2);
        $fuente = resource_path('fonts/Manrope.ttf');

        $size = 21;

        while ($size > 13 && $this->textWidth($linea, $size, $fuente) > $disponible) {
            $size -= 1;
        }

        // Si ni al tamaño mínimo entra, se sacrifica la dirección y se deja
        // el teléfono, que es lo accionable.
        if ($this->textWidth($linea, $size, $fuente) > $disponible) {
            $linea = trim((string) strrchr($linea, '·'), '· ');
        }

        $canvas->text($linea, (int) round(self::CANVAS / 2), $y + (int) round($alto / 2), function (FontFactory $f) use ($size, $fuente): void {
            $f->filename($fuente);
            $f->size($size);
            $f->color('#f1f5f9');
            $f->align('center');
            $f->valign('middle');
        });

        return $alto;
    }

    /**
     * Las etiquetas ANTES y DESPUÉS, una en cada mitad.
     *
     * Van abajo y no al centro para no taparle la cara al trabajo, que es
     * justo lo que se está comparando. El "después" lleva el color de acento
     * porque es el que se quiere mirar.
     */
    private function beforeAfterLabels(ImageInterface $canvas, ?BusinessCategory $category): void
    {
        $w = 210;
        $h = 56;
        $y = self::CANVAS - $h - 60;

        $etiquetas = [
            ['ANTES', (int) round(self::CANVAS * 0.25), 'rgba(10, 12, 18, 0.78)'],
            ['DESPUÉS', (int) round(self::CANVAS * 0.75), BrandStyle::accent($category)],
        ];

        foreach ($etiquetas as [$texto, $centerX, $fondo]) {
            $canvas->drawRectangle($centerX - (int) round($w / 2), $y, function ($rect) use ($w, $h, $fondo): void {
                $rect->size($w, $h);
                $rect->background($fondo);
            });

            $canvas->text($texto, $centerX, $y + (int) round($h / 2), function (FontFactory $f): void {
                $f->filename(resource_path('fonts/Manrope.ttf'));
                $f->size(26);
                $f->color('#ffffff');
                $f->align('center');
                $f->valign('middle');
            });
        }
    }

    /**
     * Cuánto mide de ancho ese texto, medido de verdad. Lo usa el pie de
     * contacto para encogerse hasta que entre; la versión que usa el titular
     * vive en DrawHeadline.
     */
    private function textWidth(string $text, int $size, string $font): int
    {
        return DrawHeadline::textWidth($text, $size, $font);
    }

    /**
     * El sello redondo de la esquina.
     *
     * Lleva SU LOGO —la foto de perfil del negocio— y no su nombre escrito.
     * Ella lo señaló mirando un post: «están usando un logo ahí de un nombre,
     * se tiene que usar el logo de Glow Studio». Tiene razón: un nombre en
     * texto es un pie de foto, un logo es una marca.
     *
     * Si todavía no cargó foto de perfil, cae al nombre — un sello vacío
     * sería peor que uno con letras.
     */
    private function badge(ImageInterface $canvas, Provider $provider, int $footer = 0): void
    {
        $logo = $provider->avatar_photo_url;

        if ($logo !== null && $logo !== '') {
            $this->logoBadge($canvas, $logo, $footer);

            return;
        }

        $this->nameBadge($canvas, $provider->public_name ?? Provider::DEFAULT_BUSINESS_NAME, $footer);
    }

    /**
     * Su foto de perfil recortada en círculo, con un aro blanco que la
     * despega de la foto de abajo.
     */
    private function logoBadge(ImageInterface $canvas, string $key, int $footer = 0): void
    {
        $radius = 70;
        $centerX = self::CANVAS - $radius - 32;
        $centerY = self::CANVAS - $footer - $radius - 26;

        // El aro blanco primero, un poco más grande que el logo.
        $canvas->drawCircle($centerX, $centerY, function ($circle) use ($radius): void {
            $circle->radius($radius);
            $circle->background('#ffffff');
        });

        $size = ($radius - 6) * 2;

        $logo = ImageManager::imagick()->read(self::circularLogo(Storage::disk('r2')->get($key), $size));

        $canvas->place($logo, 'top-left', $centerX - (int) round($size / 2), $centerY - (int) round($size / 2));
    }

    /**
     * Recorta una imagen en círculo y la devuelve como PNG con transparencia.
     *
     * Con Imagick a pelo y no con Intervention: la versión 3 no expone
     * máscaras (`applyMask` no existe, comprobado al reventar), y un logo
     * cuadrado metido dentro de un aro redondo se ve como un error.
     *
     * @return string el PNG en binario
     */
    public static function circularLogo(string $binary, int $size): string
    {
        $logo = new \Imagick;
        $logo->readImageBlob($binary);
        $logo->setImageFormat('png');
        $logo->cropThumbnailImage($size, $size);

        // Opaco donde se ve, TRANSPARENTE donde se recorta.
        //
        // Antes la máscara era negra y el recorte no se aplicaba: en
        // ImageMagick 7, COPYOPACITY copia el ALFA de la máscara, y una
        // máscara negra es opaca en todos lados. El logo salía cuadrado y
        // nadie lo notó porque los logos suelen venir con fondo blanco.
        // Salió a la luz recortando fotos en círculo, donde el cuadrado se
        // ve de inmediato.
        $mask = new \Imagick;
        $mask->newImage($size, $size, new \ImagickPixel('transparent'), 'png');

        $draw = new \ImagickDraw;
        $draw->setFillColor(new \ImagickPixel('white'));
        $draw->circle($size / 2, $size / 2, $size / 2, 0);
        $mask->drawImage($draw);

        $logo->setImageAlphaChannel(\Imagick::ALPHACHANNEL_OPAQUE);
        $logo->compositeImage($mask, \Imagick::COMPOSITE_DSTIN, 0, 0);

        return $logo->getImageBlob();
    }

    private function nameBadge(ImageInterface $canvas, string $name, int $footer = 0): void
    {
        // Abajo a la derecha y no al centro: centrado caía justo sobre la
        // unión entre dos fotos y quedaba partido por la junta blanca. En una
        // esquina siempre se apoya dentro de una sola foto.
        $radius = 70;
        $centerX = self::CANVAS - $radius - 32;
        $centerY = self::CANVAS - $footer - $radius - 26;

        $canvas->drawCircle($centerX, $centerY, function ($circle) use ($radius): void {
            $circle->radius($radius);
            $circle->background('#ffffff');
        });

        // Dos líneas si el nombre tiene apellido: en un círculo, una sola
        // línea larga se sale por los lados.
        $parts = preg_split('/\s+/u', trim($name)) ?: [$name];
        $first = Str::upper($parts[0]);
        $rest = count($parts) > 1 ? Str::upper(implode(' ', array_slice($parts, 1))) : '';

        $canvas->text($first, $centerX, $centerY - ($rest === '' ? 0 : 12), function (FontFactory $font): void {
            $font->filename(resource_path('fonts/Manrope.ttf'));
            $font->size(23);
            $font->color('#8a6a25');
            $font->align('center');
            $font->valign('middle');
        });

        if ($rest !== '') {
            $canvas->text($this->spaced($rest), $centerX, $centerY + 17, function (FontFactory $font): void {
                $font->filename(resource_path('fonts/Manrope.ttf'));
                $font->size(12);
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
