<?php

namespace App\Enums;

enum ServiceCategory: string
{
    case Fade = 'fade';
    case Classic = 'classic';
    case Beard = 'beard';
    case Kids = 'kids';
    case Color = 'color';

    /**
     * The catalog ServiceType a service in this category defaults to when
     * created from the admin panel (which has no type picker, only a
     * category field). Lets admin-created services still count toward the
     * Home page's ServiceType aggregation instead of always falling back
     * to a typeless service.
     */
    public function defaultTypeSlug(): string
    {
        return match ($this) {
            self::Fade => 'skin-fade',
            self::Classic => 'haircut',
            self::Beard => 'beard-grooming',
            self::Kids => 'kids-haircut',
            self::Color => 'hair-coloring',
        };
    }
}
