@props(['href', 'active' => false])

<a
    href="{{ $href }}"
    @if ($active) aria-current="page" @endif
    {{ $attributes->class([
        'flex items-center gap-x-3 rounded-md px-2 py-1.5 text-sm/6 font-medium focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-fp-accent',
        'bg-fp-accent/10 text-fp-accent' => $active,
        'text-fp-mid hover:bg-white/5 hover:text-fp-hi' => ! $active,
    ]) }}
>{{ $slot }}</a>
