<?php

namespace App\Actions\Booking;

use App\Models\Appointment;
use App\Models\Provider;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mover una cita de hora en un solo paso.
 *
 * Hasta hoy no existía: para cambiarle la hora a una clienta había que
 * cancelar y volver a reservar. Y en el hueco entre las dos, la hora vieja
 * quedaba libre y la nueva sin apartar — otra clienta podía entrar por la
 * página pública y llevarse cualquiera de las dos. La profesional se enteraba
 * después, con dos personas citadas o con una cita perdida.
 *
 * Aquí las dos cosas pasan dentro del mismo candado que usa la reserva
 * (`CreateAppointment`): o se mueve entera, o no se mueve nada. No hay
 * instante en que la cita no exista.
 *
 * **Es hermana de CreateAppointment, no una copia con cambios.** Repite su
 * forma a propósito —el mismo bloqueo por profesional, la misma revalidación
 * dentro del candado, la misma red del constraint de Postgres— porque son la
 * misma garantía. Si un día cambia la manera de apartar una hora, tienen que
 * cambiar las dos, y por eso están la una al lado de la otra.
 *
 * **La cita que se mueve no se estorba a sí misma.** El generador de huecos
 * ve ocupada la hora vieja, así que mover una cita quince minutos —o a
 * cualquier hora dentro de su propio colchón— saldría siempre «ocupado». Se le
 * pasan las citas del día ya filtradas sin ella, que es para lo que ese
 * parámetro existe.
 */
class RescheduleAppointment
{
    public function __construct(
        private readonly GenerateAvailableSlots $slots,
    ) {}

    /**
     * @throws ValidationException si la cita no se puede mover, o si la hora
     *                             nueva dejó de estar libre
     */
    public function handle(Appointment $appointment, CarbonImmutable $nuevoInicioLocal): Appointment
    {
        return DB::transaction(function () use ($appointment, $nuevoInicioLocal) {
            // Igual que al reservar: serializa por profesional. Es la
            // garantía portable contra la doble reserva.
            $provider = Provider::whereKey($appointment->provider_id)->lockForUpdate()->firstOrFail();

            // Y la propia cita bajo llave, porque entre que se abrió la
            // pantalla y se toca «guardar» pudieron cancelarla desde el
            // teléfono de al lado.
            $cita = Appointment::whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();

            // Una cita cancelada o ya cerrada no se mueve: se reserva otra.
            // Moverla sería resucitarla sin que nadie lo haya pedido.
            if ($cita->status->isTerminal()) {
                throw ValidationException::withMessages([
                    'time' => __('booking.reschedule_terminal'),
                ]);
            }

            $service = $this->serviceDe($cita);

            $dia = $nuevoInicioLocal->startOfDay();

            // Las citas que estorban ese día, MENOS la que se está moviendo.
            $ocupadas = $provider->appointments()
                ->blocking()
                ->overlapping($dia->utc(), $dia->addDay()->utc())
                ->whereKeyNot($cita->getKey())
                ->get(['starts_at', 'ends_at']);

            $minuto = $nuevoInicioLocal->hour * 60 + $nuevoInicioLocal->minute;
            $libres = $this->slots->handle($provider, $service, $dia, $ocupadas);

            if (! in_array($minuto, $libres, true)) {
                throw ValidationException::withMessages([
                    'time' => __('booking.slot_unavailable'),
                ]);
            }

            try {
                $cita->update([
                    'starts_at' => $nuevoInicioLocal->utc(),
                    'ends_at' => $nuevoInicioLocal->addMinutes($cita->duration_minutes)->utc(),
                ]);
            } catch (QueryException $e) {
                // 23P01 = exclusion_violation, la misma red que al reservar.
                // El candado de arriba debería hacerla inalcanzable; sólo
                // protege de una escritura concurrente que lo esquive.
                if ($e->getCode() === '23P01') {
                    throw ValidationException::withMessages([
                        'time' => __('booking.slot_unavailable'),
                    ]);
                }

                throw $e;
            }

            return $cita;
        });
    }

    /**
     * El generador de huecos sólo mira la duración del servicio, y la cita
     * lleva la suya copiada desde que se creó. Si el servicio se borró o se
     * desactivó desde entonces, se le arma uno de mentira con esa duración:
     * la hora que ocupa una cita no depende de que su servicio siga en el
     * catálogo.
     */
    private function serviceDe(Appointment $cita): Service
    {
        $service = $cita->service;

        if ($service !== null) {
            return $service;
        }

        $suelto = new Service;
        $suelto->duration_minutes = $cita->duration_minutes;

        return $suelto;
    }
}
