@props(['title', 'description' => null, 'href' => null, 'action' => null])

<div {{ $attributes->class('flex flex-col items-start gap-y-3 rounded-lg border border-dashed border-white/10 px-5 py-6') }}>
    @isset($icon)
        <div class="text-fp-low">{{ $icon }}</div>
    @endisset

    <div class="flex flex-col gap-y-1">
        <h3 class="text-sm font-medium text-fp-hi">{{ $title }}</h3>
        @if ($description)
            <p class="text-sm text-fp-mid">{{ $description }}</p>
        @endif
    </div>

    @if ($href)
        <a href="{{ $href }}" class="rounded-md px-3 py-1.5 text-sm font-medium text-fp-hi ring-1 ring-white/10 ring-inset hover:bg-white/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">{{ $action }}</a>
    @endif
</div>
