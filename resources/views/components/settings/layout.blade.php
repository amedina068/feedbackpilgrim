@props(['active' => 'api-tokens'])

@php
    $sections = [
        [
            'key' => 'project',
            'title' => 'Project',
            'description' => 'Name, website & logo',
            'route' => 'settings.project',
            'icon' => 'M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z',
        ],
        [
            'key' => 'api-tokens',
            'title' => 'API Tokens',
            'description' => 'REST API & MCP access',
            'route' => 'settings.api-tokens',
            'icon' => 'M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z',
        ],
        [
            'key' => 'widget',
            'title' => 'Embed Widget',
            'description' => 'Publishable key & identity',
            'route' => 'settings.widget',
            'icon' => 'M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5',
        ],
        [
            'key' => 'testimonials',
            'title' => 'Testimonials',
            'description' => 'Collection & display',
            'route' => 'settings.testimonials',
            'icon' => 'M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z',
        ],
        [
            'key' => 'integrations',
            'title' => 'Integrations',
            'description' => 'Slack & connected apps',
            'route' => 'settings.integrations',
            'icon' => 'M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0',
        ],
        [
            'key' => 'email',
            'title' => 'Email',
            'description' => 'Send from your domain',
            'route' => 'settings.email',
            'icon' => 'M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75',
        ],
        [
            'key' => 'webhooks',
            'title' => 'Webhooks',
            'description' => 'Outbound event delivery',
            'route' => 'webhooks.index',
            'icon' => 'M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244',
        ],
    ];
@endphp

<x-app-layout>
    <div class="mx-auto max-w-6xl">
        <div class="border-b border-white/10 pb-5">
            <h1 class="text-2xl font-semibold tracking-tight text-fp-hi">{{ __('Settings') }}</h1>
            <p class="mt-1 text-base text-fp-mid sm:text-sm">{{ __('Manage the project, API access, the widget, testimonials, integrations and the domain your emails come from.') }}</p>
        </div>
    </div>

    <div class="mx-auto mt-8 max-w-6xl lg:flex lg:gap-x-10">
        {{-- Secondary navigation --}}
        <nav aria-label="Settings sections" class="lg:w-56 lg:shrink-0">
            {{-- Mobile: horizontal scrolling tabs --}}
            <ul role="list" class="-mx-4 flex gap-x-2 overflow-x-auto px-4 pb-1 sm:px-6 lg:hidden">
                @foreach ($sections as $section)
                    <li class="shrink-0">
                        <a href="{{ route($section['route']) }}"
                           @if ($active === $section['key']) aria-current="page" @endif
                           class="{{ $active === $section['key'] ? 'bg-fp-accent/10 text-fp-accent ring-1 ring-fp-accent/20' : 'text-fp-mid hover:bg-fp-surface hover:text-fp-hi' }} flex items-center gap-x-2 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="h-5 w-5 shrink-0" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $section['icon'] }}" />
                            </svg>
                            {{ $section['title'] }}
                        </a>
                    </li>
                @endforeach
            </ul>

            {{-- Desktop: vertical list with descriptions --}}
            <ul role="list" class="hidden gap-y-1 lg:sticky lg:top-8 lg:flex lg:flex-col">
                @foreach ($sections as $section)
                    <li>
                        <a href="{{ route($section['route']) }}"
                           @if ($active === $section['key']) aria-current="page" @endif
                           class="group flex items-start gap-x-3 rounded-xl px-3 py-2.5 {{ $active === $section['key'] ? 'bg-fp-accent/10 ring-1 ring-fp-accent/20' : 'hover:bg-fp-surface' }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                                 class="mt-0.5 h-5 w-5 shrink-0 {{ $active === $section['key'] ? 'text-fp-accent' : 'text-fp-low group-hover:text-fp-low' }}" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $section['icon'] }}" />
                            </svg>
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold {{ $active === $section['key'] ? 'text-fp-accent' : 'text-fp-hi' }}">{{ $section['title'] }}</span>
                                <span class="mt-0.5 block text-xs text-fp-low">{{ $section['description'] }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        {{-- Panel --}}
        <div class="mt-6 min-w-0 flex-1 lg:mt-0">
            {{ $slot }}
        </div>
    </div>
</x-app-layout>
