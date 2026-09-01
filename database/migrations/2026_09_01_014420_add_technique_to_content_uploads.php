<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Qué técnica se ve en la foto: "acrílicas", "balayage", "microblading".
     *
     * El pack trae esas mismas palabras escritas por un diseñador. Sabiendo
     * cuál es, el post estampa la que corresponde en vez de una frase al
     * azar — que es la diferencia entre un post que dice la verdad y uno
     * que dice "Rubber" sobre un trabajo de acrílicas.
     *
     * Sale de la MISMA llamada que ya lee el color al subir (ver
     * ReadPhotoColor), así que no cuesta una llamada más ni le pide un dato
     * más a ella. Eso importa: pidió que subir fotos sea lo más simple
     * posible, no que haya otra pregunta antes de cada post.
     */
    public function up(): void
    {
        Schema::table('content_uploads', function (Blueprint $table) {
            $table->string('technique', 60)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('content_uploads', function (Blueprint $table) {
            $table->dropColumn('technique');
        });
    }
};
