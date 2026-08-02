<?php

namespace App\Models;

use App\Enums\ServiceCategory;
use App\Enums\ServiceIcon;
use App\Support\Format;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'provider_id', 'service_type_id', 'name', 'duration_minutes', 'price', 'category', 'position', 'is_active',
])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ServiceCategory::class,
            'duration_minutes' => 'int',
            'price' => 'int',
            'position' => 'int',
            'is_active' => 'bool',
        ];
    }

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * @return BelongsTo<ServiceType, $this>
     */
    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    protected function durationLabel(): Attribute
    {
        return Attribute::make(
            get: fn (): string => Format::duration($this->duration_minutes),
        );
    }

    /**
     * A service without a catalog type (icon lives on ServiceType) falls
     * back to the default icon rather than rendering nothing client-side.
     */
    protected function icon(): Attribute
    {
        return Attribute::make(
            get: fn (): ServiceIcon => $this->serviceType?->icon ?? ServiceIcon::default(),
        );
    }
}
