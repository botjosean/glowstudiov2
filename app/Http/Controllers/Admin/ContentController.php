<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Content\BuildCarousel;
use App\Actions\Content\BuildCollage;
use App\Actions\Content\BuildColorBlock;
use App\Actions\Content\BuildColorCombo;
use App\Actions\Content\BuildHero;
use App\Actions\Content\ColorNames;
use App\Actions\Content\ComposeChosen;
use App\Actions\Content\DetectDesignedPhoto;
use App\Actions\Content\PhraseSafety;
use App\Actions\Content\ReadPhotoColor;
use App\Actions\Content\ReadReferenceStyle;
use App\Actions\Content\StickerTrays;
use App\Actions\Content\StoreReferenceVideo;
use App\Actions\Content\SuggestReferenceNote;
use App\Actions\Content\WriteCaption;
use App\Actions\Media\DeleteProviderImage;
use App\Actions\Media\StoreProviderImage;
use App\Enums\ContentPurpose;
use App\Enums\ImageVariant;
use App\Enums\PostLayout;
use App\Enums\UploadKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreContentUploadRequest;
use App\Models\ContentAsset;
use App\Models\ContentPost;
use App\Models\ContentUpload;
use App\Support\MediaUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El Taller de Contenido: ella sube sus fotos del día y la app le devuelve
 * el post armado, con descripción y hashtags, listo para publicar a mano.
 *
 * Publicar automático queda fuera a propósito por ahora: mientras se publique
 * a mano, no hace falta ningún permiso de Instagram ni trámite con Meta.
 */
class ContentController extends Controller
{
    public function __construct(
        private readonly StoreProviderImage $store,
        private readonly StoreReferenceVideo $video,
        private readonly DeleteProviderImage $deleteImage,
        private readonly BuildCollage $collage,
        private readonly BuildHero $hero,
        private readonly BuildCarousel $carousel,
        private readonly ReadReferenceStyle $style,
        private readonly SuggestReferenceNote $noteSuggestions,
        private readonly WriteCaption $caption,
        private readonly DetectDesignedPhoto $detectDesigned,
        private readonly ReadPhotoColor $photoColor,
        private readonly BuildColorBlock $colorBlock,
        private readonly BuildColorCombo $colorCombo,
        private readonly ComposeChosen $compose,
        private readonly StickerTrays $trays,
    ) {}

