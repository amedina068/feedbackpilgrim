<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center gap-x-2 rounded-md border border-white/10 bg-fp-surface px-3 py-2 text-sm font-medium text-fp-hi hover:bg-fp-elevated hover:border-white/20 focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent disabled:opacity-50 transition-colors']) }}>
    {{ $slot }}
</button>
