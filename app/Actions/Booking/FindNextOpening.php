<?php

namespace App\Actions\Booking;

use App\Models\Provider;
use App\Models\Service;
use Illuminate\Support\Collection;

/**
 * El primer hueco libre de cada servicio, para enseñarlo en la vitrina.
 *
 * En la ficha de una profesional los servicios dicen nombre, duración y
 * precio. Lo que no dicen es lo único que la clienta vino a averiguar: si
 * puede ir hoy. Booksy y Wix ponen «Hoy 3:30 PM» debajo del precio y es de los
 * cambios que más suben las reservas — la persona ve que hay sitio y toca, en
 * vez de abrir un calendario a ver si tiene suerte.
 *
 * **Esto no calcula huecos.** Se los pide a `GenerateAvailableSlots`, que es
 * el único que sabe de horarios, almuerzos, vacaciones y colchones entre
 * citas. Si algún día cambia una regla ahí, cambia aquí sola: no hay una
 * segunda cuenta que pueda quedarse atrás y enseñar en la vitrina una hora que
 * al reservar ya no existe.
 *
 * **Una sola consulta para toda la ficha.** El generador, llamado a secas,
 * consulta las citas del día. Con cinco servicios por catorce días serían
 * setenta consultas para pintar una pantalla. Aquí las citas de toda la
 * ventana se traen de una vez y se le pasan ya cortadas por día, que es
 * justo para lo que ese parámetro existe; y el bucle corta en cuanto los
 * servicios que faltaban ya tienen respuesta.
 */
class FindNextOpening
{
    /**
     * Dos semanas. Más lejos deja de ser un gancho: «el 12 de octubre» no
     * invita a nadie, y la clienta que reserva con tanta antelación abre el
     * calendario igual. Menos que esto y una profesional con la agenda llena
     * saldría siempre sin nada, que se lee como cerrada.
     */
    public const LOOKAHEAD_DAYS = 14;

    /**
     * @param  Collection<int, Service>  $services
     * @return array<int, array{date: string, h: int, m: int, daysAway: int}|null>
     *                                                                             indexado por id de servicio; null si no hay hueco en la ventana
     */
    public function forServices(Provider $provider, Collection $services, GenerateAvailableSlots $slots): array
    {
        $resultado = [];

        foreach ($services as $service) {
            $resultado[$service->id] = null;
        }

        if ($services->isEmpty()) {
            return $resultado;
        }

        $hoy = $provider->currentTime()->startOfDay();

        // Igual que hace forMonth(): una consulta para toda la ventana, y
        // después se corta por día en memoria.
        $citas = $provider->appointments()
            ->blocking()
            ->overlapping($hoy->utc(), $hoy->addDays(self::LOOKAHEAD_DAYS + 1)->utc())
            ->get(['starts_at', 'ends_at']);

        // Horarios y vacaciones, una vez para todos los días y servicios.
        $provider->loadMissing(['businessHours', 'timeOff']);

        $pendientes = $services->keyBy('id');

        for ($dia = 0; $dia <= self::LOOKAHEAD_DAYS && $pendientes->isNotEmpty(); $dia++) {
            $fecha = $hoy->addDays($dia);

            // El corte del día se hace UNA vez, no una por servicio.
            $citasDelDia = $citas->filter(
                fn ($cita) => $cita->starts_at->setTimezone($provider->timezone)->isSameDay($fecha)
                    || $cita->ends_at->setTimezone($provider->timezone)->isSameDay($fecha)
            );

            foreach ($pendientes as $id => $service) {
                $libres = $slots->handle($provider, $service, $fecha, $citasDelDia);

                if ($libres === []) {
                    continue;
                }

                $minuto = $libres[0];

                $resultado[$id] = [
                    'date' => $fecha->toDateString(),
                    'h' => intdiv($minuto, 60),
                    'm' => $minuto % 60,
                    'daysAway' => $dia,
                ];

                $pendientes->forget($id);
            }
        }

        return $resultado;
    }
}