    public function index(Request $request): Response
    {
        $provider = $request->user()->provider;

        $waitingCount = ContentUpload::query()
            ->where('provider_id', $provider->id)
            ->waiting()
            ->count();

        return Inertia::render('Admin/Contenido', [
            'providerName' => $provider->public_name,
            'avatarPhoto' => MediaUrl::resolve($provider->avatar_photo_url),
            'contentStyle' => $provider->content_style,
            // Las que esperan que ella elija un modelo. Con su color, para
            // "Fondo de color": sin verlo, dos tandas subidas por separado se
            // pueden mezclar sin que se note hasta ver el post armado — pasó
            // de verdad, una tanda con una uña rosa metida entre unas negras.
            'waiting' => ContentUpload::query()
                ->where('provider_id', $provider->id)
                ->waiting()
                // Por id y no por fecha: dos fotos subidas en el mismo
                // segundo empatan y el orden sale al azar.
                ->orderBy('id')
                ->get()
                ->map(fn (ContentUpload $upload): array => [
                    'id' => $upload->id,
                    'url' => MediaUrl::resolve($upload->path),
                    'colorName' => $upload->color_name,
                    'colorHex' => $upload->color_hex,
                ])->values()->all(),
            // Las últimas fotos "para editar", usadas o no. Antes, apenas una
            // foto se usaba en un post, desaparecía de la pantalla sin dejar
            // rastro — no había forma de volver a intentar con ella salvo
            // subiéndola de nuevo desde el teléfono. Ella lo señaló directo:
            // «no hay como para ver la foto reciente, para volver a hacer el
            // contenido con las fotos».
            'recentEdits' => ContentUpload::query()
                ->where('provider_id', $provider->id)
                ->where('purpose', ContentPurpose::Edit->value)
                ->where('kind', UploadKind::Image->value)
                ->latest()
                // Diez y no veinticuatro: con todas, la cuadrícula ocupaba
                // cinco filas de miniaturas diminutas y empujaba el resto de
                // la pantalla fuera de vista. Ella lo pidió así mirándolo:
                // «con 10 cuadrículas es suficiente para que todo se vaya
                // acomodando y se vea más bonito».
                ->limit(10)
                ->get()
                ->map(fn (ContentUpload $upload): array => [
                    'id' => $upload->id,
                    'url' => MediaUrl::resolve($upload->path),
                    'used' => $upload->used_at !== null,
                ])->values()->all(),
            // La lista entera, no solo el conteo: sin verlas no hay forma de
            // saber si lo que subió llegó bien, ni de quitar una equivocada.
            'references' => ContentUpload::query()
                ->where('provider_id', $provider->id)
                ->references()
                ->latest()
                ->get()
                ->map(fn (ContentUpload $upload): array => [
                    'id' => $upload->id,
                    'url' => MediaUrl::resolve($upload->path),
                    'kind' => $upload->kind->value,
                    'note' => $upload->note,
                ])->values()->all(),
            'posts' => ContentPost::query()
                ->where('provider_id', $provider->id)
                ->latest()
                ->limit(12)
                ->get()
                ->map(fn (ContentPost $post): array => [
                    'id' => $post->id,
                    'url' => MediaUrl::resolve($post->path),
                    // Los posts armados antes de que existieran los carruseles
                    // no tienen láminas: su portada es todo el post.
                    'slides' => collect($post->slides ?? [$post->path])
                        ->map(fn (string $slide): string => MediaUrl::resolve($slide))
                        ->values()->all(),
                    'caption' => $post->caption,
                    'hashtags' => $post->hashtags ?? [],
                    'rating' => $post->rating,
                ])->values()->all(),
            // Las piezas para poner a mano, agrupadas en bandejas.
            //
            // Existe porque hay algo que la app NO puede saber: si la foto es
            // el antes, el proceso o el resultado. Ella lo dijo mirando
            // posts reales — «al otro le pones proceso y de repente no, ya
            // eso es terminado». Acá elige ella, que sí lo sabe.
            'trays' => $this->trays->handle($provider),
            // Qué modelos puede armar ahora mismo, según cuántas fotos tiene
            // esperando. Ofrecer uno que no cuadra solo produce un error.
            'layouts' => collect(PostLayout::cases())
                ->map(fn (PostLayout $l): array => [
                    'value' => $l->value,
                    'min' => $l->minPhotos(),
                    'uses' => $l->photosToUse($waitingCount),
                    'fits' => $l->fits($waitingCount),
                ])->values()->all(),
        ]);
    }

