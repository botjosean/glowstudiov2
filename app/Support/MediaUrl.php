<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Provider photo columns store either a legacy absolute URL (seeded demo
 * data, e.g. Unsplash) or an R2 object key uploaded through the app. This
 * resolves either into a URL the browser can load, and distinguishes the
 * two so deletion only ever touches objects this app actually manages.
 */
class MediaUrl
{
    public static function resolve(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        if (Str::startsWith($stored, ['http://', 'https://'])) {
            return $stored;
        }

        return Storage::disk('r2')->url($stored);
    }

    public static function isManagedKey(?string $stored): bool
    {
        return $stored !== null && $stored !== '' && ! Str::startsWith($stored, ['http://', 'https://']);
    }
}
