<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The stock notification sends inline, so a Resend/SMTP hiccup — at
 * registration or from the "resend" button — turns into a 500 on an
 * otherwise-successful request. Queuing (via User::sendEmailVerificationNotification)
 * decouples "action succeeded" from "email delivered".
 */
class QueuedVerifyEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;
}
