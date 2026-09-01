<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El catálogo de piezas del pack de contenido que ella compró: frases
     * con letra de diseñador, elementos ilustrados, marcos, sombras y
     * adornos.
     *
     * **Por qué importa.** Hasta ahora los titulares se dibujaban con
     * tipografías, y ella lo señaló post tras post: «no son las letras, no
     * logras llegar al punto». Tenía razón, y no era cuestión de seguir
     * puliendo — estas frases las diseñó una persona, y con código no se
     * llega ahí. Ahora se estampan en vez de dibujarse.
     *
     * Las piezas son de ella, con licencia permanente, y viven en su propio
     * bucket. No se reparten entre proveedoras de otras cuentas.
     */
    public function up(): void
    {
        Schema::create('content_assets', function (Blueprint $table) {
            $table->id();

            // frase | elemento | marco | sombra | decorativo
            $table->string('kind', 20);

            // El rubro al que pertenece, o null si sirve para cualquiera.
            $table->string('trade', 40)->nullable();

            $table->string('path');

            // Si la tinta es oscura o clara. El pack trae la misma frase en
            // los dos colores, así que se elige según qué tan clara sea la
            // foto — que es justo lo que hacía falta para que el texto se
            // lea sobre unas uñas blancas igual que sobre una mesa negra.
            $table->string('ink', 10)->nullable();

            // Qué dice, leído una sola vez. Sirve para estampar el nombre
            // del servicio que de verdad se hizo.
            $table->string('text', 120)->nullable();
            $table->string('slug', 120)->nullable();

            // Dónde cae el dibujo dentro del lienzo de 1080: el arte viene
            // centrado, así que sin esto no se puede recolocar arriba o
            // abajo sin recortar a ciegas.
            $table->unsignedSmallInteger('box_x')->default(0);
            $table->unsignedSmallInteger('box_y')->default(0);
            $table->unsignedSmallInteger('box_w')->default(0);
            $table->unsignedSmallInteger('box_h')->default(0);

            $table->timestamps();

            $table->unique('path');
            $table->index(['kind', 'trade']);
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_assets');
    }
};
