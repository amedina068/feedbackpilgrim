<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-x-2 rounded-md bg-fp-danger px-3 py-2 text-sm font-medium text-white hover:bg-fp-danger/90 focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-danger disabled:opacity-50 transition-colors']) }}>
    {{ $slot }}
</button>
