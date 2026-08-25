<?php

use App\Models\Provider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distinguir «este horario lo puso ella» de «este horario nunca lo tocó nadie».
 *
 * Hasta hoy no había forma de saberlo, y la app trataba los dos casos igual:
 * como si estuviera abierta. Por eso Patricia y Vanessa aparecían reservables
 * los siete días de la semana, domingos incluidos, sin haberlo pedido nunca —
 * comprobado contra producción el 25-ago-2026 preguntando día por día.
 *
 * **Cuidado con el diagnóstico fácil.** Podría parecer que el problema es la
 * rama de `workingWindowOn()` que abre el día cuando no hay fila. No lo es, o
 * no sólo: `Provider::booted()` le siembra a toda profesional nueva sus siete
 * días ABIERTOS. O sea que las filas normalmente existen, y existen abiertas.
 * Desde fuera los dos caminos dan exactamente el mismo resultado y no se
 * pueden distinguir. Esta marca arregla los dos a la vez, que es justo lo que
 * hace falta cuando no se puede saber cuál está vivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->timestamp('schedule_saved_at')->nullable()->after('buffer_minutes');
        });

        $this->marcarALasQueYaLoConfiguraron();
    }

    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn('schedule_saved_at');
        });
    }

    /**
     * A quien ya se tomó el trabajo de configurar su semana no se le puede
     * apagar la agenda por una columna nueva.
     *
     * Como no hay registro de quién guardó, se deduce de la forma del horario:
     * si difiere de lo que la siembra deja —los siete días abiertos, todos con
     * la misma ventana que las columnas de la profesional—, es que alguien lo
     * editó.
     *
     * Falla hacia el lado seguro a propósito. Si alguien guardó su semana sin
     * cambiar nada, aquí sale como no configurada: le aparece el aviso y
     * vuelve a guardar, treinta segundos. Al revés —darla por configurada sin
     * estarlo— sería dejar el domingo abierto en silencio, que es el problema
     * que vinimos a arreglar.
     */
    private function marcarALasQueYaLoConfiguraron(): void
    {
        Provider::query()->with('businessHours')->chunkById(100, function ($providers) {
            foreach ($providers as $provider) {
                $horas = $provider->businessHours;

                // Sin filas no hay nada configurado. Este es el caso que la
                // rama vieja de workingWindowOn() trataba como «abierta».
                if ($horas->isEmpty()) {
                    continue;
                }

                $esLaSiembra = $horas->count() === 7
                    && $horas->every(fn ($h) => $h->is_open
                        && $h->work_start_minute === $provider->work_start_minute
                        && $h->work_end_minute === $provider->work_end_minute);

                if ($esLaSiembra) {
                    continue;
                }

                $provider->forceFill(['schedule_saved_at' => now()])->saveQuietly();
            }
        });
    }
};
