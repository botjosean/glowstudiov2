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
        // La ficha leída de ESTA referencia puntual, cuando ella la elige
        // como plantilla — a diferencia de provider.content_style, que es la
        // mezcla de todas. En caché para no pagar la llamada de visión cada
        // vez que vuelve a elegir la misma.
        Schema::table('content_uploads', function (Blueprint $table) {
            $table->jsonb('learned_style')->nullable();
            $table->timestamp('learned_style_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_uploads', function (Blueprint $table) {
            $table->dropColumn(['learned_style', 'learned_style_at']);
        });
    }
};
