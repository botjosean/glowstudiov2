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
        <meta name="theme-color" content="#ffffff">
        <link rel="apple-touch-icon" href="/icons/icon-180.png">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="Glow Studio">
        {{-- Chrome no ofrece "instalar app" sin un service worker, por eso el
             aviso dejó de salir y el ícono guardado era solo un acceso directo
             del navegador. Registrado después de load para no competir con el
             primer render, y sin romper nada si el navegador no lo soporta. --}}
        <script>
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
