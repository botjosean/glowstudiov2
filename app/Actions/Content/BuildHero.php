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
        private readonly StampAsset $stamp,
        private readonly PickAsset $pick,
    ) {}

    /**
     * Estampa la frase del pack y avisa si pudo.
     *
     * La posición sale de la ficha de ella si la tiene; si no, se sortea —
     * pero una sola vez, porque la claridad se mide en la MISMA franja donde
     * va a caer, y sortear de nuevo después dejaría la tinta elegida para un
     * lado y la frase en otro.
     */
    private function stampHeadline(ImageInterface $canvas, Provider $provider, ?string $serviceSlug): bool
    {
        if (! $this->pick->hasPack($provider)) {
            return false;
        }

        $spot = BrandStyle::headlineSpotFor($provider->content_style)
            ?? ['center', 'bottom', 'top'][array_rand(['center', 'bottom', 'top'])];

        $elegida = $this->stamp->pick($canvas, $this->pick->phrases($provider, $serviceSlug), $spot);

        if ($elegida === null) {
            return false;
        }

        // Una foto de brillo intermedio no le da contraste a ninguna de las
        // dos tintas: ni la clara ni la oscura se despegan del fondo. Ahí
        // —y solo ahí— se apoya una sombra del pack debajo de la frase,
        // para no ensuciar las fotos que no la necesitan.
        $brillo = $this->stamp->brightness($canvas, $spot);

        if ($brillo > 0.40 && $brillo < 0.66) {
            $sombra = $this->pick->ofKind('sombra')->where('ink', 'dark');

            if ($sombra->isNotEmpty()) {
                $this->stamp->wash($canvas, $sombra->random(), $spot);
            }
        }

        $this->stamp->handle($canvas, $elegida, $spot);

        return true;
    }

    /**
     * La foto sola, al cuadrado, sin una sola letra encima.
     *
     * Son las láminas 2 en adelante del carrusel. Ella lo mostró con un
     * ejemplo: «una portada con letras bonitas, buena frase, seguido de
     * fotos». Después de la portada, el trabajo se muestra y ya — ni sombra,
     * ni sello, ni pie de contacto tapando las uñas.
     */
    public function plain(Provider $provider, string $path): string
    {
        $canvas = ImageManager::imagick()
            ->read(Storage::disk('r2')->get($path))
            ->cover(self::CANVAS, self::CANVAS);

        return $this->store($provider, $canvas);
    }

    /**
     * @param  list<string>  $paths  se usa la primera
     * @param  list<string>  $headline  hasta 3 líneas
     */
    public function handle(Provider $provider, array $paths, array $headline = [], ?string $serviceSlug = null): string
    {
        $manager = ImageManager::imagick();

        $canvas = $manager->read(Storage::disk('r2')->get(array_values($paths)[0]))
            ->cover(self::CANVAS, self::CANVAS);

        // Con el pack cargado, el titular se ESTAMPA en vez de dibujarse.
        //
        // Las frases del pack las diseñó una persona; el motor de abajo las
        // compone con tipografías. Ella comparó los dos resultados sin
        // saberlo, post tras post, y siempre dijo lo mismo: «no son las
        // letras, no logras llegar al punto». Cuando hay pack, gana el pack.
        //
        // El motor viejo no se borra: sigue siendo el que atiende a los
        // rubros que el pack no cubre.
        if ($this->stampHeadline($canvas, $provider, $serviceSlug)) {
            $this->mark($canvas, $provider);

            return $this->store($provider, $canvas);
        }

        // El MISMO motor de titulares que el collage. Antes acá había una
        // versión propia escrita a mano —serif blanca, abajo a la izquierda,
        // siempre igual— que ignoraba por completo la ficha de estilo, así
        // que elegir una plantilla no cambiaba nada en este formato, que es
        // el más usado. Ella lo dijo directo: «sí agarra la foto de
        // referencia, sí hace todo, pero cuando me da el resultado no es
        // nada parecido».
        //
        // Se decide ANTES de pintar nada para poder poner la sombra justo
        // donde va a caer el texto. El resguardo de abajo es más chico que
        // en el collage: acá no hay pie de contacto ni sello en esa esquina.
        $plan = $this->drawHeadline->plan($headline, $provider->business_category, $provider->content_style, self::CANVAS, 120);

        $this->shade($canvas, $plan);
        $this->mark($canvas, $provider);

        if ($plan !== null) {
            $this->drawHeadline->draw($canvas, $plan, self::CANVAS);
        }

        return $this->store($provider, $canvas);
    }

    /**
     * Guarda la lámina y devuelve su clave de R2. Compartido por la portada
     * y por las fotos limpias.
     */
    private function store(Provider $provider, ImageInterface $canvas): string
    {
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
     * curva de siempre —arranca casi transparente y se cierra rápido hacia
     * el texto— y esa máscara controla la opacidad de un relleno oscuro por
     * COPYOPACITY.
     *
     * **La sombra SIGUE AL TEXTO.** Antes estaba clavada abajo mientras el
     * titular caía donde dijera la ficha: con una referencia que pide el
     * texto al centro —la de Vane, comprobado en producción— el titular
     * quedaba flotando sobre unas uñas claras sin nada que lo sostuviera, y
     * la sombra se gastaba abajo donde no había nada. Los tratamientos que
     * traen su propio fondo (bloques, franja) no la necesitan.
     *
     * @param  array<string, mixed>|null  $plan
     */
    private function shade(ImageInterface $canvas, ?array $plan): void
    {
        // Sin titular, o con uno que ya trae su propio fondo: la sombra de
        // siempre abajo, que además le da peso al pie de la foto.
        if ($plan === null || ! DrawHeadline::needsScrim($plan)) {
            $this->gradient($canvas, self::CANVAS - self::SHADE, self::SHADE, 'up');

            return;
        }

        [$top, $height] = DrawHeadline::band($plan);

        match ($plan['spot']) {
            // Arriba y abajo: un degradado que se cierra hacia el borde,
            // como el de siempre.
            'top' => $this->gradient($canvas, 0, max($top + $height + 120, 320), 'down'),
            'bottom' => $this->gradient($canvas, self::CANVAS - self::SHADE, self::SHADE, 'up'),
            // Al centro no sirve un degradado de borde: hace falta una franja
            // que se funda por arriba Y por abajo, para no cortar la foto con
            // un filo recto.
            default => $this->centreScrim($canvas, $top, $height),
        };
    }

    /**
     * Un degradado que se cierra hacia arriba ('up': oscuro abajo) o hacia
     * abajo ('down': oscuro arriba).
     */
    private function gradient(ImageInterface $canvas, int $y, int $height, string $towards, float $alpha = 0.88, float $curve = 2.0): void
    {
        $mask = new \Imagick;
        // 'up' = se oscurece hacia abajo; 'down' = se aclara hacia abajo.
        $mask->newPseudoImage(self::CANVAS, $height, $towards === 'up' ? 'gradient:black-white' : 'gradient:white-black');
        $mask->evaluateImage(\Imagick::EVALUATE_POW, $curve);
        $mask->evaluateImage(\Imagick::EVALUATE_MULTIPLY, $alpha);

        $fill = new \Imagick;
        $fill->newImage(self::CANVAS, $height, new \ImagickPixel('rgb(8,10,16)'));
        $fill->setImageAlphaChannel(\Imagick::ALPHACHANNEL_SET);
        $fill->compositeImage($mask, \Imagick::COMPOSITE_COPYOPACITY, 0, 0);
        $fill->setImageFormat('png');

        $canvas->place(ImageManager::imagick()->read($fill->getImageBlob()), 'top-left', 0, max($y, 0));
    }

    /**
     * La franja del centro: se funde por arriba, se mantiene por el medio y
     * se funde por abajo, para que el titular tenga sobre qué apoyarse sin
     * partir la foto en dos con un borde duro.
     */
    private function centreScrim(ImageInterface $canvas, int $top, int $height): void
    {
        $fade = 170;
        $alpha = 0.62;

        $medio = max($height + 90, 1);
        $desde = max($top - 45, 0);

        // Arriba de la franja: transparente arriba, opaco abajo ('up'), para
        // entrar en la sombra sin un filo. Curva lineal y no cuadrática: en
        // 170px la cuadrática se ve como un corte.
        $this->gradient($canvas, max($desde - $fade, 0), min($fade, $desde), 'up', $alpha, 1.0);

        $solido = new \Imagick;
        $solido->newImage(self::CANVAS, $medio, new \ImagickPixel('rgba(8,10,16,'.$alpha.')'));
        $solido->setImageFormat('png');
        $canvas->place(ImageManager::imagick()->read($solido->getImageBlob()), 'top-left', 0, $desde);

        // Y abajo, al revés: opaco arriba, transparente abajo.
        $this->gradient($canvas, $desde + $medio, $fade, 'down', $alpha, 1.0);
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
