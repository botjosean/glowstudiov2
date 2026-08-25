<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        {{-- Instalable como app. Dos manifiestos, no uno: la profesional
             quiere abrir en su agenda y la clienta en la página de reservas, y
             una app que abre a una clienta en el login del panel no le sirve
             de nada. Se distinguen por su `id`, así que Android las trata como
             dos apps distintas y cada quien instala la suya. --}}
        @php
            // Iniciar sesión y crear cuenta son puertas de la PROFESIONAL, no
            // de la clienta: quien se registra ahí viene a montar su negocio,
            // así que se le ofrece la app que abre en su agenda.
            $esPanel = request()->is('admin/*', 'iniciar-sesion', 'crear-cuenta', 'admin-general*');
        @endphp
        <link rel="manifest" href="{{ $esPanel ? '/manifest-pro.webmanifest' : '/manifest.webmanifest' }}">
        <meta name="theme-color" content="#0E1626">
        {{-- La corona en la pestaña. Va en SVG y no en el .ico porque el SVG se
             dibuja nítido en cualquier tamaño y, sobre todo, porque es un
             archivo de texto: se cambia el logo editándolo, sin exportar nada.

             Ojo con los dos que siguen mostrando el loto, porque son imágenes
             ya dibujadas y no se generan desde este SVG:

             - `public/favicon.ico` está VACÍO (0 bytes) desde que se creó. Hoy
               no se nota, porque cualquier navegador que entienda el SVG de
               arriba lo prefiere; sólo uno viejo se quedaría sin ícono. No se
               enlaza a propósito: enlazar un archivo vacío sería peor que
               dejar que lo pida solo y falle.
             - El apple-touch-icon y los íconos del manifiesto
               (`icon-{180,192,512}.png` y los `icon-maskable-*`) siguen con el
               loto, así que en la pantalla de inicio del teléfono la app
               todavía se ve con la flor. Hay que volver a exportarlos con la
               corona; ver `resources/js/crown.js`. --}}
        <link rel="icon" type="image/svg+xml" href="/icons/icon.svg?v=20260823">
        <link rel="apple-touch-icon" href="/icons/icon-180.png?v=20260817">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="Glow Studio">
        {{-- Chrome no ofrece "instalar app" sin un service worker, por eso el
             aviso dejó de salir y el ícono guardado era solo un acceso directo
             del navegador. Registrado después de load para no competir con el
             primer render, y sin romper nada si el navegador no lo soporta. --}}
        {{-- El nonce es lo que le deja a ESTE script pasar la CSP sin tener
             que abrirle la puerta a todo script inline. Se comprueba `bound`
             porque una vista puede renderizarse fuera del ciclo web (una
             prueba, un comando) y ahí el middleware no ha corrido. --}}
        <script @if (app()->bound('csp-nonce')) nonce="{{ app('csp-nonce') }}" @endif>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function () {
                    navigator.serviceWorker.register('/sw.js').catch(function () {});
                });
            }
        </script>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="antialiased">
        @inertia
    </body>
</html>
