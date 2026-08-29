<?php

namespace App\Models;

use App\Enums\PostLayout;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un post ya armado: la imagen generada más el texto que la acompaña.
 */
#[Fillable([
    'provider_id', 'layout', 'path', 'slides', 'caption', 'hashtags',
    'source_paths', 'rating', 'rating_note',
])]
class ContentPost extends Model
{
    public const RATING_UP = 'up';

    public const RATING_DOWN = 'down';

    protected function casts(): array
    {
        return [
            'layout' => PostLayout::class,
            'slides' => 'array',
            'hashtags' => 'array',
            'source_paths' => 'array',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
