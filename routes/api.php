<?php

use App\Http\Controllers\Api\KapsoWebhookController;
use App\Http\Middleware\VerifyKapsoWebhookSignature;
use Illuminate\Support\Facades\Route;

// Kapso's WhatsApp delivery endpoint.
//
// Deliberately not rate limited: a throttle here would turn a burst of real
// client messages into dropped deliveries, and Kapso auto-pauses a webhook
// after a run of failures — so the limiter would take the bot offline exactly
// when it is busiest. The HMAC signature, not a limiter, is what keeps this
// closed to everyone else.
Route::post('/kapso/webhook', [KapsoWebhookController::class, 'store'])
    ->middleware(VerifyKapsoWebhookSignature::class)
    ->name('api.kapso.webhook');
