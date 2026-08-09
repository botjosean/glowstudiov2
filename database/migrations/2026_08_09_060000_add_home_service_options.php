<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Home service becomes a real, per-service choice.
 *
 * Three additive pieces, all defaulting to "off" so no existing row or
 * channel changes behavior on its own:
 *
 * - providers.home_service: "I also go to the client" ON TOP of having a
 *   studio. Together with the existing is_mobile this yields three honest
 *   modes: studio only (F/F), mobile only (is_mobile=T, unchanged), and
 *   both (F/T). is_mobile keeps meaning "no studio at all" exactly as
 *   before, so nothing already live moves.
 *
 * - services.home_available: the safety switch this exists for. The
 *   professional marks, service by service, what she is willing to do in
 *   someone's home — hair styling yes, haircuts no, her call. Unmarked
 *   services are never offered at home by any channel.
 *
 * - appointments.at_home + client_address: where a web booking asked to be
 *   served, captured at booking time. The appointment stays pending until
 *   the professional confirms — that confirmation IS the prior
 *   coordination the owner requires.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->boolean('home_service')->default(false)->after('is_mobile');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->boolean('home_available')->default(false)->after('is_active');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->boolean('at_home')->default(false)->after('status');
            $table->string('client_address', 200)->nullable()->after('at_home');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['at_home', 'client_address']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('home_available');
        });

        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn('home_service');
        });
    }
};
