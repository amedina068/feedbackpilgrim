@props(['comment'])

<li>
  <article class="flex gap-x-3 py-5">
    <img class="size-8 shrink-0 rounded-full bg-fp-surface outline-1 -outline-offset-1 outline-white/10" src="{{ $comment->contact->avatar }}" alt="{{ $comment->contact->name ?? 'N/A' }}">
    <div class="min-w-0 flex-1">
      <header class="flex flex-wrap items-baseline gap-x-2">
        <p class="text-sm font-medium text-fp-hi">{{ $comment->contact->name ?? 'N/A' }}</p>
        <p class="font-mono text-xs text-fp-low">
          <time datetime="{{ $comment->created_at->toIso8601String() }}">{{ $comment->created_at->diffForHumans() }}</time>
        </p>
      </header>
      <div class="prose prose-invert prose-sm mt-1 max-w-none text-sm text-fp-mid">
        <x-markdown>{{ $comment->content }}</x-markdown>
      </div>
      @if ($comment->getMedia('photos')->isNotEmpty())
        <ul role="list" class="mt-3 flex flex-wrap gap-2">
          @foreach ($comment->getMedia('photos') as $photo)
            <li>
              <a href="{{ $photo->getUrl() }}" target="_blank" rel="noopener" class="flex rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">
                <img src="{{ $photo->getUrl() }}" alt="{{ $photo->name }}" class="size-20 shrink-0 rounded-lg object-cover outline-1 -outline-offset-1 outline-white/10">
              </a>
            </li>
          @endforeach
        </ul>
      @endif
    </div>
  </article>
</li>
