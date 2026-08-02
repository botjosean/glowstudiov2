<?php

namespace App\Enums;

enum ServiceIcon: string
{
    case Scissors = 'scissors';
    case Sparkles = 'sparkles';
    case Smile = 'smile';

    public static function default(): self
    {
        return self::Scissors;
    }
}
