<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        {{-- Instalable como app: "Añadir a pantalla de inicio" usa esto para
             el ícono, el nombre y abrirse a pantalla completa. --}}
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#ffffff">
        <link rel="apple-touch-icon" href="/icons/icon-180.png">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="Glow Studio">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="antialiased">
        @inertia
    </body>
</html>
