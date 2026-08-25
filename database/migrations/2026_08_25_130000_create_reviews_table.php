<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las reseñas de las clientas.
 *
 * En Booksy la ficha de una profesional ABRE con las estrellas, antes que el
 * precio y antes que las fotos: es lo primero que mira quien no la conoce. Sin
 * eso, el perfil de Patricia se lee como una página personal bonita; con eso,
 * se lee como un negocio con clientas.
 *
 * **Una reseña cuelga de una cita, no de una clienta.** No hay cuentas de
 * clienta en esta app —llegan de un enlace de WhatsApp, reservan y se van—, así
 * que la cita es la única prueba de que esa persona estuvo de verdad. De ahí
 * salen las dos reglas que hacen que estas estrellas valgan algo:
 *
 * - `appointment_id` es ÚNICO: una cita, una reseña. Nadie infla su promedio
 *   contestando diez veces.
 * - Sólo se pide por una cita CERRADA, o sea que ya pasó. No se puede reseñar
 *   algo que todavía no ocurrió.
 *
 * El `token` es la llave del enlace que se le manda por WhatsApp. Va aparte
 * del id a propósito: con el id, cualquiera contaría de uno en uno y dejaría
 * reseñas en citas ajenas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();

            // Si se borra la cita, se va la reseña: sin cita no hay prueba de
            // que esa clienta estuviera, y una estrella sin respaldo es
            // exactamente lo que estas dos reglas vienen a evitar.
            $table->foreignId('appointment_id')->unique()->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('rating');

            // Opcional: mucha gente pone estrellas y no escribe. Obligar a
            // escribir es la forma más rápida de quedarse sin reseñas.
            $table->text('comment')->nullable();

            // Copiado de la cita al crear la reseña, como ya se copian el
            // nombre y el precio del servicio: si mañana la clienta cambia de
            // nombre, la reseña sigue diciendo quién la escribió entonces.
            $table->string('client_name');

            // La llave del enlace. Nace con la cita cerrada y muere al usarse.
            $table->string('token', 64)->unique();
            $table->timestamp('answered_at')->nullable();

            $table->timestamps();

            // La consulta de la ficha pública: las de esta profesional, ya
            // contestadas, de la más nueva a la más vieja.
            $table->index(['provider_id', 'answered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
