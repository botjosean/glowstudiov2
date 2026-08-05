<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ProviderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[RouteKey('slug')]
#[Fillable([
    'user_id', 'slug', 'whatsapp_phone_number_id', 'public_name', 'bio', 'banner_photo_url', 'avatar_photo_url',
    'is_mobile', 'is_available_now', 'published_at', 'service_area', 'address_line',
    'whatsapp_url', 'instagram_url', 'tiktok_url', 'facebook_url', 'timezone',
    'work_start_minute', 'work_end_minute', 'lunch_start_minute', 'lunch_end_minute', 'buffer_minutes',
])]
class Provider extends Model
{
    /** @use HasFactory<ProviderFactory> */
    use HasFactory;

    public const MAX_GALLERY_PHOTOS = 6;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_mobile' => 'bool',
            'is_available_now' => 'bool',
            'published_at' => 'immutable_datetime',
            'work_start_minute' => 'int',
            'work_end_minute' => 'int',
            'lunch_start_minute' => 'int',
            'lunch_end_minute' => 'int',
            'buffer_minutes' => 'int',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /**
     * @return HasMany<ProviderPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(ProviderPhoto::class)->orderBy('position');
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    #[Scope]
    protected function published(Builder $query): void
    {
        $query->whereNotNull('published_at');
    }

    #[Scope]
    protected function availableNow(Builder $query): void
    {
        $query->where('is_available_now', true);
    }

    /**
     * "Now" in the provider's own timezone — used for slot generation and
     * for deciding which appointments count as "today".
     */
    public function currentTime(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone);
    }
}
