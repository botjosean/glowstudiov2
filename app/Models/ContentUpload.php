<?php

namespace App\Models;

use App\Enums\ContentPurpose;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una foto que la profesional subió al Taller de Contenido, con lo que dijo
 * que quería hacer con ella.
 */
#[Fillable(['provider_id', 'path', 'purpose', 'note', 'used_at'])]
class ContentUpload extends Model
{
    protected function casts(): array
    {
        return [
            'purpose' => ContentPurpose::class,
            'used_at' => 'immutable_datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    #[Scope]
    protected function references(Builder $query): void
    {
        $query->where('purpose', ContentPurpose::Reference->value);
    }

    /** Fotos subidas para editar que todavía no se convirtieron en un post. */
    #[Scope]
    protected function waiting(Builder $query): void
    {
        $query->where('purpose', ContentPurpose::Edit->value)->whereNull('used_at');
    }
}
