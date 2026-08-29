<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Content\BuildCollage;
use App\Actions\Content\WriteCaption;
use App\Actions\Media\StoreProviderImage;
use App\Enums\ContentPurpose;
use App\Enums\ImageVariant;
use App\Enums\PostLayout;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreContentUploadRequest;
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
        private readonly BuildCollage $collage,
        private readonly WriteCaption $caption,
    ) {}

    public function index(Request $request): Response
    {
        $provider = $request->user()->provider;

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
            'referenceCount' => ContentUpload::query()
                ->where('provider_id', $provider->id)
                ->references()
                ->count(),
            'posts' => ContentPost::query()
                ->where('provider_id', $provider->id)
                ->latest()
                ->limit(12)
                ->get()
                ->map(fn (ContentPost $post): array => [
                    'id' => $post->id,
                    'url' => MediaUrl::resolve($post->path),
                    'caption' => $post->caption,
                    'hashtags' => $post->hashtags ?? [],
                    'rating' => $post->rating,
                ])->values()->all(),
            'collagePhotos' => PostLayout::Collage4->photoCount(),
        ]);
    }

    public function store(StoreContentUploadRequest $request): RedirectResponse
    {
        $provider = $request->user()->provider;
        $purpose = ContentPurpose::from($request->string('purpose')->value());
        $note = $request->input('note');

        foreach ($request->file('photos') as $photo) {
            // Se guarda en el mismo tamaño que la galería (1200²) y reencodado
            // a WebP, igual que el resto de las fotos del panel.
            $key = $this->store->handle($provider, $photo, ImageVariant::Gallery);

            ContentUpload::create([
                'provider_id' => $provider->id,
                'path' => $key,
                'purpose' => $purpose->value,
                // La nota describe la tanda, así que se copia en cada foto de
                // la tanda — es lo que se le muestra al modelo después.
                'note' => $purpose === ContentPurpose::Reference ? $note : null,
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
        $uploads = ContentUpload::query()
            ->where('provider_id', $provider->id)
            ->waiting()
            ->whereIn('id', $validated['uploadIds'])
            ->oldest()
            ->limit($layout->photoCount())
            ->get();

        if ($uploads->count() < $layout->photoCount()) {
            throw ValidationException::withMessages([
                'uploadIds' => __('admin.contentNeedsPhotos', ['count' => $layout->photoCount()]),
            ]);
        }

        $paths = $uploads->pluck('path')->all();

        $key = $this->collage->handle($provider, $paths);
        $written = $this->caption->handle($provider);

        DB::transaction(function () use ($provider, $layout, $key, $written, $paths, $uploads): void {
            ContentPost::create([
                'provider_id' => $provider->id,
                'layout' => $layout->value,
                'path' => $key,
                'caption' => $written['caption'],
                'hashtags' => $written['hashtags'],
                'source_paths' => $paths,
            ]);

            ContentUpload::query()->whereIn('id', $uploads->pluck('id'))->update(['used_at' => now()]);
        });

        return to_route('admin.contenido')->with('success', 'admin.contentPostReady');
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
