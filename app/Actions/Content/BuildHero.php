<?php

namespace App\Actions\Content;

use App\Models\Provider;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;

/**
 * Una sola foto a pantalla completa con el titular abajo, estilo revista.
 *
 * Copiado de una referencia que ella misma guardó: foto a sangre, la marca
 * arriba al centro en versalitas doradas, y abajo un antetítulo chico en
 * mayúsculas sobre un titular grande. El degradado oscuro de abajo no es
 * decoración — sin él, el texto blanco desaparece sobre una foto clara, que
 * en belleza es la mitad de las fotos.
 *
 * **El trabajo de ella no se toca**: la foto solo se recorta al cuadrado.
 * Ningún filtro ni corrección de color. Lo único que se dibuja encima es el
 * degradado y el texto.
 */
class BuildHero
{
    private const CANVAS = 1080;

    /** Alto del degradado, desde abajo. */
    private const SHADE = 560;

    public function __construct(
        private readonly DrawHeadline $drawHeadline,
    ) {}

    /**
     * @param  list<string>  $paths  se usa la primera
     * @param  list<string>  $headline  hasta 3 líneas
     */
    public function handle(Provider $provider, array $paths, array $headline = []): string
    {
        $manager = ImageManager::imagick();

        $canvas = $manager->read(Storage::disk('r2')->get(array_values($paths)[0]))
            ->cover(self::CANVAS, self::CANVAS);

        $this->shade($canvas);
        $this->mark($canvas, $provider);

        // El MISMO motor de titulares que el collage. Antes acá había una
        // versión propia escrita a mano —serif blanca, abajo a la izquierda,
        // siempre igual— que ignoraba por completo la ficha de estilo, así
        // que elegir una plantilla no cambiaba nada en este formato, que es
        // el más usado. Ella lo dijo directo: «sí agarra la foto de
        // referencia, sí hace todo, pero cuando me da el resultado no es
        // nada parecido».
        //
        // El resguardo de abajo es más chico que en el collage: acá no hay
        // pie de contacto ni sello en esa esquina.
        $this->drawHeadline->handle($canvas, $headline, $provider->business_category, $provider->content_style, self::CANVAS, 120);

        $key = sprintf('providers/%d/content/%s.jpg', $provider->id, (string) Str::ulid());

        Storage::disk('r2')->put($key, (string) $canvas->toJpeg(quality: 90), [
            'ContentType' => 'image/jpeg',
            'CacheControl' => 'public, max-age=31536000, immutable',
        ]);

        return $key;
    }

    /**
     * El degradado de abajo, con una máscara de opacidad de verdad.
     *
     * Intervention no expone degradados, así que se hace con Imagick a pelo
     * — mismo recurso que el recorte circular del logo (ver
     * BuildCollage::circularLogo). La primera versión dibujaba 40 tiras de
     * 14px con opacidad creciente a mano, y el salto de una a la siguiente
     * se veía como rayitas horizontales sobre un fondo oscuro — visto en un
     * post real (30-ago). Componer más tiras no alcanzaba: con cada tira
     * redondeando su propia opacidad a 8 bits por separado, muchas caían en
     * el mismo nivel y las rayas seguían ahí.
     *
     * Acá el degradado sale de una sola imagen calculada por Imagick de
     * punta a punta, sin ir componiendo franjas: un degradado lineal en
     * escala de grises como máscara, elevado al cuadrado para la misma
     * curva de siempre —arranca casi transparente y se cierra rápido abajo,
     * que es donde va el texto— y esa máscara controla la opacidad de un
     * relleno oscuro por COPYOPACITY.
     */
    private function shade(ImageInterface $canvas): void
    {
        $mask = new \Imagick;
        $mask->newPseudoImage(self::CANVAS, self::SHADE, 'gradient:black-white');
        $mask->evaluateImage(\Imagick::EVALUATE_POW, 2);
        $mask->evaluateImage(\Imagick::EVALUATE_MULTIPLY, 0.88);

        $fill = new \Imagick;
        $fill->newImage(self::CANVAS, self::SHADE, new \ImagickPixel('rgb(8,10,16)'));
        $fill->setImageAlphaChannel(\Imagick::ALPHACHANNEL_SET);
        $fill->compositeImage($mask, \Imagick::COMPOSITE_COPYOPACITY, 0, 0);
        $fill->setImageFormat('png');

        $overlay = ImageManager::imagick()->read($fill->getImageBlob());

        $canvas->place($overlay, 'bottom-left', 0, 0);
    }

    /**
     * La marca arriba al centro: su logo si lo tiene, su nombre si no.
     *
     * Mismo criterio que el sello del collage — ver BuildCollage::badge().
     */
    private function mark(ImageInterface $canvas, Provider $provider): void
    {
        $key = $provider->avatar_photo_url;

        if ($key !== null && $key !== '') {
            $size = 96;

            // El recorte circular vive en BuildCollage: una sola manera de
            // hacerlo para los dos formatos.
            $logo = ImageManager::imagick()->read(BuildCollage::circularLogo(Storage::disk('r2')->get($key), $size));

            $canvas->place($logo, 'top-left', (int) round((self::CANVAS - $size) / 2), 44);

            return;
        }

        $name = $provider->public_name ?? Provider::DEFAULT_BUSINESS_NAME;

        $canvas->text($this->spaced(Str::upper($name)), (int) round(self::CANVAS / 2), 62, function (FontFactory $font): void {
            $font->filename(resource_path('fonts/Manrope.ttf'));
            $font->size(19);
            $font->color('#e8c877');
            $font->align('center');
            $font->valign('middle');
        });
    }

    private function spaced(string $text): string
    {
        return implode("\u{2009}", mb_str_split($text));
    }
}
