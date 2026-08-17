<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un solo dominio para todo, sin romper los enlaces ya enviados.
 *
 * La app vivía en `citas.glowstudios.vip` mientras el dominio de verdad servía
 * un `index.html` suelto cuya única función era enlazar de vuelta aquí. Dos
 * sitios, y el segundo hacía peor lo que el primero ya hacía solo. Al unificar,
 * cada enlace que una clienta tiene guardado en su WhatsApp apunta al
 * subdominio viejo — y esos enlaces no se pueden reeditar.
 *
 * Esto los mantiene vivos: un 301 al mismo camino en el dominio canónico.
 *
 * **El interruptor es APP_URL.** Mientras APP_URL siga siendo el subdominio,
 * este middleware no hace absolutamente nada; en cuanto apunte al dominio
 * nuevo, empieza a redirigir. Así el cambio entero es una variable de entorno
 * y no hay una ventana en la que el DNS y el código discrepen.
 */
class RedirectToCanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $canonical = parse_url((string) config('app.url'), PHP_URL_HOST);

        // Sólo GET y HEAD. Un 301 sobre un POST le pide al navegador que repita
        // la petición contra otra URL, y el webhook de Kapso —que llega por
        // POST a este mismo host— dejaría de entregar mensajes.
        if (! is_string($canonical)
            || $request->getHost() === $canonical
            || ! $request->isMethodSafe()
            || $request->is('api/*', 'up')) {
            return $next($request);
        }

        return redirect()->to(
            $request->getSchemeAndHttpHost() === null
                ? '/'
                : rtrim((string) config('app.url'), '/').$request->getRequestUri(),
            301,
        );
    }
}
