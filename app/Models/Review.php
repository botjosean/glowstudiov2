<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Una reseña: las estrellas que una clienta le deja a una profesional.
 *
 * Cuelga de la cita, no de la clienta. Ver la migración para el porqué.
 */
class Review extends Model
{
    /** @use HasFactory<\Database\Factories\ReviewFactory> */
    use HasFactory;

    protected $fillable = [
        'provider_id', 'appointment_id', 'rating', 'comment', 'client_name', 'token', 'answered_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'int',
            'answered_at' => 'immutable_datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Las que la clienta ya contestó. Una invitación sin contestar existe en
     * la tabla —es la que guarda el token del enlace— pero no es una reseña
     * todavía y no puede contar para el promedio.
     */
    #[Scope]
    protected function answered(Builder $query): void
    {
        $query->whereNotNull('answered_at');
    }

    /**
     * La llave del enlace que se manda por WhatsApp.
     *
     * 32 bytes de aleatorio de verdad. Va aparte del id a propósito: con el id
     * cualquiera contaría de uno en uno y dejaría reseñas en citas ajenas.
     */
    public static function newToken(): string
    {
        return Str::random(48);
    }
}
