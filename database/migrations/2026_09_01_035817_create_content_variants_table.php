<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los borradores que ella mira antes de elegir.
     *
     * La idea es de ella: «la persona sube la imagen y ya de una vez ofrece
     * varias opciones, mirá cuál te gusta, esta no, esta sí». Hasta que
     * elija una, ninguna es un post — por eso viven acá y no en
     * content_posts, que es lo que ya publicó.
     *
     * Se borran solas cuando elige o cuando pide otras: no tiene sentido
     * acumular borradores descartados.
     */
    public function up(): void
    {
        Schema::create('content_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            // 'hero', 'collage', 'color' — para saber qué fotos consumió.
            $table->string('kind', 20);
            // Las fotos con las que se armó, para marcarlas usadas si esta
            // es la que elige.
            $table->json('source_ids');
            // La descripción y los hashtags se escriben UNA vez para toda la
            // tanda: seis opciones cuestan lo mismo que una.
            $table->text('caption')->nullable();
            $table->json('hashtags')->nullable();
            $table->timestamps();

            $table->index('provider_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_variants');
    }
};
