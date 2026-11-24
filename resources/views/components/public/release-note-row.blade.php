@props(['releaseNote', 'votes' => null, 'heading' => 'h3'])

<li class="flex flex-col gap-3 py-5 sm:flex-row sm:items-center sm:gap-6">
  <div class="min-w-0 flex-1">
    <{{ $heading }} class="text-base font-medium text-fp-hi">
      <a href="/release-notes/{{ $releaseNote->slug }}" class="rounded-sm hover:text-fp-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">{{ $releaseNote->title }}</a>
    </{{ $heading }}>
    <p class="mt-1 line-clamp-2 text-sm text-fp-mid">{{ Str::limit($releaseNote->content, 200) }}</p>
    <p class="mt-2 font-mono text-xs text-fp-low">
      <time datetime="{{ $releaseNote->created_at->toIso8601String() }}">{{ $releaseNote->created_at->diffForHumans() }}</time>
    </p>
  </div>
  <x-public.activity :comments="$releaseNote->comments" :votes="$votes" />
</li>
