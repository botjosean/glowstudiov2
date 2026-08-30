<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La foto decorativa que acompaña al color en "Fondo de color" —el
     * limón para "Butter Yellow", la fresa para "Strawberry Red" de las
     * referencias de Mimosa Studio— generada una vez por IA y reusada
     * siempre que haga falta.
     *
     * Se cachea por rubro y por color del catálogo (ver ColorNames), no por
     * proveedora ni por post: el limón de "Butter Yellow" le sirve a
     * cualquiera con uñas amarillas, así que generarlo una sola vez para
     * TODAS ahorra el gasto de repetirlo por cada post.
     */
    public function up(): void
    {
        Schema::create('color_props', function (Blueprint $table) {
            $table->id();
            $table->string('business_category', 40);
            // El hex del catálogo de ColorNames, no el hex exacto leído de
            // la foto: son ~26 colores fijos, así que el caché tiene un
            // techo conocido en vez de crecer sin límite.
            $table->string('color_key', 7);
            $table->string('path');
            $table->timestamps();

            $table->unique(['business_category', 'color_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('color_props');
    }
};
