@php
    $links = [
        'release-notes' => 'Release Notes',
        'posts' => 'Posts',
        'roadmap' => 'Roadmap',
        'testimonials' => 'Testimonials',
    ];
@endphp

<nav aria-label="Main" {{ $attributes->class('flex flex-1 flex-col gap-y-6') }}>
    <ul role="list" class="flex flex-col gap-y-0.5">
        @foreach ($links as $path => $title)
            <li>
                <x-sidebar-link href="/{{ $path }}" :active="request()->is($path, $path.'/*')">{{ $title }}</x-sidebar-link>
            </li>
        @endforeach
    </ul>

    @if ($clientDomain)
        <a href="http://{{ $clientDomain }}" class="mt-auto flex items-center justify-center gap-x-2 rounded-md px-3 py-1.5 text-sm/6 font-medium text-fp-hi ring-1 ring-white/10 ring-inset hover:bg-white/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">
            <span aria-hidden="true">&larr;</span> Back to {{ $client->name }}
        </a>
    @endif
</nav>
