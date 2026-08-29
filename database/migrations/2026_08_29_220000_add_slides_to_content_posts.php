<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un post pasa a ser un carrusel: varias láminas, no una sola imagen.
     *
     * Es lo que ella pedía desde el principio y yo venía entendiendo mal:
     * «no es que si sube 10 fotos arme algo de 10 fotos, la idea es armar
     * collage, armar el carrusel, armar todo ese tipo de cosas». Diez fotos
     * no son un collage de diez cuadraditos ilegibles — son una portada con
     * titular, un par de collages y un cierre.
     *
     * `path` se queda como la portada (la lámina 1) para no romper los posts
     * que ya existen ni las pantallas que lo leen.
     */
    public function up(): void
    {
        Schema::table('content_posts', function (Blueprint $table) {
            $table->jsonb('slides')->nullable()->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('content_posts', function (Blueprint $table) {
            $table->dropColumn('slides');
        });
    }
};
