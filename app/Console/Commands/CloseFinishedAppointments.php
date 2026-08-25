<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Review;
use App\Support\Kapso\KapsoClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;

#[Signature('appointments:close-finished')]
#[Description('Mark confirmed appointments whose end time has passed as closed.')]
class CloseFinishedAppointments extends Command
{
    /**
     * Execute the console command.
     *
     * Only Confirmed -> Closed (exactly AppointmentStatus::canTransitionTo()'s
     * one command-driven transition). Pending appointments are left alone —
     * a provider who never acted on one stays responsible for it.
     *
     * ends_at, not starts_at: an in-progress appointment must not close.
     *
     * starts_at/ends_at are absolute UTC instants (see AppointmentSeeder),
     * so "this appointment has ended" is the same fact for every observer
     * regardless of the owning provider's timezone — a plain UTC comparison
     * is correct, not just simpler. now()->utc() rather than bare now() so
     * this stays correct even if config('app.timezone') ever changes.
     */
    public function handle(KapsoClient $kapso): int
    {
        // Se traen antes de cerrarlas porque despues hace falta cada una para
        // pedirle la resena a su clienta. Un update() masivo devolvia solo el
        // numero y no daba a quien escribirle.
        $terminadas = Appointment::query()
            ->with('provider')
            ->where('status', AppointmentStatus::Confirmed->value)
            ->where('ends_at', '<=', now()->utc())
            ->get();

        foreach ($terminadas as $cita) {
            $cita->update(['status' => AppointmentStatus::Closed->value]);
        }

        $this->info('Closed '.$terminadas->count().' appointment(s).');
        $this->info('Asked for '.$this->pedirResenas($terminadas, $kapso).' review(s).');

        return self::SUCCESS;
    }

    /**
     * Pedirle la resena a cada clienta cuya cita acaba de cerrarse.
     *
     * Este es el momento exacto: el servicio ya ocurrio, esta fresco, y la
     * clienta todavia se acuerda de como quedo. Pedirla antes seria pedirle
     * que opine de algo que no ha pasado; pedirla tres dias despues es cuando
     * ya nadie contesta.
     *
     * Nada de esto puede tumbar el cierre de citas, que es la razon de ser del
     * comando y corre cada hora: una invitacion que falla se anota y se sigue.
     * La invitacion se crea igual aunque el mensaje no salga -- el enlace
     * queda vivo y la profesional puede pasarlo a mano.
     *
     * @param  \Illuminate\Support\Collection<int, Appointment>  $citas
     */
    private function pedirResenas($citas, KapsoClient $kapso): int
    {
        $pedidas = 0;

        foreach ($citas as $cita) {
            try {
                // firstOrCreate y no create: si el comando se solapa consigo
                // mismo, o si una cita ya cerrada se vuelve a procesar, la
                // clienta no puede recibir dos veces el mismo enlace.
                $review = Review::firstOrCreate(
                    ['appointment_id' => $cita->id],
                    [
                        'provider_id' => $cita->provider_id,
                        'client_name' => $cita->client_name,
                        'rating' => 0,
                        'token' => Review::newToken(),
                    ],
                );

                if (! $review->wasRecentlyCreated) {
                    continue;
                }

                $provider = $cita->provider;

                if ($provider->whatsapp_phone_number_id === null) {
                    continue;
                }

                $kapso->sendText(
                    phoneNumberId: $provider->whatsapp_phone_number_id,
                    to: '1'.$cita->client_phone,
                    body: __('admin.waMessageReviewAsk', [
                        'client' => $cita->client_name,
                        'provider' => $provider->public_name,
                        'service' => $cita->service_name,
                        'link' => url('/resena/'.$review->token),
                    ]),
                );

                $pedidas++;
            } catch (RuntimeException $e) {
                Log::warning('No se pudo pedir la resena de una cita cerrada.', [
                    'appointment_id' => $cita->id,
                    'reason' => $e->getMessage(),
                ]);
            }
        }

        return $pedidas;
    }
}
