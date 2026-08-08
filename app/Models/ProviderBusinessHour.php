<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One provider's working window for one weekday.
 *
 * `weekday` follows Carbon's dayOfWeek — 0 is Sunday through 6 is Saturday —
 * so it can be compared against a date without translating between conventions.
 */
#[Fillable(['provider_id', 'weekday', 'is_open', 'work_start_minute', 'work_end_minute'])]
class ProviderBusinessHour extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => 'int',
            'is_open' => 'bool',
            'work_start_minute' => 'int',
            'work_end_minute' => 'int',
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
