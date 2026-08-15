<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Media\StoreProviderImage;
use App\Enums\ImageVariant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UploadProviderPhotoRequest;
use App\Models\Client;
use App\Models\ClientPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * The "after" shot on a client card. Same hardened pipeline as the provider
 * gallery — re-encoded WebP under a server-generated key, never the client
 * filename — just pinned to one client instead of the public profile.
 */
class ClientPhotoController extends Controller
{
    public function __construct(
        private readonly StoreProviderImage $store,
    ) {}

    public function store(UploadProviderPhotoRequest $request, Client $client): RedirectResponse
    {
        if ($client->photos()->count() >= Client::MAX_PHOTOS) {
            throw ValidationException::withMessages(['photo' => __('admin.clientPhotoLimit')]);
        }

        $key = $this->store->handle($client->provider, $request->file('photo'), ImageVariant::Gallery);

        $client->photos()->create(['url' => $key]);

        return back()->with('success', 'admin.clientPhotoAdded');
    }

    public function destroy(Client $client, ClientPhoto $photo): RedirectResponse
    {
        $photo->delete();

        return back()->with('success', 'admin.clientPhotoDeleted');
    }
}
