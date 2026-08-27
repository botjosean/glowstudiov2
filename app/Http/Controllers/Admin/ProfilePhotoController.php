<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Media\DeleteProviderImage;
use App\Actions\Media\StoreProviderImage;
use App\Enums\ImageVariant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UploadProviderPhotoRequest;
use App\Models\Provider;
use App\Models\ProviderPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ProfilePhotoController extends Controller
{
    public function __construct(
        private readonly StoreProviderImage $store,
        private readonly DeleteProviderImage $delete,
    ) {}

    /**
     * No {id} on avatar/banner/gallery-store — the target is always
     * $request->user()->provider, so there is no cross-tenant vector here.
     */
    public function storeAvatar(UploadProviderPhotoRequest $request): RedirectResponse
    {
        $provider = $request->user()->provider;
        $previous = $provider->avatar_photo_url;

        $key = $this->store->handle($provider, $request->file('photo'), ImageVariant::Avatar, $request->focus());
        $provider->update(['avatar_photo_url' => $key]);

        // Only after the new one is safely saved — never delete-then-store.
        $this->delete->handle($previous);

        return to_route('admin.perfil')->with('success', 'admin.photoUpdated');
    }

    public function storeBanner(UploadProviderPhotoRequest $request): RedirectResponse
    {
        $provider = $request->user()->provider;
        $previous = $provider->banner_photo_url;

        $key = $this->store->handle($provider, $request->file('photo'), ImageVariant::Banner, $request->focus());
        $provider->update(['banner_photo_url' => $key]);

        $this->delete->handle($previous);

        return to_route('admin.perfil')->with('success', 'admin.photoUpdated');
    }

    public function storeGalleryPhoto(UploadProviderPhotoRequest $request): RedirectResponse
    {
        $provider = $request->user()->provider;

        if ($provider->photos()->count() >= Provider::MAX_GALLERY_PHOTOS) {
            throw ValidationException::withMessages([
                'photo' => __('admin.galleryFull'),
            ]);
        }

        $key = $this->store->handle($provider, $request->file('photo'), ImageVariant::Gallery);
        $position = ((int) $provider->photos()->max('position')) + 1;

        $provider->photos()->create(['url' => $key, 'position' => $position]);

        return to_route('admin.perfil')->with('success', 'admin.photoUpdated');
    }

    /**
     * The one photo route with an {id} — ownership is enforced by
     * ->can('delete', 'photo') in routes/web.php, see ProviderPhotoPolicy.
     */
    public function destroyGalleryPhoto(ProviderPhoto $photo): RedirectResponse
    {
        $this->delete->handle($photo->url);
        $photo->delete();

        return to_route('admin.perfil')->with('success', 'admin.photoDeleted');
    }
}
