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
     * Upload cap before re-encoding — not the stored size, which is always
     * a re-encoded WebP at width()/height().
     */
    public function maxUploadKilobytes(): int
    {
        return 8 * 1024;
    }
}