    public function store(StoreContentUploadRequest $request): RedirectResponse
    {
        $provider = $request->user()->provider;
        $purpose = ContentPurpose::from($request->string('purpose')->value());
        $note = $request->input('note');

        $creadas = collect();

        foreach ($request->file('photos') as $index => $file) {
            $isVideo = str_starts_with((string) $file->getMimeType(), 'video/');

            // Un video se guarda tal cual; una foto se reencoda a WebP al
            // tamaño de la galería, igual que el resto del panel.
            $key = $isVideo
                ? $this->video->handle($provider, $file)
                : $this->store->handle($provider, $file, ImageVariant::Gallery);

            $creadas->push(ContentUpload::create([
                'provider_id' => $provider->id,
                'path' => $key,
                'kind' => ($isVideo ? UploadKind::Video : UploadKind::Image)->value,
                'purpose' => $purpose->value,
                // La nota describe la tanda entera, así que se guarda una sola
                // vez, en la primera. Copiarla en las diez hacía que el modelo
                // viera diez veces lo mismo y ahogara al resto de referencias.
                'note' => ($purpose === ContentPurpose::Reference && $index === 0) ? $note : null,
            ]));
        }

        $redirect = to_route('admin.contenido')->with(
            'success',
            $purpose === ContentPurpose::Reference ? 'admin.contentReferenceSaved' : 'admin.contentUploaded',
        );

        // Con una referencia recién guardada y sin nota, se le propone una en
        // vez de dejarle el cuadro vacío. «A la gente le da flojera pensar»,
        // y una referencia sin nota enseña la mitad: es la nota, no la imagen,
        // lo que le dice al sistema QUÉ mirar.
        //
        // Solo en la primera tanda de la selección: las siguientes son las
        // mismas fotos partidas para que pasen por Cloudflare, y sugerir en
        // cada una sería pagar lo mismo varias veces.
        if ($purpose === ContentPurpose::Reference && $note === null && $request->boolean('first')) {
            $redirect->with('noteSuggestion', [
                'uploadIds' => $creadas->pluck('id')->all(),
                'text' => $this->noteSuggestions->handle($creadas),
            ]);
        }

        // Aprender el estilo era un botón aparte que ella tenía que
        // acordarse de tocar después de subir — y si no lo hacía, la ficha
        // quedaba vieja o vacía aunque hubiera subido referencias nuevas.
        // Ella lo dijo directo: «apenas se suba algo como referencia tiene
        // que detectarlo». Ahora se lee sola en la primera tanda de cada
        // subida de referencias, con las que ya tenía guardadas más esta.
        // El botón sigue ahí para releerla a mano después de borrar una.
        if ($purpose === ContentPurpose::Reference && $request->boolean('first')) {
            $resultado = $this->style->handle($provider);

            if ($resultado['ok']) {
                $provider->update([
                    'content_style' => $resultado['style'],
                    'content_style_at' => now(),
                ]);
            }
        }

        // Solo la primera foto de la primera tanda: si YA es un flyer
        // terminado —título, precio, contacto ya impresos—, el sistema le va
        // a montar SU propio titular arriba y queda ilegible. Visto en una
        // generación real de Josean. Es un aviso, nunca bloquea la subida.
        $primeraImagen = $creadas->first(fn (ContentUpload $u): bool => $u->kind === UploadKind::Image);

        if ($purpose === ContentPurpose::Edit && $request->boolean('first') && $primeraImagen !== null
            && $this->detectDesigned->handle($primeraImagen)) {
            $redirect->with('warning', 'admin.contentPhotoAlreadyDesigned');
        }

        // Y de qué color es el trabajo, para las plantillas que pintan el
        // fondo o ponen la tarjeta con el nombre del color.
        //
        // Se lo propone el modelo y ELLA lo corrige: mirando los píxeles no
        // se puede —las uñas son una parte chica del cuadro y gana la ropa
        // del fondo, comprobado con sus fotos reales—. Ella misma pidió que
        // fuera así: «yo detecto tal cosa, ¿puedes decirnos algo más de los
        // colores como tú lo ves, con tu propia palabra?».
        //
        // Se lee UNA sola vez, de la primera foto, pero se guarda en TODAS
        // las de la tanda: son fotos del mismo trabajo, subidas juntas. Antes
        // solo quedaba en la primera y las demás se quedaban sin color — con
        // una tanda mezclada por error eso hacía que "Fondo de color" pudiera
        // agarrar una foto sin etiqueta y otra de un trabajo distinto sin que
        // nada avisara. Visto en una tanda real: una uña rosa degradado
        // mezclada con dos negras.
        if ($purpose === ContentPurpose::Edit && $request->boolean('first') && $primeraImagen !== null) {
            $color = $this->photoColor->handle($primeraImagen);

            if ($color !== null) {
                ContentUpload::query()
                    ->whereIn('id', $creadas->pluck('id'))
                    ->update([
                        'color_name' => $color['nombre'],
                        'color_hex' => $color['hex'],
                        // La técnica viene de la misma llamada: sirve para
                        // estampar la palabra del pack que corresponde
                        // ("Acrílicas" si fue de acrílicas) en vez de una
                        // frase al azar.
                        'technique' => $color['tecnica'] ?? null,
                    ]);

                $redirect->with('colorSuggestion', [
                    'uploadIds' => $creadas->pluck('id')->all(),
                    'name' => $color['nombre'],
                    'hex' => $color['hex'],
                ]);
            }
        }

        return $redirect;
    }

