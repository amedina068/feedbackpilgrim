@props(['title'])

<details {{ $attributes->class('group') }}>
    <summary class="flex cursor-pointer list-none items-center justify-between gap-x-4 rounded-md text-sm/6 font-medium text-fp-hi focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent [&::-webkit-details-marker]:hidden">
        {{ $title }}
        <svg class="size-5 shrink-0 text-fp-low group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
        </svg>
    </summary>

    <div class="flex flex-col gap-y-3 pt-4">
        {{ $slot }}
    </div>
</details>
