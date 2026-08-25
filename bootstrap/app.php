<?php

use App\Console\Commands\AlertWaitingLeads;
use App\Console\Commands\CloseFinishedAppointments;
use App\Console\Commands\FollowUpWaitingLeads;
use App\Console\Commands\PruneWhatsAppClaims;
use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectToCanonicalHost;
use App\Http\Middleware\SetLocaleFromCookie;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(
            // Antes que nada: si llega por el dominio viejo, se redirige sin
            // gastar sesión ni consultas. Inerte hasta que APP_URL cambie.
            // AddSecurityHeaders va de PRIMERO por dos razones: genera el
            // nonce antes de que nada renderice, y así hasta un 301 sale con
            // sus cabeceras puestas.
            prepend: [AddSecurityHeaders::class, RedirectToCanonicalHost::class, SetLocaleFromCookie::class],
            append: [HandleInertiaRequests::class],
        );

        $middleware->redirectGuestsTo('/iniciar-sesion');
        $middleware->redirectUsersTo('/admin/citas');

        $middleware->trustProxies(at: '*');
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command(CloseFinishedAppointments::class)->hourly()->withoutOverlapping();
        $schedule->command(PruneWhatsAppClaims::class)->dailyAt('04:10')->withoutOverlapping();
        // Hourly, but each request is only ever alerted about once — see the
        // command. Nothing runs for a provider still in agent mode.
        $schedule->command(AlertWaitingLeads::class)->hourly()->withoutOverlapping();
        // Every minute so "diez minutos" means ten, not "up to fifteen". It
        // does nothing at all unless a conversation has been waiting that long.
        $schedule->command(FollowUpWaitingLeads::class)->everyMinute()->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Laravel's default here is a bare 429 with no page for Inertia to
        // render — the login form just sits there looking broken. Converting
        // it to the same 'identifier' field error SignIn.vue already
        // displays for wrong credentials makes the lockout visible instead.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if (! $request->routeIs('login.store')) {
                return null;
            }

            $seconds = max(1, (int) ($e->getHeaders()['Retry-After'] ?? 60));
            $minutes = (int) ceil($seconds / 60);

            $message = app()->getLocale() === 'en'
                ? ($minutes === 1 ? 'Too many attempts. Try again in 1 minute.' : "Too many attempts. Try again in {$minutes} minutes.")
                : ($minutes === 1 ? 'Demasiados intentos. Probá de nuevo en 1 minuto.' : "Demasiados intentos. Probá de nuevo en {$minutes} minutos.");

            throw ValidationException::withMessages(['identifier' => [$message]]);
        });
    })->create();
