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
            $this->headline($canvas, $headline, $provider->business_category);
        }

        $this->badge($canvas, $provider);

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
    private function headline(ImageInterface $canvas, array $lines, ?BusinessCategory $category): void
    {
        $lines = self::cleanLines($lines);

        if ($lines === []) {
            return;
        }

        // El estilo se sortea, pero solo entre los que le pegan al rubro: una
        // barbería con bloques rosa de revista es justo el post que no se
        // publica. Ver BrandStyle.
        $styles = BrandStyle::headlineStyles($category);
        $style = $styles[array_rand($styles)];

        $colors = BrandStyle::blockColors($category);
        $font = BrandStyle::headlineFont($category);

        // Y dónde cae: al centro, abajo o arriba.
        $spots = ['center', 'bottom', 'top'];
        $spot = $spots[array_rand($spots)];

        $size = $style === 'clean' ? 104 : 96;
        $lineHeight = (int) round($size * 1.22);
        $padX = 26;
        $padY = 12;

        $blockHeight = count($lines) * $lineHeight;

        $top = match ($spot) {
            'top' => 86,
            'bottom' => self::CANVAS - $blockHeight - 210,
            default => (int) round((self::CANVAS - $blockHeight) / 2),
        };

        // Antetítulo: la línea chica que promete algo antes de que se lea el
        // titular. No va con 'blocks' — sobre bloques de color apilados no
        // tiene dónde apoyarse y queda flotando.
        $eyebrows = BrandStyle::eyebrows($category);

        if ($style !== 'blocks' && $eyebrows !== [] && random_int(0, 1) === 1) {
            $canvas->text($eyebrows[array_rand($eyebrows)], (int) round(self::CANVAS / 2), $top - 34, function (FontFactory $f) use ($category): void {
                $f->filename(resource_path('fonts/Manrope.ttf'));
                $f->size(20);
                $f->color(BrandStyle::accent($category));
                $f->align('center');
                $f->valign('middle');
            });
        }

        // Y a veces la última línea va en el color de acento en vez de blanca:
        // es el truco de su referencia («que SIEMPRE quisiste» en dorado).
        $accentLast = $style !== 'blocks' && random_int(0, 1) === 1;

        // La franja va de una sola pieza, antes del texto: dibujar una por
        // línea dejaba rayas de foto entre medio y se veía descuidado.
        if ($style === 'band') {
            $canvas->drawRectangle(0, $top - $padY, function ($rect) use ($blockHeight, $padY): void {
                $rect->size(self::CANVAS, $blockHeight + ($padY * 2));
                $rect->background('rgba(10, 12, 18, 0.72)');
            });
        }

        foreach ($lines as $i => $line) {
            $centerY = $top + ($i * $lineHeight) + (int) round($lineHeight / 2);
            $width = $this->textWidth($line, $size, $font);

            if ($style === 'blocks') {
                $canvas->drawRectangle(
                    (int) round((self::CANVAS - $width) / 2) - $padX,
                    $centerY - (int) round($size / 2) - $padY,
                    function ($rect) use ($width, $size, $padX, $padY, $i, $colors): void {
                        $rect->size($width + ($padX * 2), $size + ($padY * 2));
                        $rect->background($colors[$i % count($colors)]);
                    },
                );
            } elseif ($style !== 'band') {
                // 'clean': sin fondo. Una sombra suave detrás para que sobreviva
                // sobre una foto clara, que en belleza son la mitad.
                $canvas->text($line, (int) round(self::CANVAS / 2) + 3, $centerY + 3, function (FontFactory $f) use ($size, $font): void {
                    $f->filename($font);
                    $f->size($size);
                    $f->color('rgba(0, 0, 0, 0.45)');
                    $f->align('center');
                    $f->valign('middle');
                });
            }

            $isLast = $i === count($lines) - 1;

            $canvas->text($line, (int) round(self::CANVAS / 2), $centerY, function (FontFactory $f) use ($size, $font, $accentLast, $isLast, $category): void {
                $f->filename($font);
                $f->size($size);
                $f->color($accentLast && $isLast ? BrandStyle::accent($category) : '#ffffff');
                $f->align('center');
                $f->valign('middle');
            });
        }
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
     * Deja solo lo que la tipografía sabe dibujar.
     *
     * Anton trae glifos latinos y nada más. El modelo devolvió un titular con
     * un emoji al final y esa línea no pintó ninguna letra, pero SÍ midió
     * ancho — así que quedó un bloque de color vacío colgando debajo del
     * titular en un post real. Se limpia el texto y se descartan las líneas
     * que quedan sin nada que dibujar.
     *
     * @param  list<string>  $lines
     * @return list<string>
     */
    public static function cleanLines(array $lines): array
    {
        // Rangos explícitos y no \p{Latin}: esa propiedad incluye el punto
        // volado «·» —Unicode lo cuenta como latino porque el catalán lo usa—
        // y una línea de solo puntos volados pasaba el filtro y volvía a
        // pintar el bloque vacío. Lo cazó una prueba antes de llegar a un post.
        $letters = 'A-Za-z0-9À-ÖØ-öø-ÿ';

        $clean = array_map(static function (string $line) use ($letters): string {
            $only = preg_replace('/[^'.$letters.'\s\'\-&¡!¿?.,]/u', '', $line) ?? '';

            return Str::upper(trim(preg_replace('/\s+/u', ' ', $only) ?? ''));
        }, array_slice($lines, 0, 3));

        return array_values(array_filter(
            $clean,
            // No basta con que no esté vacía: una línea de solo signos
            // también quedaría sin letras visibles.
            static fn (string $line): bool => preg_match('/['.$letters.']/u', $line) === 1,
        ));
    }

    /**
     * Cuánto mide de ancho ese texto, medido de verdad: se dibuja en un
     * lienzo aparte y se pregunta por su caja.
     */
    private function textWidth(string $text, int $size, string $font): int
    {
        $draw = new \ImagickDraw;
        $draw->setFont($font);
        $draw->setFontSize($size);

        $metrics = (new \Imagick)->queryFontMetrics($draw, $text);

        return (int) round($metrics['textWidth']);
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
    private function badge(ImageInterface $canvas, Provider $provider): void
    {
        $logo = $provider->avatar_photo_url;

        if ($logo !== null && $logo !== '') {
            $this->logoBadge($canvas, $logo);

            return;
        }

        $this->nameBadge($canvas, $provider->public_name ?? Provider::DEFAULT_BUSINESS_NAME);
    }

    /**
     * Su foto de perfil recortada en círculo, con un aro blanco que la
     * despega de la foto de abajo.
     */
    private function logoBadge(ImageInterface $canvas, string $key): void
    {
        $radius = 70;
        $centerX = self::CANVAS - $radius - 32;
        $centerY = self::CANVAS - $radius - 32;

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

        // Blanco donde se ve, negro donde se recorta.
        $mask = new \Imagick;
        $mask->newImage($size, $size, new \ImagickPixel('black'), 'png');

        $draw = new \ImagickDraw;
        $draw->setFillColor(new \ImagickPixel('white'));
        $draw->circle($size / 2, $size / 2, $size / 2, 0);
        $mask->drawImage($draw);

        $logo->setImageAlphaChannel(\Imagick::ALPHACHANNEL_SET);
        $logo->compositeImage($mask, \Imagick::COMPOSITE_COPYOPACITY, 0, 0);

        return $logo->getImageBlob();
    }

    private function nameBadge(ImageInterface $canvas, string $name): void
    {
        // Abajo a la derecha y no al centro: centrado caía justo sobre la
        // unión entre dos fotos y quedaba partido por la junta blanca. En una
        // esquina siempre se apoya dentro de una sola foto.
        $radius = 70;
        $centerX = self::CANVAS - $radius - 32;
        $centerY = self::CANVAS - $radius - 32;

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
