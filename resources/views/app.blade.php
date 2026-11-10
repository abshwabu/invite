<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Teri') }}</title>

        <!-- Favicon & Brand Icons -->
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="icon" type="image/x-icon" href="/favicon.ico">
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#1C2140">

        <!-- Fonts -->
        <link rel="preload" href="/fonts/young-serif-normal-400.ttf" as="font" type="font/ttf" crossorigin>
        <link rel="preload" href="/fonts/instrument-sans-normal-400.ttf" as="font" type="font/ttf" crossorigin>
        <link rel="preload" href="/fonts/noto-serif-ethiopic-normal-400.ttf" as="font" type="font/ttf" crossorigin>
        <link rel="stylesheet" href="/fonts/fonts.css">

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
