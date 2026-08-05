<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            // Which WhatsApp business number reaches this professional. The
            // database owns this mapping rather than a config file because
            // Provider is already the tenant here — routing a message is the
            // same question as "whose agenda is this", and answering it in two
            // places is how they drift apart.
            //
            // Unique: two professionals sharing one number would make the
            // routing ambiguous. Nullable: a provider without a connected
            // number is the normal starting state.
            $table->string('whatsapp_phone_number_id', 32)->nullable()->unique()->after('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn('whatsapp_phone_number_id');
        });
    }
};
