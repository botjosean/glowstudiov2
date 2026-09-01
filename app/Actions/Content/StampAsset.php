<?php

namespace App\Actions\Content;

use App\Models\ContentAsset;
use Illuminate\Support\Collection;
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
 * **Se estampan tal cual, sin recortar ni reescalar.** La primera versión
 * recortaba la pieza a su dibujo y la recolocaba arriba, al centro o abajo
 * según la ficha. Eso rompía las composiciones grandes: una frase que viene
 * con un círculo alrededor —pensada para enmarcar la foto entera— salía
 * recortada y agrandada hasta tapar el trabajo. Visto en el perfil de
 * Patricia. El pack ya viene armado sobre un lienzo de 1080, igual que el
 * post: la posición la decidió el diseñador y se respeta.
 *
 * **La tinta sí se elige.** El pack trae cada frase en oscuro y en claro, y
 * cuál usar no se sortea: se mide qué tan clara es la zona de la foto donde
 * cae el dibujo. Eso es lo que hace que se lea igual sobre unas uñas blancas
 * que sobre una mesa negra, sin taparle la foto con una sombra.
 */
class StampAsset
{
    private const CANVAS = 1080;

    /** Pinta la pieza en la posición en que fue diseñada. */
    public function handle(ImageInterface $canvas, ContentAsset $asset): void
    {
        $arte = $this->read($asset->path);

        if ($arte === null) {
            return;
        }

        // A 1080 si viniera de otro tamaño; el pack ya está en esa medida,
        // así que en la práctica no toca nada.
        if ($arte->width() !== self::CANVAS || $arte->height() !== self::CANVAS) {
            $arte->resize(self::CANVAS, self::CANVAS);
        }

        $canvas->place($arte, 'top-left', 0, 0);
    }

    /**
     * Una sombra del pack, para cuando la foto no le da contraste a ninguna
     * de las dos tintas.
     *
     * Se pone entera y se da vuelta si el dibujo cae arriba: las sombras del
     * pack vienen oscuras abajo, y sin dar vuelta el velo quedaría del lado
     * contrario al texto.
     */
    public function wash(ImageInterface $canvas, ContentAsset $shadow, ContentAsset $under): void
    {
        $velo = $this->read($shadow->path);

        if ($velo === null) {
            return;
        }

        $velo->resize(self::CANVAS, self::CANVAS);

        if ($under->box_y + ($under->box_h / 2) < self::CANVAS / 2) {
            $velo->flip();
        }

        $canvas->place($velo, 'top-left', 0, 0);
    }

    /**
     * Elige la frase y, dentro de ella, la tinta que se va a leer.
     *
     * Primero se sortea QUÉ dice —entre los textos distintos que hay— y
     * recién después se elige la tinta midiendo la foto justo donde ese
     * dibujo va a caer. Al revés no se puede: sin saber cuál es la pieza no
     * se sabe qué parte de la foto medir, y medir el promedio de todo hace
     * que una foto oscura arriba y clara abajo dé "gris" y ninguna tinta
     * contraste.
     *
     * @param  Collection<int, ContentAsset>  $candidatas
     */
    public function pick(ImageInterface $canvas, Collection $candidatas): ?ContentAsset
    {
        if ($candidatas->isEmpty()) {
            return null;
        }

        // Las que no tienen texto leído se agrupan por su ruta, así cada una
        // es su propio grupo y el sorteo sigue funcionando.
        $porTexto = $candidatas->groupBy(fn (ContentAsset $a): string => $a->slug ?? $a->path);
        $grupo = $porTexto->get($porTexto->keys()->random());

        $muestra = $grupo->first();
        $tinta = ContentAsset::inkFor($this->brightness($canvas, $muestra));

        $encaja = $grupo->where('ink', $tinta);

        return $encaja->isNotEmpty() ? $encaja->random() : $grupo->random();
    }

    /**
     * Qué tan clara es la foto justo debajo de esta pieza, de 0 a 1.
     *
     * Se mide sobre el recuadro del dibujo y no sobre la foto entera: lo que
     * importa es lo que queda detrás de las letras.
     */
    public function brightness(ImageInterface $canvas, ContentAsset $asset): float
    {
        $ancho = max($asset->box_w, 1);
        $alto = max($asset->box_h, 1);
        $x = min($asset->box_x, max($canvas->width() - $ancho, 0));
        $y = min($asset->box_y, max($canvas->height() - $alto, 0));

        $muestra = clone $canvas;
        $muestra->crop(
            min($ancho, $canvas->width()),
            min($alto, $canvas->height()),
            max($x, 0),
            max($y, 0),
        );

        // Una miniatura alcanza y de sobra: interesa el promedio, no el
        // detalle, y sobre el recorte entero serían cientos de miles de
        // píxeles por post.
        $muestra->resize(24, 24);

        $suma = 0.0;

        for ($px = 0; $px < 24; $px++) {
            for ($py = 0; $py < 24; $py++) {
                $c = $muestra->pickColor($px, $py)->toArray();
                $suma += ($c[0] * 0.299 + $c[1] * 0.587 + $c[2] * 0.114) / 255;
            }
        }

        return $suma / (24 * 24);
    }

    private function read(string $path): ?ImageInterface
    {
        try {
            $bytes = Storage::disk('r2')->get($path);
        } catch (\Throwable) {
            return null;
        }

        return $bytes === null || $bytes === '' ? null : ImageManager::imagick()->read($bytes);
    }
}
