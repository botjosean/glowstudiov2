<?php

namespace App\Notifications;

use App\Support\Format;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the professional that a WhatsApp conversation needs a person.
 *
 * Mail because that is the channel this app already has wired and proven
 * (see NewAppointmentRequest). Which channel the owner actually wants for
 * hand-offs is still an open business decision, so treat this as the
 * changeable part — the requirement it satisfies is that a client asking for
 * a human always reaches one.
 *
 * Queued and written in Spanish for the same reasons as
 * NewAppointmentRequest: it runs in a worker with no request locale, and a
 * mail hiccup must not break the reply the client is waiting for.
 */
class HumanHandoffRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $clientPhone,
        private readonly string $clientName,
        private readonly string $reason,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Una clienta pidió hablar con una persona — {$this->clientName}")
            ->greeting('Hola,')
            ->line("{$this->clientName} está escribiendo por WhatsApp y el asistente no pudo resolver su caso.")
            ->line("Motivo: {$this->reason}")
            ->line('Teléfono: '.Format::usPhone(Format::digitsOnly($this->clientPhone)))
            ->line('Respóndele directamente por WhatsApp cuando puedas.');
    }
}
