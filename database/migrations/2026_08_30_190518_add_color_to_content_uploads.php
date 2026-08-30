<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El color del trabajo de esa foto, en las palabras de ella.
     *
     * No sale de mirar los píxeles: se probó y no funciona. En una foto de
     * uñas lo que más superficie ocupa es la piel, la ropa y la mesa, no el
     * esmalte — comprobado contra fotos reales de Vane, donde unas uñas
     * rosa daban "negro" porque ganaba la ropa del fondo.
     *
     * Lo propone el modelo de visión y ella lo corrige, que es el mismo
     * arreglo que ya funciona con las notas de las referencias: «yo detecto
     * tal cosa, ¿puedes decirnos algo más de los colores como tú lo ves, con
     * tus propias palabras?». Su palabra manda sobre la del modelo.
     */
    public function up(): void
    {
        Schema::table('content_uploads', function (Blueprint $table) {
            // Cómo lo llama ella: "rosa bebé con blanco", "vino oscuro".
            $table->string('color_name', 60)->nullable();
            // Y el tono para pintar fondos y tarjetas, en #RRGGBB.
            $table->string('color_hex', 7)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('content_uploads', function (Blueprint $table) {
            $table->dropColumn(['color_name', 'color_hex']);
        });
    }
};
