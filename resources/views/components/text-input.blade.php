@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'block w-full rounded-md border-0 bg-fp-surface py-1.5 text-fp-hi ring-1 ring-inset ring-white/10 placeholder:text-fp-low focus:ring-2 focus:ring-inset focus:ring-fp-accent sm:text-sm']) !!}>
