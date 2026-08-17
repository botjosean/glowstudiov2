<?php

namespace App\Notifications;

use App\Models\Lead;
use App\Support\Format;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Tells the professional that people are waiting on WhatsApp.
 *
 * This is the counterweight to receptionist mode. The assistant used to book
 * a client by itself at two in the morning; now it takes her details and stops,
 * which means a professional who does not read her WhatsApp loses a client and
 * nobody finds out. Trading autonomy for control only works if forgetting is
 * visible.
 *
 * Mail, queued and in Spanish for the same reasons as HumanHandoffRequested:
 * it is the channel this app has proven, and it runs in a worker with no
 * request locale.
 */
class LeadsWaiting extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, Lead>  $leads
     */
    public function __construct(private readonly Collection $leads, private readonly int $hours) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = $this->leads->count();

        $message = (new MailMessage)
            ->subject($count === 1
                ? 'Una clienta lleva esperando respuesta en WhatsApp'
                : "{$count} clientas llevan esperando respuesta en WhatsApp")
            ->greeting('Hola,')
            ->line($count === 1
                ? "Una persona escribió por WhatsApp hace más de {$this->hours} horas y todavía nadie le respondió."
                : "{$count} personas escribieron por WhatsApp hace más de {$this->hours} horas y todavía nadie les respondió.");

        foreach ($this->leads as $lead) {
            $who = $lead->name ?? Format::usPhone($lead->phone);
            $what = $lead->message === null ? '' : ' — "'.mb_substr($lead->message, 0, 120).'"';

            $message->line("• {$who} (".Format::usPhone($lead->phone).')'.$what);
        }

        return $message
            ->action('Ver las solicitudes', route('admin.citas'))
            ->line('Cuando la agendes, la solicitud se cierra sola.');
    }
}
