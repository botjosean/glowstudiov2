<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Content\BuildCarousel;
use App\Actions\Content\BuildCollage;
use App\Actions\Content\BuildHero;
use App\Actions\Content\FetchReferenceImage;
use App\Actions\Content\StoreReferenceVideo;
use App\Actions\Content\WriteCaption;
use App\Actions\Media\DeleteProviderImage;
use App\Actions\Media\StoreProviderImage;
use App\Enums\ContentPurpose;
use App\Enums\ImageVariant;
use App\Enums\PostLayout;
use App\Enums\UploadKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreContentUploadRequest;
use App\Models\ContentPost;
use App\Models\ContentUpload;
use App\Support\MediaUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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
        private readonly FetchReferenceImage $fetch,
        private readonly WriteCaption $caption,
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
            // Las que esperan que ella elija un modelo.
            'waiting' => ContentUpload::query()
                ->where('provider_id', $provider->id)
                ->waiting()
                ->oldest()
                ->get()
                ->map(fn (ContentUpload $upload): array => [
                    'id' => $upload->id,
                    'url' => MediaUrl::resolve($upload->path),
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

        foreach ($request->file('photos') as $index => $file) {
            $isVideo = str_starts_with((string) $file->getMimeType(), 'video/');

            // Un video se guarda tal cual; una foto se reencoda a WebP al
            // tamaño de la galería, igual que el resto del panel.
            $key = $isVideo
                ? $this->video->handle($provider, $file)
                : $this->store->handle($provider, $file, ImageVariant::Gallery);

            ContentUpload::create([
                'provider_id' => $provider->id,
                'path' => $key,
                'kind' => ($isVideo ? UploadKind::Video : UploadKind::Image)->value,
                'purpose' => $purpose->value,
                // La nota describe la tanda entera, así que se guarda una sola
                // vez, en la primera. Copiarla en las diez hacía que el modelo
                // viera diez veces lo mismo y ahogara al resto de referencias.
                'note' => ($purpose === ContentPurpose::Reference && $index === 0) ? $note : null,
            ]);
        }

        return to_route('admin.contenido')->with(
            'success',
            $purpose === ContentPurpose::Reference ? 'admin.contentReferenceSaved' : 'admin.contentUploaded',
        );
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
        ]);

        $layout = PostLayout::from($validated['layout']);

        // Reconsultado con el provider_id puesto por el servidor: unos ids
        // inventados no alcanzan las fotos de otra profesional.
        $chosen = ContentUpload::query()
            ->where('provider_id', $provider->id)
            ->waiting()
            ->whereIn('id', $validated['uploadIds'])
            ->oldest()
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

        // El texto primero: el titular que escribe el modelo va impreso
        // dentro de la imagen, así que no se puede armar sin él.
        $written = $this->caption->handle($provider);

        $slides = match ($layout) {
            PostLayout::Hero => [$this->hero->handle($provider, $paths, $written['headline'])],
            PostLayout::Collage => [$this->collage->handle($provider, $paths, $written['headline'])],
            PostLayout::Carousel => $this->carousel->handle($provider, $paths, $written['headline']),
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
     * Guardar una referencia desde un enlace pegado.
     *
     * Ella lo pidió para no tener que bajar cada imagen al teléfono primero.
     * El control de a dónde apunta el enlace vive en FetchReferenceImage: es
     * el servidor quien hace la petición, así que un enlace a una dirección
     * interna sería una puerta de entrada, no una foto fea.
     */
    public function storeLink(Request $request): RedirectResponse
    {
        $provider = $request->user()->provider;

        $validated = $request->validate([
            'url' => ['required', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $this->fetch->handle(trim($validated['url']));

        if ($result['ok'] === false) {
            throw ValidationException::withMessages(['url' => __($result['error'])]);
        }

        // Reencodada igual que una subida normal: la defensa de verdad es que
        // lo que se guarda lo genera este servidor, no el de enfrente.
        //
        // El archivo temporal y el `true` del final son para poder construir
        // un UploadedFile sobre algo que no vino de un formulario — sin eso
        // Symfony lo rechaza por no haber pasado por is_uploaded_file().
        $tmp = tempnam(sys_get_temp_dir(), 'ref');
        file_put_contents($tmp, $result['body']);

        try {
            $key = $this->store->handle(
                $provider,
                new UploadedFile($tmp, 'link.jpg', $result['mime'], null, true),
                ImageVariant::Gallery,
            );
        } finally {
            @unlink($tmp);
        }

        ContentUpload::create([
            'provider_id' => $provider->id,
            'path' => $key,
            'kind' => UploadKind::Image->value,
            'purpose' => ContentPurpose::Reference->value,
            'note' => $validated['note'] ?? null,
        ]);

        return to_route('admin.contenido')->with('success', 'admin.contentReferenceSaved');
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
     * El pulgar arriba o abajo, y sobre todo el motivo: un "no me gusta"
     * suelto no sirve para ajustar nada, el motivo escrito sí.
     */
    public function rate(Request $request, ContentPost $post): RedirectResponse
    {
        $validated = $request->validate([
            'rating' => ['required', Rule::in([ContentPost::RATING_UP, ContentPost::RATING_DOWN])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $post->update([
            'rating' => $validated['rating'],
            'rating_note' => $validated['note'] ?? null,
        ]);

        return to_route('admin.contenido')->with('success', 'admin.contentRated');
    }
}
