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
        Schema::create('whatsapp_idempotency_keys', function (Blueprint $table) {
            $table->id();

            // 'delivery' (Kapso's X-Idempotency-Key), 'message' (WAMID) or
            // 'reply' (a reply already sent for that WAMID). Scoped rather than
            // three tables: the shape and the lifetime are identical, only the
            // meaning differs.
            $table->string('scope', 16);
            $table->string('idempotency_key', 191);

            // No updated_at: a claim is written once and never modified.
            $table->timestamp('created_at')->nullable();

            // The uniqueness that makes claiming atomic — two concurrent
            // retries both INSERT, and the database decides which one wins.
            $table->unique(['scope', 'idempotency_key']);

            // For pruning old claims; a retry window is minutes, so rows older
            // than a few days carry no information.
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_idempotency_keys');
    }
};
