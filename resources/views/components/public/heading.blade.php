<div class="flex flex-wrap items-center justify-between gap-4">
  <h1 class="min-w-0 text-2xl font-semibold tracking-tight text-balance text-fp-hi sm:text-3xl">{{ $slot }}</h1>
  @if (isset($actions) && $actions->isNotEmpty())
    <div class="flex shrink-0 items-center gap-x-3">{{ $actions }}</div>
  @endif
</div>
