<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las cabeceras que el navegador necesita para defender a la profesional.
 *
 * Hasta el 2026-08-25 el servidor no mandaba ninguna: ni HSTS, ni CSP, ni
 * `X-Frame-Options`, ni `X-Content-Type-Options`, ni `Referrer-Policy`.
 * Comprobado pidiéndoselas a producción, no leyendo el código.
 *
 * La que más pesa es la de los marcos. Sin ella, cualquiera puede meter el
 * panel de una profesional dentro de un `<iframe>` invisible en su propia
 * página y ponerle botones encima: ella cree que toca una cosa y toca otra
 * —cancelar una cita, borrar un servicio—. Se llama clickjacking y se cierra
 * con una línea. Verificado antes de cerrarla: la app **no usa iframes en
 * ningún sitio**, así que `DENY` no rompe nada.
 *
 * **Por qué la CSP va en dos partes.** Una CSP mal puesta rompe la app en
 * silencio: deja de cargar una foto, o el service worker, y nadie se entera
 * hasta que una clienta ve el perfil vacío. Así que:
 *
 * 1. Lo que **no puede romper nada** va aplicado de verdad: `frame-ancestors`,
 *    que sólo decide quién puede enmarcar la página.
 * 2. La política completa va en `Report-Only`. El navegador la revisa y avisa
 *    en su consola de lo que bloquearía, pero **no bloquea**. Cuando esté
 *    comprobada contra producción unos días, se pasa a aplicada moviendo una
 *    sola línea de aquí abajo.
 *
 * El `nonce` es para que esa política pueda llegar a ser estricta de verdad.
 * `app.blade.php` tiene un `<script>` suelto —el que registra el service
 * worker, sin el cual Chrome no ofrece instalar la app— y con `nonce` se le
 * puede permitir a ÉL sin abrir la puerta a todo script inline, que es lo que
 * haría `'unsafe-inline'` y lo que dejaría la CSP sin sentido.
 */
class AddSecurityHeaders
{
    /**
     * Un año. Es el mínimo que piden las listas de precarga, y es seguro
     * porque el sitio ya vive detrás de Cloudflare con HTTPS.
     *
     * **Sin `preload` a propósito.** Entrar en la lista de precarga de los
     * navegadores es una puerta de una sola dirección: salir tarda meses y
     * mientras tanto el dominio es inalcanzable por HTTP. Eso se decide con
     * el dueño, no en un middleware.
     */
    private const HSTS = 'max-age=31536000; includeSubDomains';

    public function handle(Request $request, Closure $next): Response
    {
        // El nonce se genera antes de renderizar, porque la vista lo necesita.
        $nonce = Str::random(24);
        app()->instance('csp-nonce', $nonce);

        $response = $next($request);

        // Nada de esto tiene sentido en una descarga de archivo o en una foto.
        if (! $this->esPagina($response)) {
            return $response;
        }

        $h = $response->headers;

        // El navegador no adivina el tipo de un archivo: si el servidor dice
        // que es texto, es texto. Sin esto, una subida maliciosa que el
        // servidor sirve como texto puede acabar ejecutándose como script.
        $h->set('X-Content-Type-Options', 'nosniff');

        // Clickjacking. `DENY`, no `SAMEORIGIN`: la app no se enmarca a sí
        // misma en ningún sitio, así que no hay nada que conservar.
        $h->set('X-Frame-Options', 'DENY');

        // Al salir hacia otro sitio se manda el dominio, nunca la ruta. Que el
        // Instagram de una profesional sepa que la visita viene de Glow
        // Studios está bien; que sepa de QUÉ clienta venía, no.
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Nada de esto se usa hoy. Se apaga antes de que a alguien se le
        // ocurra usarlo, y sobre todo antes de que se lo pida una etiqueta de
        // terceros que entre mañana.
        $h->set('Permissions-Policy', 'geolocation=(), microphone=(), payment=(), usb=(), camera=(self)');

        // Sólo sobre HTTPS: mandar HSTS por HTTP no hace nada y en desarrollo
        // le fija al navegador un dominio local en HTTPS para siempre, que es
        // un dolor de cabeza que no le voy a dejar a nadie.
        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', self::HSTS);
        }

        // Aplicada. Sólo esto — es lo único que no puede romper nada.
        $h->set('Content-Security-Policy', "frame-ancestors 'none'");

        // Vigilando, sin bloquear. Cuando esté comprobada, esta línea cambia
        // de nombre y pasa a ser la de arriba.
        $h->set('Content-Security-Policy-Report-Only', $this->politica($nonce));

        return $response;
    }

    /**
     * La política completa. Cuando pase a aplicada, lo que rompa se verá aquí.
     */
    private function politica(string $nonce): string
    {
        // Las fotos de las profesionales no viven en este servidor sino en un
        // dominio propio de R2, y ese dominio es una variable de entorno. Sale
        // de la configuración a propósito: escrito a mano aquí, el día que
        // cambie el bucket las fotos desaparecen y nadie sabrá por qué.
        $fotos = (string) config('filesystems.disks.r2.url', '');
        $origenFotos = $fotos !== '' ? ' '.rtrim($fotos, '/') : '';

        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "img-src 'self' data: blob:".$origenFotos,
            "font-src 'self' data:",
            // Los atributos `style="..."` sueltos de las plantillas —el
            // degradado de Instagram, por ejemplo— necesitan esto. Es mucho
            // menos grave que abrirlo en los scripts: un estilo no ejecuta.
            "style-src 'self' 'unsafe-inline'",
            "script-src 'self' 'nonce-{$nonce}'",
            "connect-src 'self'",
            "worker-src 'self'",
            "manifest-src 'self'",
        ]);
    }

    /**
     * Sólo páginas. Un PNG o un CSV no se enmarcan ni ejecutan scripts, y
     * llenarlos de cabeceras es peso por nada en cada petición.
     */
    private function esPagina(Response $response): bool
    {
        $tipo = (string) $response->headers->get('Content-Type', '');

        return $tipo === ''
            || str_contains($tipo, 'text/html')
            || str_contains($tipo, 'application/json');
    }
}
