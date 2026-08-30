<?php

namespace App\Actions\Content;

use App\Enums\BusinessCategory;
use Illuminate\Support\Str;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Typography\FontFactory;

/**
 * El titular impreso sobre la foto, en cualquiera de los tratamientos que
 * ella guardó como referencia.
 *
 * **Vive aparte a propósito.** Antes esto estaba escrito dentro de
 * BuildCollage y BuildHero tenía SU PROPIA versión, escrita a mano: serif
 * blanca, abajo a la izquierda, siempre igual. Así, elegir una plantilla no
 * cambiaba nada en el formato de foto grande —el más usado— y ella lo
 * notó de inmediato: «sí agarra la foto de referencia, sí hace todo, pero
 * cuando me da el resultado no es nada parecido [...] no son las letras,
 * esa que una va arriba de otra, una gruesa, una fina».
 *
 * Con un solo motor, la ficha leída de su referencia manda igual en los dos
 * formatos.
 */
class DrawHeadline
{
    /**
     * @param  list<string>  $lines  hasta 3 líneas
     * @param  array<string, mixed>|null  $style  la ficha leída de su referencia
     * @param  int  $bottomGuard  alto reservado abajo (pie de contacto, sello)
     */
    public function handle(
        ImageInterface $canvas,
        array $lines,
        ?BusinessCategory $category,
        ?array $style = null,
        int $canvasSize = 1080,
        int $bottomGuard = 210,
    ): void {
        $lines = self::cleanLines($lines);

        if ($lines === []) {
            return;
        }

        // El estilo se sortea, pero solo entre los que le pegan al rubro: una
        // barbería con bloques rosa de revista es justo el post que no se
        // publica. Ver BrandStyle.
        // $style es la FICHA leída de sus referencias; $look es el tratamiento
        // elegido para este post. Nombres distintos a propósito: mezclarlos
        // pisaba la ficha con una cadena y todo lo de abajo dejaba de verla.
        $styles = BrandStyle::headlineStylesFor($category, $style);
        $look = $styles[array_rand($styles)];

        $colors = BrandStyle::blockColorsFor($category, $style);
        $font = BrandStyle::headlineFontFor($category, $style);

        // Y dónde cae: lo que digan sus referencias, o sorteado si no hay ficha.
        $spots = ['center', 'bottom', 'top'];
        $spot = BrandStyle::headlineSpotFor($style) ?? $spots[array_rand($spots)];

        $size = in_array($look, ['clean', 'cursiva', 'resaltado'], true) ? 104 : 96;
        // La script agranda la línea y sus trazos vuelan más allá de la caja
        // normal de una letra: con el mismo aire de siempre, su cola tocaba
        // la línea de abajo cuando le tocaba ir arriba.
        $lineHeight = (int) round($size * ($look === 'cursiva' ? 1.42 : 1.22));
        $padX = 26;
        $padY = 12;

        // 'cursiva': una línea en letra script y el resto en la gruesa
        // condensada, apiladas — la tendencia que ella pidió imitar («una
        // montada sobre otra, arriba cursiva y abajo grueso, o al revés»).
        // Cuál línea es la script se sortea, así sale a veces arriba y a
        // veces abajo, como en sus referencias.
        $scriptLine = $look === 'cursiva' ? array_rand($lines) : null;

        // 'resaltado': una línea con la última palabra en el color de acento
        // y el resto en blanco. Otra referencia de video que ella señaló
        // directo — el mismo recurso se repetía en varios de sus subtítulos:
        // texto blanco grueso con una sola palabra o frase resaltada.
        $highlightLine = $look === 'resaltado' ? array_rand($lines) : null;

        $blockHeight = count($lines) * $lineHeight;

        $top = match ($spot) {
            'top' => 86,
            'bottom' => $canvasSize - $blockHeight - $bottomGuard,
            default => (int) round(($canvasSize - $blockHeight) / 2),
        };

        // Antetítulo: la línea chica que promete algo antes de que se lea el
        // titular. No va con 'blocks' — sobre bloques de color apilados no
        // tiene dónde apoyarse y queda flotando.
        $eyebrows = BrandStyle::eyebrows($category);

        if ($look !== 'blocks' && $eyebrows !== [] && random_int(0, 1) === 1) {
            $canvas->text($eyebrows[array_rand($eyebrows)], (int) round($canvasSize / 2), $top - 34, function (FontFactory $f) use ($colors): void {
                $f->filename(resource_path('fonts/Manrope.ttf'));
                $f->size(20);
                $f->color($colors[1]);
                $f->align('center');
                $f->valign('middle');
            });
        }

        // Y a veces la última línea va en el color de acento en vez de blanca:
        // es el truco de su referencia («que SIEMPRE quisiste» en dorado).
        // 'cursiva' y 'resaltado' quedan afuera: los dos ya tienen su propio
        // acento de color en otro lado, y sumar este encima queda cargado.
        $accentLast = ! in_array($look, ['blocks', 'cursiva', 'resaltado'], true) && random_int(0, 1) === 1;

        // La franja va de una sola pieza, antes del texto: dibujar una por
        // línea dejaba rayas de foto entre medio y se veía descuidado.
        if ($look === 'band') {
            $canvas->drawRectangle(0, $top - $padY, function ($rect) use ($blockHeight, $padY, $canvasSize): void {
                $rect->size($canvasSize, $blockHeight + ($padY * 2));
                $rect->background('rgba(10, 12, 18, 0.72)');
            });
        }

        foreach ($lines as $i => $line) {
            $centerY = $top + ($i * $lineHeight) + (int) round($lineHeight / 2);

            // La script se lee más chica que la condensada al mismo tamaño de
            // punto por sus trazos finos — se agranda para que pese igual.
            $isScriptLine = $look === 'cursiva' && $i === $scriptLine;
            $lineFont = match (true) {
                $isScriptLine => resource_path('fonts/Pacifico.ttf'),
                $look === 'cursiva' => resource_path('fonts/Anton.ttf'),
                default => $font,
            };
            $lineSize = $isScriptLine ? (int) round($size * 1.3) : $size;

            $width = self::textWidth($line, $lineSize, $lineFont);
            $blockColor = $colors[$i % count($colors)];

            if ($look === 'blocks') {
                $canvas->drawRectangle(
                    (int) round(($canvasSize - $width) / 2) - $padX,
                    $centerY - (int) round($size / 2) - $padY,
                    function ($rect) use ($width, $size, $padX, $padY, $blockColor): void {
                        $rect->size($width + ($padX * 2), $size + ($padY * 2));
                        $rect->background($blockColor);
                    },
                );
            } elseif ($look !== 'band') {
                // 'clean' y 'cursiva': sin fondo. Una sombra suave detrás para
                // que sobreviva sobre una foto clara, que en belleza son la mitad.
                $canvas->text($line, (int) round($canvasSize / 2) + 3, $centerY + 3, function (FontFactory $f) use ($lineSize, $lineFont): void {
                    $f->filename($lineFont);
                    $f->size($lineSize);
                    $f->color('rgba(0, 0, 0, 0.45)');
                    $f->align('center');
                    $f->valign('middle');
                });
            }

            $isLast = $i === count($lines) - 1;

            // En 'blocks' el texto va siempre SOBRE SU PROPIO bloque: si la
            // ficha trae un color claro (blanco, crema) entre los tres, el
            // texto blanco de siempre quedaba invisible sobre su propio
            // fondo — un bloque en blanco sin letra, visto en un post real
            // de Josean. Se decide por contraste, no a ciegas.
            $textColor = match (true) {
                $look === 'blocks' => self::textColorFor($blockColor),
                $accentLast && $isLast => $colors[1],
                default => '#ffffff',
            };

            if ($look === 'resaltado' && $i === $highlightLine) {
                $this->highlightedLine($canvas, $line, $centerY, $lineSize, $lineFont, $colors[1], $canvasSize);
            } else {
                $canvas->text($line, (int) round($canvasSize / 2), $centerY, function (FontFactory $f) use ($lineSize, $lineFont, $textColor): void {
                    $f->filename($lineFont);
                    $f->size($lineSize);
                    $f->color($textColor);
                    $f->align('center');
                    $f->valign('middle');
                });
            }
        }
    }