    /**
     * Arma el post con las fotos que ella eligió.
     */
    public function generate(Request $request): RedirectResponse
    {
        $provider = $request->user()->provider;

        $validated = $request->validate([
            'layout' => ['required', Rule::enum(PostLayout::class)],
            'uploadIds' => ['required', 'array'],
            'uploadIds.*' => ['integer'],
            // "¿Como cuál de tus referencias?" — ella lo pidió directo: en vez
            // de que el sistema mezcle todas sus referencias en un estilo
            // promedio, poder elegir UNA puntual para que el post salga
            // parecido a esa, no a un promedio de todas.
            'templateUploadId' => ['nullable', 'integer'],
        ]);

        $layout = PostLayout::from($validated['layout']);

        if (! empty($validated['templateUploadId'])) {
            $template = ContentUpload::query()
                ->where('provider_id', $provider->id)
                ->references()
                ->find($validated['templateUploadId']);

            if ($template !== null) {
                $resultado = $this->style->handleForUpload($template);

                // Se pisa en memoria y no se guarda: es la plantilla de ESTE
                // post, no la ficha general de la cuenta. Si falla la
                // lectura, sigue con lo de siempre — nunca por esto se queda
                // sin post.
                if ($resultado['ok']) {
                    $provider->content_style = $resultado['style'];
                }
            }
        }

        // Reconsultado con el provider_id puesto por el servidor: unos ids
        // inventados no alcanzan las fotos de otra profesional.
        $chosen = ContentUpload::query()
            ->where('provider_id', $provider->id)
            ->waiting()
            ->whereIn('id', $validated['uploadIds'])
            ->orderBy('id')
            ->get();

        // Todas las que quepan: el collage arma filas de distinto largo, así
        // que cinco fotos entran las cinco. El tope solo evita que cada foto
        // quede tan chica que no se distinga el trabajo.
        $uses = $layout->photosToUse($chosen->count());

        if ($uses === 0) {
            throw ValidationException::withMessages([
                'uploadIds' => __('admin.contentNeedsPhotos', ['count' => $layout->minPhotos()]),
            ]);
        }

        $uploads = $chosen->take($uses);
        $paths = $uploads->pluck('path')->all();

        // El fondo de color necesita saber de qué color es el trabajo, y eso
        // lo confirmó ella al subir las fotos.
        $color = null;

        if ($layout === PostLayout::ColorBlock) {
            $conColor = $uploads->first(fn (ContentUpload $u): bool => $u->color_hex !== null);

            if ($conColor === null) {
                throw ValidationException::withMessages([
                    'uploadIds' => __('admin.contentNeedsColor'),
                ]);
            }

            // Si en lo elegido hay más de un color y son bien distintos entre
            // sí, no se adivina con cuál quedarse: se avisa. Es justo lo que
            // pasó de verdad con una tanda que mezclaba una uña rosa con dos
            // negras — el post salió con "ROSA" escrito sobre fotos negras.
            $distinta = $uploads->first(fn (ContentUpload $u): bool => $u->color_hex !== null
                && ColorNames::farApart($u->color_hex, $conColor->color_hex));

            if ($distinta !== null) {
                throw ValidationException::withMessages([
                    'uploadIds' => __('admin.contentColorMismatch'),
                ]);
            }

            $color = ['name' => (string) $conColor->color_name, 'hex' => (string) $conColor->color_hex];
        }

        // La combinación es lo opuesto: necesita DOS colores distintos, uno
        // por foto.
        $combo = null;

        if ($layout === PostLayout::ColorCombo) {
            [$primera, $segunda] = [$uploads->get(0), $uploads->get(1)];

            if ($primera === null || $segunda === null) {
                throw ValidationException::withMessages([
                    'uploadIds' => __('admin.contentNeedsTwoColors'),
                ]);
            }

            // Acá el color se lee POR FOTO, no por tanda.
            //
            // Al subir se lee una sola vez, de la primera, y se copia a todas
            // las de la tanda: son fotos del mismo trabajo y así "Fondo de
            // color" no se mezcla. Pero para una combinación eso lo rompía
            // todo — dos fotos subidas juntas salían siempre del mismo color
            // y la plantilla se rechazaba sola, sin forma de usarla nunca.
            // Ella lo probó y lo dijo: «el modo combinación todavía no
            // funciona».
            //
            // Son dos llamadas baratas y solo cuando ella elige esta
            // plantilla, así que no encarece el resto del taller.
            foreach ([$primera, $segunda] as $foto) {
                $leido = $this->photoColor->handle($foto);

                if ($leido !== null) {
                    $foto->update(['color_name' => $leido['nombre'], 'color_hex' => $leido['hex']]);
                }
            }

            $primera->refresh();
            $segunda->refresh();

            $listos = $primera->color_hex !== null && $segunda->color_hex !== null
                && ColorNames::farApart($primera->color_hex, $segunda->color_hex);

            if (! $listos) {
                throw ValidationException::withMessages([
                    'uploadIds' => __('admin.contentNeedsTwoColors'),
                ]);
            }

            $combo = [
                'paths' => [$primera->path, $segunda->path],
                'colores' => [
                    ['nombre' => (string) $primera->color_name, 'hex' => (string) $primera->color_hex],
                    ['nombre' => (string) $segunda->color_name, 'hex' => (string) $segunda->color_hex],
                ],
            ];
        }

        // El texto primero: el titular que escribe el modelo va impreso
        // dentro de la imagen, así que no se puede armar sin él.
        $written = $this->caption->handle($provider);

        // La técnica de la primera foto elegida: con ella el titular puede
        // ser la palabra exacta del trabajo en vez de una frase cualquiera.
        $tecnica = $uploads->first(fn (ContentUpload $u): bool => $u->technique !== null)?->technique;

        $slides = match ($layout) {
            PostLayout::Hero => [$this->hero->handle($provider, $paths, $written['headline'], $tecnica)],
            PostLayout::Collage => [$this->collage->handle($provider, $paths, $written['headline'])],
            PostLayout::Carousel => $this->carousel->handle($provider, $paths, $written['headline']),
            // Esta no usa el titular: su texto ES el nombre del color, que
            // ella confirmó al subir las fotos. Ver BuildColorBlock.
            PostLayout::ColorBlock => [$this->colorBlock->handle(
                $provider,
                $paths,
                $color['name'],
                $color['hex'],
            )],
            // Tampoco esta usa el titular: la tarjeta lleva los dos nombres
            // de color. Ver BuildColorCombo.
            PostLayout::ColorCombo => [$this->colorCombo->handle($provider, $combo['paths'], $combo['colores'])],
        };

        // La portada es la que se ve en el muro y la que lista la pantalla.
        $key = $slides[0];

        DB::transaction(function () use ($provider, $layout, $key, $slides, $written, $paths, $uploads): void {
            ContentPost::create([
                'provider_id' => $provider->id,
                'layout' => $layout->value,
                'path' => $key,
                'slides' => $slides,
                'caption' => $written['caption'],
                'hashtags' => $written['hashtags'],
                'source_paths' => $paths,
            ]);

            ContentUpload::query()->whereIn('id', $uploads->pluck('id'))->update(['used_at' => now()]);
        });

        return to_route('admin.contenido')->with('success', 'admin.contentPostReady');
    }

