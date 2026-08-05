<?php

use App\Console\Commands\CloseFinishedAppointments;
use App\Console\Commands\PruneWhatsAppClaims;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocaleFromCookie;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(
            prepend: [SetLocaleFromCookie::class],
            append: [HandleInertiaRequests::class],
        );

        $middleware->redirectGuestsTo('/iniciar-sesion');
        $middleware->redirectUsersTo('/admin/citas');

        $middleware->trustProxies(at: '*');
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command(CloseFinishedAppointments::class)->hourly()->withoutOverlapping();
        $schedule->command(PruneWhatsAppClaims::class)->dailyAt('04:10')->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
