<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El Taller de Contenido: ella sube fotos y la app le devuelve el post
     * armado.
     *
     * Dos tablas y no una porque son dos cosas distintas de verdad: lo que
     * ella subió (`content_uploads`) sobrevive al post que se generó con
     * ello, y una referencia no genera ningún post — solo enseña estilo.
     */
    public function up(): void
    {
        Schema::create('content_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();

            // Clave de R2, igual que provider_photos.url — nunca el nombre
            // que traía el archivo de la clienta.
            $table->string('path');

            // 'edit' = para armar un post. 'reference' = para enseñar estilo.
            // Preguntado de una en la subida: adivinarlo salía mal en los dos
            // sentidos (armar un post que no pidió, o archivar lo que sí).
            $table->string('purpose', 16);

            // Sus propias palabras sobre la referencia, dictadas o escritas.
            // Una foto sola dice poco; el "por qué me gusta" es lo que
            // convierte una carpeta de imágenes en instrucciones.
            $table->text('note')->nullable();

            // Cuándo se usó para armar un post. Las de 'edit' sin usar son
            // justamente las que esperan que ella elija un modelo. No se
            // borran al usarlas: el archivo en R2 sigue vivo para poder
            // rehacer el mismo post con otro modelo más adelante.
            $table->timestamp('used_at')->nullable();

            $table->timestamps();

            $table->index(['provider_id', 'purpose']);
        });

        Schema::create('content_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();

            $table->string('layout', 32);
            $table->string('path');
            $table->text('caption')->nullable();
            $table->jsonb('hashtags')->nullable();

            // De qué fotos salió. Guardado para poder rehacer el mismo post
            // con otro modelo sin pedirle que las suba de nuevo.
            $table->jsonb('source_paths')->nullable();

            // 'up' | 'down' | null. El motivo del pulgar abajo es lo único
            // que de verdad sirve para ajustar las plantillas después.
            $table->string('rating', 8)->nullable();
            $table->text('rating_note')->nullable();

            $table->timestamps();

            $table->index(['provider_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_posts');
        Schema::dropIfExists('content_uploads');
    }
};
