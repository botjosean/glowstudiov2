<?php

namespace App\Actions\Media;

use App\Enums\ImageVariant;
use App\Models\Provider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

class StoreProviderImage
{
    /**
     * Re-encoding to a server-generated WebP is the actual security
     * control here, not the upload validation — File::image() only checks
     * the header, so whatever payload, EXIF, or malformed structure the
     * original carried does not survive this step.
     */
    public function handle(Provider $provider, UploadedFile $file, ImageVariant $variant): string
    {
        $image = ImageManager::imagick()
            ->read($file->getPathname())
            ->cover($variant->width(), $variant->height());

        $encoded = $image->toWebp(quality: 82);

        // Server-generated ULID key — the client's filename is never used.
        $key = sprintf('providers/%d/%s/%s.webp', $provider->id, $variant->value, (string) Str::ulid());

        Storage::disk('r2')->put($key, (string) $encoded, [
            'ContentType' => 'image/webp',
            'CacheControl' => 'public, max-age=31536000, immutable',
        ]);

        return $key;
    }
}
