<?php

namespace App\Actions\Content;

use App\Models\ContentAsset;
use App\Models\Provider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\ImageManager;

/**
 * Una historia de Instagram armada con la receta de @mafer.glowupdigital.
 *
 * **De dónde sale.** Ella trajo siete videos de esa cuenta —la misma que le
 * vendió el pack— y pidió que se analizaran. Tres son tutoriales hablados y
 * describen la MISMA receta paso por paso, lo que la vuelve confiable. Los
 * otros cuatro son solo música.
 *
 * **Y explica por qué nada terminaba de cuadrar:** son HISTORIAS verticales,
 * 1080x1920, no los posts cuadrados que se venían armando.
 *
 * La receta, en sus palabras:
 *
 * 1. Una foto del trabajo de fondo, a pantalla completa: «para que sea el
 *    protagonista de la historia».
 * 2. Una transparencia encima: «para poder trabajar sobre ella sin que la
 *    imagen se vea tan saturada».
 * 3. Las fotos del resultado recortadas EN CÍRCULO, a un costado, una debajo
 *    de la otra y de distinto tamaño: «para destacar el resultado y darle
 *    balance».
 * 4. Un difuminado de color de marca en una esquina, y el criterio es
 *    explícito: que COMBINE con la foto, no un color al azar.
 * 5. La frase principal GIRADA EN VERTICAL al costado: «aquí está el toque
 *    diferente, en lugar de colocarla normal, la giraría».
 * 6. Sombras detrás de las fotos, «para darles profundidad».
 *
 * **Lo mejor de todo es lo que NO tiene.** Ella había señalado, con razón,
 * que la app no puede saber si una foto es el antes, el proceso o el
 * resultado, y que etiquetarlo mal arruina el post. En esta receta ese
 * problema no existe: en el tutorial de antes y después no hay ninguna
 * etiqueta que lo diga. Lo dice la estructura — el fondo es el proceso, los
 * círculos son el resultado. No hay nada que adivinar porque no hay nada que
 * afirmar.
 */
class BuildStory
{
    private const W = 1080;
    private const H = 1920;

    /** Cuánto tapa el velo blanco de arriba de todo. */
    private const VEIL = 0.42;

    public function __construct(
        private readonly StampAsset $stamp,
        private readonly PickAsset $pick,
    ) {}

    /**
     * @param  list<string>  $paths  la primera va de fondo; las demás, en círculo
     * @return string la clave de R2
     */
    public function handle(Provider $provider, array $paths, ?ContentAsset $phrase = null, ?string $colorHex = null): string
    {
        $paths = array_values($paths);
        $manager = ImageManager::imagick();

        // 1. El fondo: la foto a pantalla completa.
        $canvas = $manager->read(Storage::disk('r2')->get($paths[0]))->cover(self::W, self::H);

        // 2. La transparencia, para poder trabajar encima sin saturar.
        $canvas->place(
            $manager->create(self::W, self::H)->fill('rgba(255,255,255,'.self::VEIL.')'),
            'top-left',
            0,
            0,
        );

        // 3. Las fotos del resultado, en círculo y a un costado.
        $this->circles($canvas, $manager, array_slice($paths, 1));

        // 4. El difuminado de color, arriba.
        $this->haze($canvas, $manager, $colorHex);

        // 5. La frase, girada en vertical.
        if ($phrase !== null) {
            $this->verticalPhrase($canvas, $manager, $phrase);
        }

        $key = sprintf('providers/%d/stories/%s.jpg', $provider->id, (string) Str::ulid());

        Storage::disk('r2')->put($key, (string) $canvas->toJpeg(quality: 90), [
            'ContentType' => 'image/jpeg',
            'CacheControl' => 'public, max-age=31536000, immutable',
        ]);

        return $key;
    }

    /**
     * Las fotos de detalle, recortadas en círculo y alineadas a la derecha.
     *
     * De distinto tamaño a propósito: «una más grande que la otra... para
     * darle un poco más de balance a la historia». Con una sola foto se
     * repite la del fondo, que es lo que hace el primer tutorial.
     *
     * @param  list<string>  $paths
     */
    private function circles(ImageInterface $canvas, ImageManager $manager, array $paths): void
    {
        if ($paths === []) {
            return;
        }

        // Dos como mucho: en los tres tutoriales nunca hay más.
        $usar = array_slice($paths, 0, 2);

        $medidas = count($usar) === 1
            ? [['d' => 560, 'x' => 430, 'y' => 700]]
            : [
                ['d' => 520, 'x' => 470, 'y' => 560],
                ['d' => 400, 'x' => 560, 'y' => 1090],
            ];

        foreach ($usar as $i => $path) {
            $medida = $medidas[$i];

            // 6. La sombra primero, detrás: «para darles profundidad».
            $canvas->place(
                $manager->read($this->softShadow($medida['d'])),
                'top-left',
                $medida['x'] - 14,
                $medida['y'] + 10,
            );

            $canvas->place(
                $manager->read($this->circleCrop(Storage::disk('r2')->get($path), $medida['d'])),
                'top-left',
                $medida['x'],
                $medida['y'],
            );
        }
    }

