<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The stock notification is inline (a Resend hiccup would 500 the "send me
 * a link" request) AND entirely in English (there's no lang/es override in
 * this app — see the isMobile/lang gap in CLAUDE.md). QueuedVerifyEmail
 * already solved the first problem for email verification; this mirrors it
 * for password reset and solves the second by writing the email itself
 * instead of relying on Laravel's default copy.
 */
class QueuedResetPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public function toMail($notifiable): MailMessage
    {
        $expireMinutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Restablecé tu contraseña — Glow Studio')
            ->greeting('¡Hola!')
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta.')
            ->action('Crear contraseña nueva', $this->resetUrl($notifiable))
            ->line("Este enlace vence en {$expireMinutes} minutos.")
            ->line('Si no fuiste vos quien lo pidió, no hace falta que hagas nada — tu contraseña actual sigue funcionando.');
    }
}
