<?php

namespace App\Actions\Content;

use App\Models\ContentAsset;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\ImageManager;

/**
 * Estampa una pieza del pack encima del lienzo, eligiendo la versión que se
 * lee sobre esa foto.
 *
 * **Por qué esto reemplaza al dibujado con tipografías.** El motor de
 * titulares (ver DrawHeadline) compone las letras a mano: elige fuente,
 * cuerpo, color, sombra. Funciona, pero ella lo miró post tras post y dijo
 * siempre lo mismo — «no son las letras, no logras llegar al punto». Tenía
 * razón: estas frases las diseñó una persona, y con código no se llega ahí.
 *
 * El pack trae cada frase en tinta oscura y en tinta clara. Cuál se usa no
 * se sortea: se mide qué tan clara es la zona de la foto donde va a caer, y
 * se elige la que contrasta. Ese detalle es lo que hace que el titular se
 * lea igual sobre unas uñas blancas que sobre una mesa negra, sin necesidad
 * de meterle una sombra encima que ensucia la foto.
 */
class StampAsset
{
    private const CANVAS = 1080;

    /** Cuánto del ancho puede ocupar la pieza, como mucho. */
    private const MAX_WIDTH = 0.82;

    /**
     * @param  'top'|'center'|'bottom'  $spot
     */
    public function handle(ImageInterface $canvas, ContentAsset $asset, string $spot = 'center'): void
    {
        $arte = $this->art($asset);

        if ($arte === null) {
            return;
        }

        [$ancho, $alto] = [$arte->width(), $arte->height()];
        $x = (int) round((self::CANVAS - $ancho) / 2);
        $y = match ($spot) {
            'top' => (int) round(self::CANVAS * 0.10),
            'bottom' => self::CANVAS - $alto - (int) round(self::CANVAS * 0.16),
            default => (int) round((self::CANVAS - $alto) / 2),
        };

        $canvas->place($arte, 'top-left', $x, max($y, 0));
    }

    /**
     * La pieza recortada a su dibujo y escalada para que entre.
     *
     * Se recorta porque el arte viene centrado en un lienzo de 1080 casi
     * vacío: sin recortar no se puede subir ni bajar sin mover también el
     * aire de alrededor.
     */
    private function art(ContentAsset $asset): ?ImageInterface
    {
        try {
            $bytes = Storage::disk('r2')->get($asset->path);
        } catch (\Throwable) {
            return null;
        }

        if ($bytes === null || $bytes === '') {
            return null;
        }

        $arte = ImageManager::imagick()->read($bytes);

        if ($asset->box_w > 0 && $asset->box_h > 0) {
            $arte->crop($asset->box_w, $asset->box_h, $asset->box_x, $asset->box_y);
        }

        $tope = (int) round(self::CANVAS * self::MAX_WIDTH);

        if ($arte->width() > $tope) {
            $arte->scaleDown(width: $tope);
        }

        return $arte;
    }

    /**
     * Elige entre las candidatas la que se va a leer sobre esta foto.
     *
     * Mide la claridad de la franja donde va a caer, no de la foto entera:
     * una foto puede ser oscura arriba y clarísima abajo, y lo que importa
     * es lo que queda justo detrás de las letras.
     *
     * @param  \Illuminate\Support\Collection<int, ContentAsset>  $candidatas
     * @param  'top'|'center'|'bottom'  $spot
     */
    public function pick(ImageInterface $canvas, $candidatas, string $spot = 'center'): ?ContentAsset
    {
        if ($candidatas->isEmpty()) {
            return null;
        }

        $tinta = ContentAsset::inkFor($this->brightness($canvas, $spot));

        // Las de color —un marco dorado, un esmalte rosa— no entran en la
        // elección por contraste: no son ni claras ni oscuras, así que si
        // se mezclaran ganarían por azar y a veces no se leerían.
        $encaja = $candidatas->where('ink', $tinta);

        if ($encaja->isNotEmpty()) {
            return $encaja->random();
        }

        return $candidatas->random();
    }

    /**
     * Qué tan clara es la franja donde va a caer la pieza, de 0 a 1.
     *
     * @param  'top'|'center'|'bottom'  $spot
     */
    public function brightness(ImageInterface $canvas, string $spot = 'center'): float
    {
        $alto = $canvas->height();
        $franja = (int) round($alto * 0.34);
        $desde = match ($spot) {
            'top' => 0,
            'bottom' => $alto - $franja,
            default => (int) round(($alto - $franja) / 2),
        };

        $muestra = clone $canvas;
        $muestra->crop($canvas->width(), $franja, 0, max($desde, 0));

        // Una miniatura alcanza y de sobra: interesa el promedio, no el
        // detalle, y sobre el recorte entero serían más de trescientos mil
        // píxeles por post.
        $muestra->resize(24, 24);

        $suma = 0.0;
        $n = 0;

        for ($x = 0; $x < 24; $x++) {
            for ($y = 0; $y < 24; $y++) {
                $c = $muestra->pickColor($x, $y)->toArray();
                $suma += ($c[0] * 0.299 + $c[1] * 0.587 + $c[2] * 0.114) / 255;
                $n++;
            }
        }

        return $n === 0 ? 0.5 : $suma / $n;
    }
}
