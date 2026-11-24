@props([
    'title' => 'Documentation',
    'active' => 'guides',
])

@php
    $tab = fn (bool $on) => $on
        ? 'text-gray-900'
        : 'text-gray-500 hover:text-gray-900';
@endphp
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — Feedback Pilgrim</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/docs.js'])
    <style>[id]{scroll-margin-top:5.5rem}pre code.hljs{background:transparent;padding:0}</style>
</head>
<body class="bg-white text-gray-900 antialiased">
    <header class="sticky top-0 z-30 border-b border-gray-200 bg-white/85 backdrop-blur-sm">
        <div class="mx-auto flex max-w-(--breakpoint-2xl) items-center justify-between gap-4 px-4 py-3.5 sm:px-6">
            <div class="flex items-center gap-6">
                <a href="{{ route('documentation') }}" class="text-base font-bold tracking-tight">Feedback Pilgrim</a>
                <nav class="flex items-center gap-5 text-sm font-medium" aria-label="Documentation sections">
                    <a href="{{ route('documentation') }}" class="border-b-2 pb-px {{ $active === 'guides' ? 'border-orange-600 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-900' }}">Guides</a>
                    <a href="{{ route('documentation.reference') }}" class="border-b-2 pb-px {{ $active === 'reference' ? 'border-orange-600 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-900' }}">API reference</a>
                </nav>
            </div>
            <nav class="flex items-center gap-4 text-sm font-medium" aria-label="Account">
                {{ $actions ?? '' }}
                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-lg bg-gray-900 px-3 py-1.5 text-white hover:bg-gray-700">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="rounded-lg bg-gray-900 px-3 py-1.5 text-white hover:bg-gray-700">Sign in</a>
                @endauth
            </nav>
        </div>
    </header>

    {{ $slot }}
</body>
</html>