    /**
     * Arma el post con las piezas que eligió ella, a mano.
     *
     * Es la salida al problema que ella señaló y que no tiene arreglo
     * automático: el sistema no sabe si la foto es el antes, el proceso o el
     * resultado, y estampar «proceso» sobre un trabajo terminado deja un
     * post que se contradice. Acá elige ella, que sí lo sabe.
     */
    public function compose(Request $request): RedirectResponse
    {
        $provider = $request->user()->provider;

        $validated = $request->validate([
            'uploadId' => ['required', 'integer'],
            'assetIds' => ['required', 'array', 'min:1', 'max:6'],
            'assetIds.*' => ['integer'],
        ]);

        // Reconsultada con el provider_id del servidor: un id inventado no
        // alcanza la foto de otra profesional.
        $foto = ContentUpload::query()
            ->where('provider_id', $provider->id)
            ->where('purpose', ContentPurpose::Edit->value)
            ->find($validated['uploadId']);

        if ($foto === null) {
            throw ValidationException::withMessages(['uploadId' => __('admin.contentNeedsPhotos', ['count' => 1])]);
        }

        // En el orden en que ella las tocó, no en el que vengan de la base:
        // la última que puso tiene que quedar arriba.
        $porId = ContentAsset::query()
            ->whereIn('id', $validated['assetIds'])
            ->get()
            ->filter(fn (ContentAsset $a): bool => PhraseSafety::usable($a->slug))
            ->keyBy('id');

        $piezas = collect($validated['assetIds'])
            ->map(fn (int $id) => $porId->get($id))
            ->filter()
            ->values();

        if ($piezas->isEmpty()) {
            throw ValidationException::withMessages(['assetIds' => __('admin.contentNeedsSticker')]);
        }

        $written = $this->caption->handle($provider);
        $key = $this->compose->handle($provider, $foto->path, $piezas);

        DB::transaction(function () use ($provider, $key, $written, $foto): void {
            ContentPost::create([
                'provider_id' => $provider->id,
                'layout' => PostLayout::Hero->value,
                'path' => $key,
                'slides' => [$key],
                'caption' => $written['caption'],
                'hashtags' => $written['hashtags'],
                'source_paths' => [$foto->path],
            ]);

            $foto->update(['used_at' => now()]);
        });

        return to_route('admin.contenido')->with('success', 'admin.contentPostReady');
    }

