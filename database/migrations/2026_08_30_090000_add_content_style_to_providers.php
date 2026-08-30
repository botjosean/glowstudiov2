<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La ficha de estilo que se saca leyendo sus referencias.
     *
     * Guarda colores, dónde va el texto y qué tipografía usa — datos, no un
     * diseño. El motor de plantillas los usa como ajustes; el modelo nunca
     * dibuja nada.
     *
     * Nulo mientras no la haya generado: ahí manda lo que dicta el rubro.
     */
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->jsonb('content_style')->nullable()->after('business_subcategories');
            // Para poder decirle "esto salió de tus 9 referencias del martes"
            // y saber cuándo conviene volver a leerlas.
            $table->timestamp('content_style_at')->nullable()->after('content_style');
        });
    }

    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn(['content_style', 'content_style_at']);
        });
    }
};
