@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-fp-mid']) }}>
    {{ $value ?? $slot }}
</label>
