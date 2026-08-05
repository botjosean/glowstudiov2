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
        Schema::create('whatsapp_opt_outs', function (Blueprint $table) {
            $table->id();

            // Per number, not global: someone may want the nails line to stop
            // writing while still expecting answers from the hair line. They
            // are different businesses to the client.
            $table->string('phone_number_id', 32);
            $table->string('phone', 20);

            // No updated_at: an opt-out is created or deleted, never edited.
            $table->timestamp('created_at')->nullable();

            $table->unique(['phone_number_id', 'phone']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_opt_outs');
    }
};
