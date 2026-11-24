<aside class="fixed inset-y-0 left-0 flex w-72 flex-col gap-y-5 overflow-y-auto border-r border-white/5 bg-fp-base px-4 py-5 max-lg:hidden">
  <a href="/dashboard" class="flex items-center gap-x-2 px-2 font-mono text-sm font-medium tracking-tight text-fp-hi">
    <span class="flex size-7 shrink-0 items-center justify-center rounded-md bg-fp-accent/15 text-fp-accent">fp</span>
    feedbackpilgrim
  </a>

  @include('layouts.navigation-links')
</aside>

<header class="sticky top-0 z-10 flex items-center justify-between gap-x-4 border-b border-white/5 bg-fp-base/95 py-2 pr-2 pl-4 backdrop-blur sm:pl-6 lg:hidden">
  <p class="min-w-0 truncate text-sm/6 font-medium text-fp-hi">{{ $currentSection }}</p>

  <button
    type="button"
    onclick="document.getElementById('admin-menu').showModal()"
    aria-controls="admin-menu"
    aria-haspopup="dialog"
    class="relative flex shrink-0 items-center gap-x-2 rounded-md py-2 pr-2 pl-3 text-sm font-medium text-fp-mid hover:bg-white/5 hover:text-fp-hi focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent"
  >
    <span class="absolute top-1/2 left-1/2 size-[max(100%,3rem)] -translate-1/2 pointer-fine:hidden" aria-hidden="true"></span>
    Menu
    <svg class="size-5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
      <path fill-rule="evenodd" d="M2 4.75A.75.75 0 0 1 2.75 4h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 4.75ZM2 10a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 10Zm0 5.25a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd" />
    </svg>
  </button>
</header>

<x-drawer id="admin-menu" label="Menu">
  <x-slot:title>feedbackpilgrim</x-slot:title>
  @include('layouts.navigation-links')
</x-drawer>
