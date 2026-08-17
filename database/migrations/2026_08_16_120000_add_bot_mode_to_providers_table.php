<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Turns the assistant's behaviour into per-provider data.
     *
     * Requested by Patricia on 2026-08-16 (voice note transcribed in "audio de
     * clienta/"): she does not want an agent that quotes prices and books for
     * her, she wants a receptionist that says the message arrived, asks for a
     * few things, and gets out of the way. Two failures she named: the
     * assistant rambling at a client who asked about a service that is not in
     * her two-service catalogue, and greeting a client who was already on her
     * way to an appointment — which made that client wonder whether she had
     * the wrong number.
     *
     * **Every default here reproduces today's behaviour exactly.** `agente` is
     * what every existing provider gets, so this migration changes nothing for
     * anybody until a mode is flipped by hand. That is deliberate: the number
     * is live, and a migration that alters how a bot talks to real clients the
     * moment it runs is not something to deploy on a Sunday.
     *
     * The text columns are nullable and fall back to the defaults in
     * App\Support\Assistant\Receptionist, so the wording can be changed from
     * the panel (or with one UPDATE) without a deploy — the same reason
     * negocio.md lives on a mounted volume.
     */
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            // 'agente'        = today's assistant: tools, catalogue, booking.
            // 'recepcionista' = acknowledge, ask, hand over. No model call at
            //                   all, so it cannot invent an appointment.
            $table->string('bot_mode', 20)->default('agente');

            // How the assistant names the trade to a stranger: "peluquera",
            // "manicurista", "barbero". Null keeps the generic wording.
            $table->string('bot_trade', 40)->nullable();

            // The business name the assistant introduces itself with. Null
            // falls back to the app-wide default, which is how "Glow Studio"
            // stopped being hardcoded in SystemPrompt.
            $table->string('bot_business_name', 80)->nullable();

            // The two messages, and only two.
            $table->text('bot_greeting')->nullable();
            $table->text('bot_greeting_returning')->nullable();
            $table->text('bot_intake')->nullable();

            // Whether the second message offers the public booking page. Off
            // for a professional who does not want her prices quoted before
            // she has spoken to the client — that page lists them.
            $table->boolean('bot_offers_booking_link')->default(true);

            // Per-provider replacement for the single shared negocio.md. Null
            // keeps reading the shared file, so nothing moves until it is
            // filled in.
            $table->text('bot_notes')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE providers ADD CONSTRAINT providers_bot_mode_chk CHECK (bot_mode IN ('agente', 'recepcionista'))"
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE providers DROP CONSTRAINT IF EXISTS providers_bot_mode_chk');
        }

        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn([
                'bot_mode', 'bot_trade', 'bot_business_name',
                'bot_greeting', 'bot_greeting_returning', 'bot_intake',
                'bot_offers_booking_link', 'bot_notes',
            ]);
        });
    }
};
