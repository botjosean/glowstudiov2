<?php

namespace App\Enums;

enum ServiceCategory: string
{
    case Fade = 'fade';
    case Classic = 'classic';
    case Beard = 'beard';
    case Kids = 'kids';
    case Color = 'color';

    // Beauty categories, added once the platform stopped being barbershop-only.
    // The five above are kept as-is because live provider data already uses
    // them; widening the list never invalidates a service that already exists.
    case Nails = 'nails';
    case Hands = 'hands';
    case Feet = 'feet';
    case Lashes = 'lashes';
    case Facial = 'facial';
    case Hair = 'hair';
    case Other = 'other';

    /**
     * The catalog ServiceType a service in this category defaults to when
     * created from the admin panel (which has no type picker, only a
     * category field). Lets admin-created services still count toward the
     * Home page's ServiceType aggregation instead of always falling back
     * to a typeless service.
     *
     * The beauty categories return null on purpose: the shipped catalog is
     * all barbershop types, and inventing rows for them here would change
     * what the public Home page advertises as a side effect of a form field.
     * A typeless service is already a supported state — it simply sits out
     * of that aggregation, and still renders its own icon (see icon()).
     */
    public function defaultTypeSlug(): ?string
    {
        return match ($this) {
            self::Fade => 'skin-fade',
            self::Classic => 'haircut',
            self::Beard => 'beard-grooming',
            self::Kids => 'kids-haircut',
            self::Color => 'hair-coloring',
            self::Nails, self::Hands, self::Feet,
            self::Lashes, self::Facial, self::Hair, self::Other => null,
        };
    }

    /**
     * The icon a service in this category shows on the public profile.
     *
     * The category is what the provider actually picked in the panel, so it
     * is the honest source for this — reading it off the catalog ServiceType
     * instead meant a nail service created before the beauty categories
     * existed kept rendering a pair of scissors no matter what its owner
     * chose. The five barbershop arms deliberately reproduce the icons their
     * matching ServiceTypes already carried, so no live service changes
     * appearance from this move alone.
     */
    public function icon(): ServiceIcon
    {
        return match ($this) {
            self::Fade, self::Classic, self::Hair => ServiceIcon::Scissors,
            self::Beard, self::Color => ServiceIcon::Sparkles,
            self::Kids => ServiceIcon::Smile,
            self::Nails => ServiceIcon::Gem,
            self::Hands => ServiceIcon::Hand,
            self::Feet => ServiceIcon::Footprints,
            self::Lashes => ServiceIcon::Eye,
            self::Facial => ServiceIcon::Flower,
            self::Other => ServiceIcon::Star,
        };
    }
}
