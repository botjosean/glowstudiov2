<?php

namespace App\Enums;

enum ImageVariant: string
{
    case Avatar = 'avatar';
    case Banner = 'banner';
    case Gallery = 'gallery';

    public function width(): int
    {
        return match ($this) {
            self::Avatar => 512,
            self::Banner => 1600,
            self::Gallery => 1200,
        };
    }

    public function height(): int
    {
        return match ($this) {
            self::Avatar => 512,
            self::Banner => 600,
            self::Gallery => 1200,
        };
    }

    /**
     * Where the square/rectangle is taken from when the source doesn't match
     * the target ratio.
     *
     * An avatar anchors to the top because people upload the photo they like,
     * which is usually a half or full-length shot — and a centred crop of one
     * of those lands on a torso. Faces sit in the upper third, so that is
     * where the crop belongs. Banner and gallery keep the centre, where the
     * subject of a wide or square photo actually is.
     */
    public function cropPosition(): string
    {
        return match ($this) {
            self::Avatar => 'top',
            self::Banner, self::Gallery => 'center',
        };
    }

    /**
     * Upload cap before re-encoding — not the stored size, which is always
     * a re-encoded WebP at width()/height().
     */
    public function maxUploadKilobytes(): int
    {
        return 8 * 1024;
    }
}
