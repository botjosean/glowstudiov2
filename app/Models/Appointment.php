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
use Illuminate\Database\QueryException;

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

    /**
     * Every booked phone grows a card in the provider's client book, whatever
     * the channel — bot, public page or the panel's manual flow all end up
     * here. Done on the model rather than in CreateAppointment so no future
     * caller can forget it. firstOrCreate keeps the existing card untouched:
     * the professional's own edits (name fixes, notes) always win over a
     * booking snapshot.
     */
    protected static function booted(): void
    {
        static::created(function (self $appointment): void {
            $phone = (string) $appointment->client_phone;

            if (preg_match('/^\d{10}$/', $phone) !== 1) {
                return;
            }

            try {
                Client::firstOrCreate(
                    ['provider_id' => $appointment->provider_id, 'phone' => $phone],
                    ['name' => $appointment->client_name],
                );
            } catch (QueryException) {
                // A concurrent booking already created the card. The card is a
                // convenience; it must never break the booking that spawned it.
            }

            // A booking is the ending a WhatsApp request was waiting for, so
            // the card closes itself. Without this the professional would book
            // her and then have to remember to tick the request off, which is
            // exactly the kind of bookkeeping nobody does twice.
            Lead::query()
                ->where('provider_id', $appointment->provider_id)
                ->where('phone', $phone)
                ->where('status', Lead::STATUS_NEW)
                ->update([
                    'status' => Lead::STATUS_HANDLED,
                    'answered_at' => now(),
                    'updated_at' => now(),
                ]);
        });
    }
}
