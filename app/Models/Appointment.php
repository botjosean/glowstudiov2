<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Support\Format;
use Carbon\CarbonInterface;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'provider_id', 'service_id', 'client_name', 'client_phone',
    'service_name', 'duration_minutes', 'price',
    'starts_at', 'ends_at', 'status', 'confirmed_at', 'cancelled_at',
    'at_home', 'client_address',
])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Immutable: the slot algorithm does heavy ->addMinutes() chaining,
            // and mutable Carbon there is a classic source of silent bugs.
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'status' => AppointmentStatus::class,
            'duration_minutes' => 'int',
            'price' => 'int',
            'at_home' => 'bool',
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
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    #[Scope]
    protected function blocking(Builder $query): void
    {
        $query->whereIn('status', AppointmentStatus::blocking());
    }

    #[Scope]
    protected function overlapping(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->where('starts_at', '<', $to)->where('ends_at', '>', $from);
    }

    protected function durationLabel(): Attribute
    {
        return Attribute::make(
            get: fn (): string => Format::duration($this->duration_minutes),
        );
    }
}
