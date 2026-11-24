<!DOCTYPE html>
<html class="h-full bg-fp-base scheme-only-dark has-[dialog[open]]:overflow-hidden" style="color-scheme: dark;" lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="dark">

    <title>{{ $client->name }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&family=jetbrains-mono:400,500,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-inter antialiased h-full bg-fp-base text-fp-hi font-features-['cv02','cv03','cv04','cv11']">
    <div id="app" class="isolate">
        <aside class="fixed inset-y-0 left-0 flex w-64 flex-col gap-y-6 overflow-y-auto border-r border-white/5 bg-fp-surface px-4 py-5 max-lg:hidden">
            <a href="/" class="flex h-10 items-center px-2 font-mono text-lg font-medium tracking-tight text-fp-hi">
                @if ($client->logo)
                    <img src="{{ $client->logo }}" alt="{{ $client->name }}" class="h-10 max-w-full shrink-0 object-contain">
                @else
                    <span class="truncate">{{ $client->name }}</span>
                @endif
            </a>

            <x-public.nav />
        </aside>

        <header class="sticky top-0 z-10 flex items-center justify-between gap-x-4 border-b border-white/5 bg-fp-base/95 py-2 pr-2 pl-4 backdrop-blur sm:pl-6 lg:hidden">
            <a href="/" class="min-w-0 truncate font-mono text-sm/6 font-medium text-fp-hi">{{ $client->name }}</a>

            <button
                type="button"
                onclick="document.getElementById('public-menu').showModal()"
                aria-controls="public-menu"
                aria-haspopup="dialog"
                class="relative flex shrink-0 items-center gap-x-2 rounded-md py-2 pr-2 pl-3 text-sm font-medium text-fp-mid hover:bg-white/5 hover:text-fp-hi focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent"
            >
                <span class="absolute top-1/2 left-1/2 size-[max(100%,3rem)] -translate-1/2 pointer-fine:hidden" aria-hidden="true"></span>
                Menu
                <svg class="size-5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M2 4.75A.75.75 0 0 1 2.75 4h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 4.75ZM2 10a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 10Zm0 5.25a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd" />
                </svg>
            </button>
        </header>

        <x-drawer id="public-menu" label="Menu" side="right">
            <x-slot:title>{{ $client->name }}</x-slot:title>
            <x-public.nav class="pt-2" />
        </x-drawer>

        <main class="lg:pl-64">
            <div class="mx-auto max-w-5xl px-4 pb-12 sm:px-6 lg:px-8">
                <x-flash class="mt-6" />
                {{ $slot }}
            </div>
        </main>
    </div>
</body>
</html>
