<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foto o video.
     *
     * Los videos entran solo como referencia y de a uno: son los tutoriales
     * que ella tiene sobre cómo encuadrar, qué tipografía usar o cómo armar
     * un carrusel. No se publican y no se pueden meter en un collage — el
     * collage necesita imágenes.
     *
     * Con valor por defecto para las filas que ya existen: todo lo subido
     * antes de esta columna era foto.
     */
    public function up(): void
    {
        Schema::table('content_uploads', function (Blueprint $table) {
            $table->string('kind', 8)->default('image')->after('path');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE content_uploads ADD CONSTRAINT content_uploads_kind_chk '
                ."CHECK (kind IN ('image','video'))"
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE content_uploads DROP CONSTRAINT IF EXISTS content_uploads_kind_chk');
        }

        Schema::table('content_uploads', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
