<x-public-layout>
  <x-public.breadcrumbs :client="$client">
    <x-public.breadcrumbs.item>Roadmap</x-public.breadcrumbs.item>
  </x-public.breadcrumbs>

  <div class="mt-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-fp-hi sm:text-3xl">Roadmap</h1>
        <p class="mt-1 text-sm text-fp-mid">See what we're planning, building, and shipping.</p>
      </div>

      @if ($boards->count() > 1)
        <div class="flex flex-wrap gap-2">
          <a href="{{ url('/roadmap') }}" class="rounded-md px-3 py-1.5 text-sm font-medium {{ request('board') ? 'text-fp-mid ring-1 ring-inset ring-white/10 hover:bg-white/5' : 'bg-fp-elevated text-fp-hi' }}">All</a>
          @foreach ($boards as $board)
            <a href="{{ url('/roadmap?board='.$board->slug) }}" class="rounded-md px-3 py-1.5 text-sm font-medium {{ request('board') === $board->slug ? 'bg-fp-elevated text-fp-hi' : 'text-fp-mid ring-1 ring-inset ring-white/10 hover:bg-white/5' }}">{{ $board->name }}</a>
          @endforeach
        </div>
      @endif
    </div>

    @if ($stages->isEmpty())
      <p class="mt-8 rounded-lg border border-dashed border-white/10 p-6 text-sm text-fp-mid">Nothing on the roadmap yet.</p>
    @endif

    <div class="mt-8 grid grid-cols-1 gap-x-6 gap-y-8 md:grid-cols-2 lg:grid-cols-3">
      @foreach ($stages as $stage)
        <section>
          <header class="flex items-center gap-2 border-b border-white/5 pb-2.5">
            <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $stage->color }}"></span>
            <h2 class="text-sm font-medium text-fp-hi">{{ $stage->name }}</h2>
            <span class="ml-auto rounded-md bg-white/5 px-2 py-0.5 font-mono text-xs tabular-nums text-fp-mid">{{ $stage->posts->count() }}</span>
          </header>

          <ul role="list" class="mt-4 space-y-3">
            @forelse ($stage->posts as $post)
              <li class="rounded-lg bg-fp-surface p-4 ring-1 ring-white/5 transition-colors hover:ring-white/10">
                <a href="{{ url('/posts/'.$post->id) }}" class="text-sm font-medium text-fp-hi hover:text-fp-accent">{{ $post->title }}</a>
                <p class="mt-1 line-clamp-2 text-sm text-fp-mid">{{ $post->content_excerpt }}</p>
                <div class="mt-3 inline-flex items-center gap-1.5 text-xs font-medium text-fp-low">
                  <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M10 3a.75.75 0 0 1 .55.24l5.25 5.5a.75.75 0 1 1-1.1 1.02L10.75 6.3V16a.75.75 0 0 1-1.5 0V6.3L5.3 9.76a.75.75 0 0 1-1.1-1.02l5.25-5.5A.75.75 0 0 1 10 3Z" clip-rule="evenodd" />
                  </svg>
                  <span class="font-mono tabular-nums">{{ $post->votes_count }}</span> {{ Str::plural('vote', $post->votes_count) }}
                </div>
              </li>
            @empty
              <li class="rounded-lg border border-dashed border-white/10 p-4 text-sm text-fp-low">Nothing here yet.</li>
            @endforelse
          </ul>
        </section>
      @endforeach
    </div>
  </div>
</x-public-layout>
