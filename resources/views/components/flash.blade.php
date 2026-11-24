@if (session('success'))
    <div role="status" {{ $attributes->merge(['class' => 'rounded-lg bg-fp-success/10 px-4 py-3 text-sm text-fp-success ring-1 ring-fp-success/20']) }}>{{ session('success') }}</div>
@elseif (session('info'))
    <div role="status" {{ $attributes->merge(['class' => 'rounded-lg bg-fp-accent/10 px-4 py-3 text-sm text-fp-hi ring-1 ring-fp-accent/20']) }}>{{ session('info') }}</div>
@endif
