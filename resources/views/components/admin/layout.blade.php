@props(['active' => 'users'])

@php
    $tabs = [
        ['key' => 'users', 'title' => 'Users', 'href' => '/admin/users'],
        ['key' => 'settings', 'title' => 'Settings', 'href' => '/admin/settings'],
    ];
@endphp

<x-app-layout>
    <div class="mx-auto max-w-6xl">
        <div class="border-b border-white/10 pb-5">
            <p class="font-mono text-xs uppercase tracking-wide text-fp-low">Instance</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-fp-hi">Admin</h1>
            <p class="mt-1 text-sm text-fp-mid">Only the owner sees this area. The changes apply to every project on this instance.</p>
        </div>

        <nav aria-label="Admin sections" class="mt-6">
            <ul role="list" class="flex gap-x-2">
                @foreach ($tabs as $tab)
                    <li>
                        <a href="{{ $tab['href'] }}"
                           @if ($active === $tab['key']) aria-current="page" @endif
                           class="{{ $active === $tab['key'] ? 'bg-fp-accent/10 text-fp-accent ring-1 ring-fp-accent/20' : 'text-fp-mid hover:bg-fp-surface hover:text-fp-hi' }} flex rounded-lg px-3 py-2 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">
                            {{ $tab['title'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        @if (session('status'))
            <p role="status" class="mt-6 rounded-md bg-fp-surface px-4 py-3 text-sm text-fp-hi ring-1 ring-white/5">
                {{ session('status') }}
            </p>
        @endif

        <div class="mt-6">
            {{ $slot }}
        </div>
    </div>
</x-app-layout>
