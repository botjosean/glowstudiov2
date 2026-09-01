<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El tono de la pieza, de 0 a 359, o null si es gris.
     *
     * Sirve para la bandeja de temporada: en septiembre se le ofrecen
     * primero los adornos ámbar y terracota, en febrero los rosados. El
     * pack no viene ordenado por época —eso se lo dije a ella—, así que
     * esto es lo más honesto que se puede hacer con lo que trae: agrupar
     * por color y dejar que la época elija la familia.
     */
    public function up(): void
    {
        Schema::table('content_assets', function (Blueprint $table) {
            $table->unsignedSmallInteger('hue')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('content_assets', function (Blueprint $table) {
            $table->dropColumn('hue');
        });
    }
};
