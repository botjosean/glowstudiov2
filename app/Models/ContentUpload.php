<?php

namespace App\Models;

use App\Enums\ContentPurpose;
use App\Enums\UploadKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una foto que la profesional subió al Taller de Contenido, con lo que dijo
 * que quería hacer con ella.
 */
#[Fillable(['provider_id', 'path', 'kind', 'purpose', 'note', 'used_at', 'learned_style', 'learned_style_at', 'color_name', 'color_hex', 'technique'])]
class ContentUpload extends Model
{
    protected function casts(): array
    {
        return [
            'kind' => UploadKind::class,
            'purpose' => ContentPurpose::class,
            'used_at' => 'immutable_datetime',
            'learned_style' => 'array',
            'learned_style_at' => 'immutable_datetime',
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

    /**
     * Fotos subidas para editar que todavía no se convirtieron en un post.
     *
     * El filtro por 'image' es defensa en profundidad: un video ya no puede
     * llegar con purpose 'edit' (lo corta el Request), pero un collage armado
     * con un archivo de video fallaría de una forma difícil de leer.
     */
    #[Scope]
    protected function waiting(Builder $query): void
    {
        $query->where('purpose', ContentPurpose::Edit->value)
            ->where('kind', UploadKind::Image->value)
            ->whereNull('used_at');
    }
}
