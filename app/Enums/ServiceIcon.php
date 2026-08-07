<?php

namespace App\Enums;

enum ServiceIcon: string
{
    case Scissors = 'scissors';
    case Sparkles = 'sparkles';
    case Smile = 'smile';
    case Gem = 'gem';
    case Hand = 'hand';
    case Footprints = 'footprints';
    case Eye = 'eye';
    case Flower = 'flower';
    case Star = 'star';

    public static function default(): self
    {
        return self::Scissors;
    }
}
