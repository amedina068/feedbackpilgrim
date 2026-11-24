@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-fp-success']) }}>
        {{ $status }}
    </div>
@endif
