<?php

namespace App\Actions\Media;

use App\Enums\ImageVariant;
use App\Models\Provider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\ImageManager;

class StoreProviderImage
{
    /**
     * Re-encoding to a server-generated WebP is the actual security
     * control here, not the upload validation — File::image() only checks
     * the header, so whatever payload, EXIF, or malformed structure the
     * original carried does not survive this step.
     *
     * @param  array{x: float, y: float}|null  $focus  Where she dragged the
     *         photo to (PhotoRepositionSheet.vue), same 0–100 semantics as
     *         CSS object-position. Falls back to ImageVariant's fixed
     *         anchor when absent — the gallery upload never sends one.
     */
    public function handle(Provider $provider, UploadedFile $file, ImageVariant $variant, ?array $focus = null): string
    {
        $image = ImageManager::imagick()->read($file->getPathname());

        $image = $focus !== null
            ? $this->cropToFocus($image, $variant->width(), $variant->height(), $focus['x'], $focus['y'])
            : $image->cover($variant->width(), $variant->height(), $variant->cropPosition());

        $encoded = $image->toWebp(quality: 82);

        // Server-generated ULID key — the client's filename is never used.
        $key = sprintf('providers/%d/%s/%s.webp', $provider->id, $variant->value, (string) Str::ulid());

        Storage::disk('r2')->put($key, (string) $encoded, [
            'ContentType' => 'image/webp',
            'CacheControl' => 'public, max-age=31536000, immutable',
        ]);

        return $key;
    }

    /**
     * The manual version of ->cover(): crop a $targetW×$targetH window out
     * of the source first, then resize that window down — instead of
     * ->cover()'s fixed named anchor ('top', 'center', …), the window's
     * position along whichever axis has slack is $focusX/$focusY percent
     * of the way across it, exactly like CSS object-position.
     */
    private function cropToFocus(ImageInterface $image, int $targetW, int $targetH, float $focusX, float $focusY): ImageInterface
    {
        $origW = $image->width();
        $origH = $image->height();
        $targetRatio = $targetW / $targetH;

        if (($origW / $origH) > $targetRatio) {
            $cropH = $origH;
            $cropW = (int) round($origH * $targetRatio);
        } else {
            $cropW = $origW;
            $cropH = (int) round($origW / $targetRatio);
        }

        $offsetX = (int) round(max(0, $origW - $cropW) * (max(0, min(100, $focusX)) / 100));
        $offsetY = (int) round(max(0, $origH - $cropH) * (max(0, min(100, $focusY)) / 100));

        return $image->crop($cropW, $cropH, $offsetX, $offsetY, position: 'top-left')
            ->resize($targetW, $targetH);
    }
}
