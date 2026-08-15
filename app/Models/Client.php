<?php

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['provider_id', 'name', 'phone', 'email', 'notes', 'tags'])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }

    public const MAX_PHOTOS = 12;

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * @return HasMany<ClientPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(ClientPhoto::class)->orderByDesc('id');
    }

    /**
     * The card's appointment history. Not an Eloquent relation on purpose:
     * appointments carry phone snapshots (no clients foreign key), so the
     * link is the same phone match the WhatsApp assistant uses. A card
     * without phone has no history to claim.
     *
     * @return Builder<Appointment>
     */
    public function appointmentsQuery(): Builder
    {
        $query = Appointment::query()->where('provider_id', $this->provider_id);

        if ($this->phone === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('client_phone', $this->phone);
    }
}
