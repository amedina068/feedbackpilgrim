@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-1 pt-1 border-b-2 border-fp-accent text-sm font-medium leading-5 text-fp-hi focus:outline-hidden transition duration-150 ease-in-out'
            : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-fp-mid hover:text-fp-hi hover:border-white/20 focus:outline-hidden focus:text-fp-hi transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