    /**
     * El difuminado de color arriba.
     *
     * El criterio lo da ella misma: «café claro porque COMBINA con los tonos
     * de la historia». Un color al azar es justo lo que no hay que hacer.
     *
     * Se toma el tono del TRABAJO —el color del esmalte o del tinte, que ya
     * está confirmado por ella al subir— y no el promedio de la foto. Eso se
     * probó primero y salió mal: en una foto de uñas verdes el promedio daba
     * naranja, porque manda la piel. El mismo problema de siempre.
     */
    private function haze(ImageInterface $canvas, ImageManager $manager, ?string $colorHex): void
    {
        $sombras = ContentAsset::query()
            ->where('kind', 'sombra')
            ->whereNotNull('hue')
            ->get();

        if ($sombras->isEmpty()) {
            return;
        }

        $tono = $colorHex === null ? null : $this->hexHue($colorHex);

        $elegida = $tono === null
            ? $sombras->random()
            : $sombras->sortBy(fn (ContentAsset $a): int => min(
                abs($a->hue - $tono),
                360 - abs($a->hue - $tono),
            ))->first();

        try {
            $velo = $manager->read(Storage::disk('r2')->get($elegida->path))->resize(self::W, (int) round(self::H * 0.45));
        } catch (\Throwable) {
            return;
        }

        // Del pack vienen oscuros abajo; acá el difuminado va arriba.
        $velo->flip();

        $canvas->place($velo, 'top-left', 0, 0);
    }

    /**
     * La frase girada 90°, pegada al borde izquierdo.
     *
     * Es el rasgo que ella marca como propio de este estilo: «aquí está el
     * toque diferente, en lugar de colocarla normal, la giraría y la
     * colocaría de forma vertical al costado de la imagen».
     */
    private function verticalPhrase(ImageInterface $canvas, ImageManager $manager, ContentAsset $phrase): void
    {
        try {
            $arte = new Imagick;
            $arte->readImageBlob(Storage::disk('r2')->get($phrase->path));
        } catch (\Throwable) {
            return;
        }

        // Se recorta al dibujo ANTES de girar: girar el lienzo entero de
        // 1080 dejaría la frase perdida en el medio de un cuadrado vacío.
        $arte->trimImage(0);
        $arte->setImagePage(0, 0, 0, 0);
        $arte->rotateImage(new ImagickPixel('transparent'), -90);

        $alto = (int) round(self::H * 0.46);
        $escala = $alto / max($arte->getImageHeight(), 1);
        $arte->resizeImage((int) round($arte->getImageWidth() * $escala), $alto, Imagick::FILTER_LANCZOS, 1);

        $canvas->place(
            $manager->read($arte->getImageBlob()),
            'top-left',
            70,
            (int) round((self::H - $alto) / 2),
        );

        $arte->destroy();
    }

    /**
     * Una foto recortada en círculo, del diámetro pedido.
     *
     * **La máscara va transparente afuera, no negra.** Con fondo negro y
     * COPYOPACITY el recorte no se aplicaba y la foto salía cuadrada: en
     * ImageMagick 7 esa operación copia el ALFA de la máscara, y una máscara
     * negra opaca es alfa 1 en todos lados. Con una foto sintética parecía
     * funcionar, con una foto de verdad no — por eso se escapó. Se usa DSTIN,
     * que es la que dice lo que se quiere: quedarse con el destino solo donde
     * la máscara tiene alfa.
     */
    private function circleCrop(string $binary, int $d): string
    {
        $foto = new Imagick;
        $foto->readImageBlob($binary);
        $foto->setImageFormat('png');
        $foto->cropThumbnailImage($d, $d);

        $mascara = new Imagick;
        $mascara->newImage($d, $d, new ImagickPixel('transparent'), 'png');

        $draw = new ImagickDraw;
        $draw->setFillColor(new ImagickPixel('white'));
        $draw->circle($d / 2, $d / 2, $d / 2, 0);
        $mascara->drawImage($draw);

        $foto->setImageAlphaChannel(Imagick::ALPHACHANNEL_OPAQUE);
        $foto->compositeImage($mascara, Imagick::COMPOSITE_DSTIN, 0, 0);

        $salida = $foto->getImageBlob();

        $foto->destroy();
        $mascara->destroy();

        return $salida;
    }

    /** Un disco negro difuso, para apoyar detrás de un círculo. */
    private function softShadow(int $d): string
    {
        $sombra = new Imagick;
        $sombra->newImage($d + 40, $d + 40, new ImagickPixel('transparent'), 'png');

        $draw = new ImagickDraw;
        $draw->setFillColor(new ImagickPixel('rgba(0,0,0,0.34)'));
        $draw->circle(($d + 40) / 2, ($d + 40) / 2, ($d + 40) / 2, 20);
        $sombra->drawImage($draw);
        $sombra->blurImage(0, 18);

        $salida = $sombra->getImageBlob();
        $sombra->destroy();

        return $salida;
    }

    /** El tono de un color en grados, o null si es gris. */
    private function hexHue(string $hex): ?int
    {
        [$r, $g, $b] = ColorNames::rgb($hex);

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);

        if ($max === $min || $max === 0 || ($max - $min) / $max < 0.12) {
            return null;
        }

        $grados = match (true) {
            $max === $r => 60 * fmod(($g - $b) / ($max - $min), 6),
            $max === $g => 60 * ((($b - $r) / ($max - $min)) + 2),
            default => 60 * ((($r - $g) / ($max - $min)) + 4),
        };

        return (int) round(fmod($grados + 360, 360));
    }
}