    /**
     * Guardar la nota de una tanda de referencias, con la sugerencia ya
     * editada por ella.
     *
     * La nota se guarda en la primera de la tanda, igual que al subirlas —
     * repetirla en todas ahogaba al resto de las referencias.
     */
    public function updateNote(Request $request): RedirectResponse
    {
        $provider = $request->user()->provider;

        $validated = $request->validate([
            'uploadIds' => ['required', 'array'],
            'uploadIds.*' => ['integer'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        // Reconsultado con el provider_id del servidor: unos ids inventados no
        // alcanzan las referencias de otra profesional.
        $primera = ContentUpload::query()
            ->where('provider_id', $provider->id)
            ->references()
            ->whereIn('id', $validated['uploadIds'])
            // Por id: con fecha, dos filas del mismo segundo empatan y la
            // nota podia terminar guardada en la foto equivocada.
            ->orderBy('id')
            ->first();

        $primera?->update(['note' => $validated['note'] ?: null]);

        return to_route('admin.contenido')->with('success', 'admin.contentReferenceSaved');
    }

    /**
     * Guardar el color del trabajo con las palabras de ella.
     *
     * El modelo lo propuso al subir; acá manda lo que ella escribió. Si lo
     * deja vacío se borra: prefiere no decir nada antes que dejar puesto algo
     * que no es.
     *
     * Se aplica a TODA la tanda, no solo a la primera foto: son fotos del
     * mismo trabajo, y si la corrección quedara solo en una, las demás se
     * quedarían con el color viejo que el modelo propuso.
     */
    public function updateColor(Request $request): RedirectResponse
    {
        $provider = $request->user()->provider;

        $validated = $request->validate([
            'uploadIds' => ['required', 'array'],
            'uploadIds.*' => ['integer'],
            'name' => ['nullable', 'string', 'max:60'],
            'hex' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        // Reconsultado con el provider_id del servidor: unos ids inventados no
        // alcanzan las fotos de otra profesional.
        ContentUpload::query()
            ->where('provider_id', $provider->id)
            ->where('purpose', ContentPurpose::Edit->value)
            ->whereIn('id', $validated['uploadIds'])
            ->update([
                'color_name' => $validated['name'] ?: null,
                'color_hex' => isset($validated['hex']) ? strtoupper($validated['hex']) : null,
            ]);

        return to_route('admin.contenido')->with('success', 'admin.contentColorSaved');
    }

    /**
     * Leer sus referencias y quedarse con la ficha de estilo.
     *
     * Se dispara cuando ella lo pide y no en cada post: es una llamada con
     * imágenes y no tiene sentido repetirla si las referencias no cambiaron.
     */
    public function learnStyle(Request $request): RedirectResponse
    {
        $provider = $request->user()->provider;

        $result = $this->style->handle($provider);

        if ($result['ok'] === false) {
            throw ValidationException::withMessages(['style' => __($result['error'])]);
        }

        $provider->update([
            'content_style' => $result['style'],
            'content_style_at' => now(),
        ]);

        return to_route('admin.contenido')->with('success', 'admin.contentStyleLearned');
    }

    /**
     * Quitar una referencia que se subió por error.
     *
     * El archivo se borra de R2 también: una referencia que ya no enseña nada
     * no tiene por qué seguir ocupando espacio ni apareciendo en la lista.
     */
    public function destroyReference(ContentUpload $upload): RedirectResponse
    {
        $this->deleteImage->handle($upload->path);
        $upload->delete();

        return to_route('admin.contenido')->with('success', 'admin.contentReferenceRemoved');
    }

    /**
     * Meter o sacar una foto de la cola del próximo post.
     *
     * Es un interruptor y no dos acciones distintas: antes solo se podía
     * METER —las que ya estaban en la cola no eran tocables— y ella se quedó
     * trabada: «yo la selecciono, voy agregando, y después ya no la puedo
     * deseleccionar».
     *
     * `used_at` es lo que decide si una foto espera un post (ver
     * ContentUpload::waiting). Sacarla de la cola es marcarla como usada,
     * que es justo lo que significa en la pantalla: ya no la ofrezco.
     */
    public function toggleQueued(ContentUpload $upload): RedirectResponse
    {
        if ($upload->purpose === ContentPurpose::Edit) {
            $upload->update(['used_at' => $upload->used_at === null ? now() : null]);
        }

        return to_route('admin.contenido')->with(
            'success',
            $upload->used_at === null ? 'admin.contentPhotoQueued' : 'admin.contentPhotoUnqueued',
        );
    }

    /**
     * El pulgar arriba o abajo, y sobre todo el motivo: un "no me gusta"
     * suelto no sirve para ajustar nada, el motivo escrito sí.
     *
     * Un "no me gusta" también libera las fotos que armaron ese post: sin
     * esto, un post que no le gustó dejaba las mismas fotos marcadas como
     * usadas para siempre, y para volver a intentar tenía que subirlas de
     * nuevo desde el teléfono. Ella lo dijo directo: «esa foto deberían
     * quedar ahí lista, para volverlas a seleccionar».
     */
    public function rate(Request $request, ContentPost $post): RedirectResponse
    {
        $validated = $request->validate([
            'rating' => ['required', Rule::in([ContentPost::RATING_UP, ContentPost::RATING_DOWN])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        // Solo en el momento en que pasa a "no me gusta", nunca de nuevo: si
        // ya estaba en "no me gusta" y esas mismas fotos se volvieron a usar
        // en un post distinto, repetir la liberación se las robaría a ese
        // post nuevo.
        $yaEstabaDescartado = $post->rating === ContentPost::RATING_DOWN;

        $post->update([
            'rating' => $validated['rating'],
            'rating_note' => $validated['note'] ?? null,
        ]);

        if ($validated['rating'] === ContentPost::RATING_DOWN && ! $yaEstabaDescartado) {
            ContentUpload::query()
                ->where('provider_id', $post->provider_id)
                ->whereIn('path', $post->source_paths ?? [])
                ->update(['used_at' => null]);

            // Mensaje distinto y no el genérico: es la parte que a ella le
            // molestaba no saber — que esas fotos no se perdieron.
            return to_route('admin.contenido')->with('success', 'admin.contentRatedDownFreedPhotos');
        }

        return to_route('admin.contenido')->with('success', 'admin.contentRated');
    }
}
