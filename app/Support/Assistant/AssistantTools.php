<?php

namespace App\Support\Assistant;

use App\Actions\Booking\CreateAppointment;
use App\Actions\Booking\GenerateAvailableSlots;
use App\Enums\AppointmentStatus;
use App\Events\AppointmentRequested;
use App\Models\Appointment;
use App\Models\Service;
use App\Notifications\HumanHandoffRequested;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The tools the model may propose, and the only place they are carried out.
 *
 * The division of labour is deliberate and absolute: **the model proposes, the
 * server authorises and executes.** Nothing here trusts an argument. Every
 * lookup is re-scoped to the provider and client phone that the webhook already
 * established (see ToolContext), so a model that invents an appointment id or
 * a service belonging to somebody else gets "not found" rather than access. No
 * tool builds SQL from model output, and none of them can widen their own
 * scope.
 *
 * The tools themselves are thin adapters over the booking logic this app
 * already had — GenerateAvailableSlots and CreateAppointment — so availability
 * and double-booking protection behave exactly as they do for the website. No
 * second implementation of either exists.
 */
class AssistantTools
{
    public function __construct(
        private readonly GenerateAvailableSlots $slots,
        private readonly CreateAppointment $createAppointment,
    ) {}

    /**
     * The tool schemas sent to Groq.
     *
     * Descriptions are in Spanish because the whole conversation is, and the
     * model picks tools more reliably when the schema speaks the same language
     * as the client. `additionalProperties: false` keeps invented arguments out
     * before validation has to reject them.
     *
     * @return list<array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            $this->definition(
                'listar_servicios',
                'Lista los servicios que ofrece esta profesional, con precio en dólares y duración en minutos. Úsala siempre antes de hablar de precios: nunca los inventes.',
            ),
            $this->definition(
                'buscar_disponibilidad',
                'Devuelve las horas libres reales de un día concreto para un servicio. Úsala siempre antes de ofrecer una hora: nunca inventes disponibilidad.',
                [
                    'servicio_id' => ['type' => 'integer', 'description' => 'El id del servicio, tal como lo devolvió listar_servicios.'],
                    'fecha' => ['type' => 'string', 'description' => 'La fecha en formato AAAA-MM-DD.'],
                ],
                ['servicio_id', 'fecha'],
            ),
            $this->definition(
                'crear_cita',
                'Reserva la cita. Llámala SOLO después de haberle repetido a la clienta el servicio, la fecha completa, la hora y el precio, y de que ella haya dicho que sí explícitamente.',
                [
                    'servicio_id' => ['type' => 'integer', 'description' => 'El id del servicio.'],
                    'fecha' => ['type' => 'string', 'description' => 'La fecha en formato AAAA-MM-DD.'],
                    'hora' => ['type' => 'string', 'description' => 'La hora en formato HH:MM de 24 horas, tomada de buscar_disponibilidad.'],
                    'nombre_completo' => ['type' => 'string', 'description' => 'El nombre y apellido de la clienta. Pregúntaselo si no lo sabes.'],
                ],
                ['servicio_id', 'fecha', 'hora', 'nombre_completo'],
            ),
            $this->definition(
                'listar_mis_citas',
                'Muestra las próximas citas de esta clienta. No necesita datos: el sistema ya sabe quién escribe.',
            ),
            $this->definition(
                'cancelar_cita',
                'Cancela una cita de esta clienta. Confirma con ella cuál es antes de llamarla.',
                ['cita_id' => ['type' => 'integer', 'description' => 'El id de la cita, tal como lo devolvió listar_mis_citas.']],
                ['cita_id'],
            ),
            $this->definition(
                'solicitar_atencion_humana',
                'Avisa a una persona del salón. Úsala cuando la clienta lo pida, se queje, o pregunte algo que no puedes resolver con las demás herramientas.',
                ['motivo' => ['type' => 'string', 'description' => 'En una frase, qué necesita la clienta.']],
                ['motivo'],
            ),
        ];
    }

    /**
     * Runs a proposed tool call.
     *
     * Always returns an array for the model to read, never throws: a tool that
     * blew up would abort the conversation, whereas a returned `error` lets the
     * model apologise, ask again, or hand off. Unknown tool names land here too
     * — the model can hallucinate one.
     *
     * @param  array<mixed>  $arguments
     * @return array<string, mixed>
     */
    public function run(string $name, array $arguments, ToolContext $context): array
    {
        return match ($name) {
            'listar_servicios' => $this->listServices($context),
            'buscar_disponibilidad' => $this->findAvailability($arguments, $context),
            'crear_cita' => $this->createBooking($arguments, $context),
            'listar_mis_citas' => $this->listOwnAppointments($context),
            'cancelar_cita' => $this->cancelAppointment($arguments, $context),
            'solicitar_atencion_humana' => $this->requestHuman($arguments, $context),
            default => ['error' => "La herramienta '{$name}' no existe."],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function listServices(ToolContext $context): array
    {
        $services = $context->provider->services()
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        if ($services->isEmpty()) {
            // Said explicitly rather than returning an empty list, because an
            // empty list is exactly the situation where a model is tempted to
            // fill the silence with invented services and prices.
            return [
                'servicios' => [],
                'aviso' => 'Esta profesional todavía no tiene servicios publicados. No inventes ninguno: dile a la clienta que en un momento le confirma una persona del salón y usa solicitar_atencion_humana.',
            ];
        }

        return [
            'servicios' => $services->map(fn (Service $service): array => [
                'id' => $service->id,
                'nombre' => $service->name,
                'precio_usd' => $service->price,
                'duracion' => Format::duration($service->duration_minutes),
            ])->all(),
        ];
    }

    /**
     * @param  array<mixed>  $arguments
     * @return array<string, mixed>
     */
    private function findAvailability(array $arguments, ToolContext $context): array
    {
        $validated = $this->validate($arguments, [
            'servicio_id' => ['required', 'integer'],
            'fecha' => ['required', 'date_format:Y-m-d'],
        ]);

        if (isset($validated['error'])) {
            return $validated;
        }

        $service = $this->ownService((int) $validated['servicio_id'], $context);

        if ($service === null) {
            return ['error' => 'Ese servicio no existe. Vuelve a llamar a listar_servicios.'];
        }

        $date = CarbonImmutable::createFromFormat('Y-m-d', $validated['fecha'], $context->provider->timezone)->startOfDay();

        $minutes = $this->slots->handle($context->provider, $service, $date);

        if ($minutes === []) {
            return [
                'fecha' => $date->toDateString(),
                'horas_disponibles' => [],
                'aviso' => 'No queda ningún hueco ese día. Ofrécele otro día; no inventes horas.',
            ];
        }

        return [
            'fecha' => $date->toDateString(),
            'servicio' => $service->name,
            'horas_disponibles' => array_map(
                static fn (int $minute): string => sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60),
                $minutes,
            ),
        ];
    }

    /**
     * @param  array<mixed>  $arguments
     * @return array<string, mixed>
     */
    private function createBooking(array $arguments, ToolContext $context): array
    {
        $validated = $this->validate($arguments, [
            'servicio_id' => ['required', 'integer'],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora' => ['required', 'date_format:H:i'],
            'nombre_completo' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        if (isset($validated['error'])) {
            return $validated;
        }

        $service = $this->ownService((int) $validated['servicio_id'], $context);

        if ($service === null) {
            return ['error' => 'Ese servicio no existe. Vuelve a llamar a listar_servicios.'];
        }

        $localStart = CarbonImmutable::createFromFormat(
            'Y-m-d H:i',
            "{$validated['fecha']} {$validated['hora']}",
            $context->provider->timezone,
        );

        // Idempotent by the appointment's own identity rather than by a stored
        // key: if this exact slot is already booked for this client, the answer
        // is that same appointment. A retried job (or a model that calls the
        // tool twice) therefore cannot produce two bookings, and no extra
        // bookkeeping table is needed to guarantee it.
        $existing = Appointment::query()
            ->where('provider_id', $context->provider->id)
            ->where('client_phone', $context->storedPhone)
            ->where('starts_at', $localStart->utc())
            ->whereIn('status', AppointmentStatus::blocking())
            ->first();

        if ($existing !== null) {
            return [
                'cita_id' => $existing->id,
                'ya_estaba_reservada' => true,
                'resumen' => $this->summarise($existing, $context),
            ];
        }

        try {
            $appointment = $this->createAppointment->handle(
                provider: $context->provider,
                service: $service,
                localStart: $localStart,
                clientName: trim((string) $validated['nombre_completo']),
                phoneDigits: $context->storedPhone,
            );
        } catch (ValidationException) {
            // The slot went while they were deciding. CreateAppointment
            // revalidates inside a row lock, so this is the honest answer, not
            // a race we can paper over.
            return ['error' => 'Esa hora ya se ocupó. Llama otra vez a buscar_disponibilidad y ofrécele las horas que queden.'];
        }

        // The website fires this from its controller after the transaction
        // commits, not from inside CreateAppointment — so a caller that skips
        // the controller has to fire it too, or the professional never learns
        // she has a new appointment.
        event(new AppointmentRequested($appointment));

        return [
            'cita_id' => $appointment->id,
            'estado' => 'pendiente de confirmación',
            'resumen' => $this->summarise($appointment, $context),
            'aviso' => 'Dile a la clienta que queda registrada y que el salón se la confirma en breve.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listOwnAppointments(ToolContext $context): array
    {
        // Scoped to provider + this client's phone. This is the authorisation,
        // and it lives here rather than in anything the model can influence.
        $appointments = Appointment::query()
            ->where('provider_id', $context->provider->id)
            ->where('client_phone', $context->storedPhone)
            ->whereIn('status', AppointmentStatus::blocking())
            ->where('starts_at', '>=', now()->utc())
            ->orderBy('starts_at')
            ->limit(10)
            ->get();

        return [
            'citas' => $appointments->map(fn (Appointment $appointment): array => [
                'cita_id' => $appointment->id,
                'resumen' => $this->summarise($appointment, $context),
                'estado' => $appointment->status === AppointmentStatus::Confirmed ? 'confirmada' : 'pendiente de confirmación',
            ])->all(),
        ];
    }

    /**
     * @param  array<mixed>  $arguments
     * @return array<string, mixed>
     */
    private function cancelAppointment(array $arguments, ToolContext $context): array
    {
        $validated = $this->validate($arguments, ['cita_id' => ['required', 'integer']]);

        if (isset($validated['error'])) {
            return $validated;
        }

        $appointment = Appointment::query()
            ->where('id', (int) $validated['cita_id'])
            ->where('provider_id', $context->provider->id)
            ->where('client_phone', $context->storedPhone)
            ->first();

        if ($appointment === null) {
            // Deliberately the same answer whether the appointment is somebody
            // else's or does not exist: distinguishing them would let anyone
            // with WhatsApp probe for other people's bookings.
            return ['error' => 'No encontré ninguna cita tuya con ese número.'];
        }

        if (! $appointment->status->canTransitionTo(AppointmentStatus::Cancelled)) {
            return ['error' => 'Esa cita ya no se puede cancelar porque está cerrada o ya estaba cancelada.'];
        }

        $appointment->update([
            'status' => AppointmentStatus::Cancelled->value,
            'cancelled_at' => now(),
        ]);

        return [
            'cancelada' => true,
            'resumen' => $this->summarise($appointment, $context),
        ];
    }

    /**
     * @param  array<mixed>  $arguments
     * @return array<string, mixed>
     */
    private function requestHuman(array $arguments, ToolContext $context): array
    {
        $validated = $this->validate($arguments, ['motivo' => ['required', 'string', 'max:400']]);

        if (isset($validated['error'])) {
            return $validated;
        }

        $reason = trim((string) $validated['motivo']);

        Log::warning('WhatsApp assistant requested a human.', [
            'provider' => $context->provider->slug,
            'reason' => $reason,
        ]);

        $user = $context->provider->loadMissing('user')->user;

        $user?->notify(new HumanHandoffRequested(
            clientPhone: $context->whatsappPhone,
            clientName: $context->clientName,
            reason: $reason,
        ));

        return [
            'registrado' => true,
            'aviso' => 'Ya avisaste al salón. Dile a la clienta que una persona le escribe en breve, y no le prometas nada más.',
        ];
    }

    /**
     * A full, unambiguous restatement — the thing the assistant must read back
     * before anything is created or cancelled.
     */
    private function summarise(Appointment $appointment, ToolContext $context): string
    {
        $localStart = $appointment->starts_at->setTimezone($context->provider->timezone);

        return sprintf(
            '%s con %s el %s a las %s (%s, $%d)',
            $appointment->service_name,
            $context->provider->public_name,
            $localStart->format('d/m/Y'),
            $localStart->format('H:i'),
            Format::duration($appointment->duration_minutes),
            $appointment->price,
        );
    }

    /**
     * Only ever returns a service that belongs to this provider and is active,
     * so a model naming somebody else's service cannot reach it.
     */
    private function ownService(int $serviceId, ToolContext $context): ?Service
    {
        return $context->provider->services()
            ->where('id', $serviceId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Server-side argument validation. Groq cannot enforce a strict schema at
     * the same time as tool use, so the schema sent to the model is a hint and
     * this is the actual gate.
     *
     * @param  array<mixed>  $arguments
     * @param  array<string, list<string>>  $rules
     * @return array<string, mixed> the validated values, or a single `error` key
     */
    private function validate(array $arguments, array $rules): array
    {
        $validator = Validator::make($arguments, $rules);

        if ($validator->fails()) {
            return ['error' => 'Argumentos inválidos: '.implode(' ', $validator->errors()->all())];
        }

        return $validator->validated();
    }

    /**
     * @param  array<string, array<string, string>>  $properties
     * @param  list<string>  $required
     * @return array<string, mixed>
     */
    private function definition(string $name, string $description, array $properties = [], array $required = []): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
                'parameters' => [
                    'type' => 'object',
                    // (object) so an empty parameter list encodes as {} rather
                    // than [], which the API rejects.
                    'properties' => $properties === [] ? (object) [] : $properties,
                    'required' => $required,
                    'additionalProperties' => false,
                ],
            ],
        ];
    }
}
