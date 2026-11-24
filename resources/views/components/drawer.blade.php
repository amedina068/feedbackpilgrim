@props(['id', 'label', 'side' => 'left'])

{{--
    A side panel on the native <dialog> element. Open it with
    document.getElementById(id).showModal(). The browser keeps the focus in
    the panel, closes it on Escape and gives the focus back to the opener.
    A click on the backdrop also closes the panel.
--}}
<dialog
    id="{{ $id }}"
    aria-label="{{ $label }}"
    onclick="if (event.target === this) this.close()"
    {{ $attributes->class([
        'fixed inset-y-0 m-0 h-dvh max-h-none w-full max-w-xs bg-fp-surface p-0 text-fp-hi backdrop:bg-black/70',
        'transition-transform duration-150 ease-out motion-reduce:transition-none',
        'left-0 border-r border-white/5 starting:open:-translate-x-full' => $side === 'left',
        'left-auto right-0 border-l border-white/5 starting:open:translate-x-full' => $side === 'right',
    ]) }}
>
    <div class="flex h-full flex-col overflow-y-auto">
        <div class="flex items-center justify-between gap-x-4 py-3 pr-2 pl-4">
            <p class="min-w-0 truncate font-mono text-sm font-medium text-fp-hi">{{ $title ?? $label }}</p>
            <form method="dialog" class="shrink-0">
                <button type="submit" class="relative flex rounded-md p-2 text-fp-mid hover:text-fp-hi focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">
                    <span class="absolute top-1/2 left-1/2 size-[max(100%,3rem)] -translate-1/2 pointer-fine:hidden" aria-hidden="true"></span>
                    <span class="sr-only">Close {{ strtolower($label) }}</span>
                    <svg class="size-5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                    </svg>
                </button>
            </form>
        </div>

        <div class="flex flex-1 flex-col px-4 pb-6">
            {{ $slot }}
        </div>
    </div>
</dialog>
