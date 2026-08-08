<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dated block where the provider takes no appointments at all — a holiday, a
 * trip, a single day off. Inclusive on both ends: one day off is stored with
 * starts_on equal to ends_on.
 */
#[Fillable(['provider_id', 'starts_on', 'ends_on', 'reason'])]
class ProviderTimeOff extends Model
{
    protected $table = 'provider_time_off';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
