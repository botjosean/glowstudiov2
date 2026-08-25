<?php

namespace App\Http\Requests\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * La fecha y la hora nuevas de una cita que se mueve.
 *
 * Sólo comprueba la FORMA. Si esa hora está libre de verdad no se decide
 * aquí: se decide dentro del candado, en RescheduleAppointment, porque entre
 * validar y guardar cabe otra reserva. Validar la disponibilidad aquí sería
 * exactamente el agujero que esta tarea vino a cerrar.
 *
 * El permiso —que la cita sea suya— lo pone `->can('update', 'appointment')`
 * en las rutas, igual que confirmar y cancelar.
 */
class RescheduleAppointmentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * La hora que llega es la del reloj de la profesional, no la del servidor
     * ni la del navegador de quien la escribió. Se arma en su huso y la acción
     * la pasa a UTC al guardar.
     */
    public function newLocalStart(): CarbonImmutable
    {
        $provider = $this->route('appointment')->provider;

        return CarbonImmutable::createFromFormat(
            'Y-m-d H:i',
            $this->validated('date').' '.$this->validated('time'),
            $provider->timezone,
        );
    }
}
