<li class="flex min-w-0 items-center gap-x-2">
  <span class="shrink-0 font-mono text-fp-low" aria-hidden="true">/</span>
  @if ($href)
    <a href="{{ $href }}" class="truncate rounded-sm text-fp-mid hover:text-fp-hi focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">{{ $slot }}</a>
  @else
    <span class="truncate text-fp-hi">{{ $slot }}</span>
  @endif
</li>
