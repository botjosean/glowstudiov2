<?php

namespace App\Actions\Media;

use App\Support\MediaUrl;
use Illuminate\Support\Facades\Storage;

class DeleteProviderImage
{
    /**
     * Only ever deletes objects this app manages — a seeded Unsplash URL
     * (or any other absolute URL) is never sent to R2's delete endpoint.
     */
    public function handle(?string $stored): void
    {
        if (MediaUrl::isManagedKey($stored)) {
            Storage::disk('r2')->delete($stored);
        }
    }
}