    /**
     * Una línea con la última palabra en el color de acento y el resto en
     * blanco, sin correr el conjunto del centro.
     *
     * Imagick no dibuja dos colores en un solo `text()`, así que se mide
     * cada parte por separado con textWidth() y se arma desde el borde
     * izquierdo del total — el mismo truco que ya usa el pie de contacto
     * para medir texto de verdad en vez de estimarlo.
     */
    private function highlightedLine(ImageInterface $canvas, string $line, int $centerY, int $size, string $font, string $accent, int $canvasSize): void
    {
        $words = preg_split('/\s+/u', trim($line)) ?: [$line];

        if (count($words) < 2) {
            // Una sola palabra: no queda "resto" en blanco, va entera en acento.
            $canvas->text($line, (int) round($canvasSize / 2), $centerY, function (FontFactory $f) use ($size, $font, $accent): void {
                $f->filename($font);
                $f->size($size);
                $f->color($accent);
                $f->align('center');
                $f->valign('middle');
            });

            return;
        }

        $highlight = array_pop($words);
        $before = implode(' ', $words).' ';

        $totalWidth = self::textWidth($line, $size, $font);
        $beforeWidth = self::textWidth($before, $size, $font);
        $left = (int) round(($canvasSize - $totalWidth) / 2);

        $canvas->text($before, $left, $centerY, function (FontFactory $f) use ($size, $font): void {
            $f->filename($font);
            $f->size($size);
            $f->color('#ffffff');
            $f->align('left');
            $f->valign('middle');
        });

        $canvas->text($highlight, $left + $beforeWidth, $centerY, function (FontFactory $f) use ($size, $font, $accent): void {
            $f->filename($font);
            $f->size($size);
            $f->color($accent);
            $f->align('left');
            $f->valign('middle');
        });
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
     * Blanco o casi-negro según qué tan clara sea la sombra que recibe: el
     * mismo criterio que usa cualquier lector de contraste (fórmula YIQ),
     * sin traer una librería aparte para una cuenta de tres líneas.
     */
    public static function textColorFor(string $hex): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) !== 6) {
            return '#ffffff';
        }

        [$r, $g, $b] = array_map(static fn (string $c): int => hexdec($c), str_split($hex, 2));

        $brightness = ($r * 299 + $g * 587 + $b * 114) / 1000;

        return $brightness > 150 ? '#111827' : '#ffffff';
    }

    /**
     * Cuánto mide de ancho ese texto, medido de verdad: se dibuja en un
     * lienzo aparte y se pregunta por su caja.
     */
    public static function textWidth(string $text, int $size, string $font): int
    {
        $draw = new \ImagickDraw;
        $draw->setFont($font);
        $draw->setFontSize($size);

        $metrics = (new \Imagick)->queryFontMetrics($draw, $text);

        return (int) round($metrics['textWidth']);
    }
}
