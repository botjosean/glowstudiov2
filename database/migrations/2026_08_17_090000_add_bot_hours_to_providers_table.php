<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las horas en que el asistente contesta.
     *
     * Pedido por el dueño el 2026-08-17: de 9 a 22, hora de Atlanta, para todo
     * el mundo. Va por profesional y no en el `.env` por la misma razón que
     * todo lo demás del bot dejó de ser global — una tercera clienta en otro
     * huso, o con otro horario, no debería obligar a un despliegue. Los valores
     * por defecto SON la regla que él pidió, así que nadie tiene que tocar nada
     * para cumplirla.
     *
     * Minutos desde medianoche, la misma unidad que `work_start_minute` y el
     * resto del horario, y se leen en la zona de la profesional
     * (`providers.timezone`), no en la del servidor.
     *
     * Deliberadamente separadas del horario de atención: el salón abre a las 10
     * y el asistente contesta desde las 9, porque acusar recibo no es lo mismo
     * que estar disponible.
     */
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->unsignedSmallInteger('bot_start_minute')->default(9 * 60);
            $table->unsignedSmallInteger('bot_end_minute')->default(22 * 60);
        });
    }

    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn(['bot_start_minute', 'bot_end_minute']);
        });
    }
};
