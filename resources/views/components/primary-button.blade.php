<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-x-2 rounded-md bg-fp-accent px-3 py-2 text-sm font-medium text-white hover:bg-fp-accent-hover focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent disabled:opacity-50 transition-colors']) }}>
    {{ $slot }}
</button>
