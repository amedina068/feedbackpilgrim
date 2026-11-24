<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <meta name="color-scheme" content="dark">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600&family=jetbrains-mono:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-inter text-fp-hi antialiased font-features-['cv02','cv03','cv04','cv11']">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-fp-base">
            <a href="/" class="flex items-center gap-2">
                <span class="flex h-8 w-8 items-center justify-center rounded-md bg-fp-accent/15 font-mono text-base font-semibold text-fp-accent">fp</span>
                <span class="font-mono text-base font-medium tracking-tight text-fp-hi">feedbackpilgrim</span>
            </a>

            <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-fp-surface ring-1 ring-white/10 overflow-hidden sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
