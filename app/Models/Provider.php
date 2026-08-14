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
    'is_mobile', 'home_service', 'is_available_now', 'published_at', 'service_area', 'address_line',
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
            'home_service' => 'bool',
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

    /**
     * @return HasMany<Client, $this>
     */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    /**
     * @return HasMany<ProviderBusinessHour, $this>
     */
    public function businessHours(): HasMany
    {
        return $this->hasMany(ProviderBusinessHour::class)->orderBy('weekday');
    }

    /**
     * @return HasMany<ProviderTimeOff, $this>
     */
    public function timeOff(): HasMany
    {
        return $this->hasMany(ProviderTimeOff::class)->orderBy('starts_on');
    }

    /**
     * The working window for a given local date, or null when the provider
     * takes no appointments that day — either the weekday is closed or the
     * date falls inside a time-off block.
     *
     * Falls back to the provider's own columns when no row exists for the
     * weekday. That is not dead code: a provider created before its seven rows
     * are written (or by a seeder that skips them) must keep its schedule
     * rather than silently going dark on every day of the week.
     *
     * @return array{0: int, 1: int}|null [startMinute, endMinute]
     */
    public function workingWindowOn(CarbonImmutable $localDate): ?array
    {
        $date = $localDate->startOfDay();

        $isOff = $this->relationLoaded('timeOff')
            ? $this->timeOff->contains(fn (ProviderTimeOff $off) => $date->betweenIncluded($off->starts_on, $off->ends_on))
            : $this->timeOff()->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->exists();

        if ($isOff) {
            return null;
        }

        $hours = $this->relationLoaded('businessHours')
            ? $this->businessHours->firstWhere('weekday', $date->dayOfWeek)
            : $this->businessHours()->where('weekday', $date->dayOfWeek)->first();

        if ($hours === null) {
            return [$this->work_start_minute, $this->work_end_minute];
        }

        return $hours->is_open ? [$hours->work_start_minute, $hours->work_end_minute] : null;
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

    /**
     * Every new provider starts with all seven days open on the window its own
     * columns carry, which is what the panel edits from. Done on the model
     * rather than at the one registration call site so a provider created by a
     * seeder, a factory or a future flow can never exist without a schedule.
     */
    protected static function booted(): void
    {
        static::created(function (self $provider): void {
            // The work columns carry database defaults, and registration
            // creates a provider without naming them — so in memory they are
            // still null here. Without this reload the seven rows are written
            // with a null window, the insert fails, and the whole registration
            // transaction rolls back.
            $provider->refresh();

            $provider->businessHours()->createMany(
                array_map(fn (int $weekday) => [
                    'weekday' => $weekday,
                    'is_open' => true,
                    'work_start_minute' => $provider->work_start_minute,
                    'work_end_minute' => $provider->work_end_minute,
                ], range(0, 6))
            );
        });
    }
}
