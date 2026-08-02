<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Support\Format;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Queued (see QueuedVerifyEmail for why): a Resend/SMTP hiccup here must
 * not affect the booking response the client is waiting on.
 *
 * Written in Spanish rather than via __(): this is dispatched from a
 * queue worker in its own process, with no request-scoped locale cookie
 * to read — App::getLocale() there is just the app default, not the
 * provider's preference.
 */
class NewAppointmentRequest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Appointment $appointment) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $provider = $this->appointment->provider;
        $localStart = $this->appointment->starts_at->setTimezone($provider->timezone);

        return (new MailMessage)
            ->subject("Nueva solicitud de cita — {$this->appointment->client_name}")
            ->greeting("Hola {$provider->public_name},")
            ->line("{$this->appointment->client_name} solicitó una cita.")
            ->line("Servicio: {$this->appointment->service_name}")
            ->line('Fecha: '.$localStart->format('d/m/Y').' a las '.$localStart->format('H:i'))
            ->line('Teléfono: '.Format::usPhone($this->appointment->client_phone))
            ->action('Ver en el panel', url('/admin/citas'))
            ->line('Puedes confirmarla o rechazarla desde el panel de administración.');
    }
}
