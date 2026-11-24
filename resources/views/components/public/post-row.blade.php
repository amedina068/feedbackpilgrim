@props(['post', 'client'])

<li class="flex flex-col gap-3 py-5 sm:flex-row sm:items-center sm:gap-6">
  <div class="flex min-w-0 flex-1 items-center gap-x-4">
    <x-vote-form :model="$post" :client="$client" />
    <div class="min-w-0 flex-1">
      <h3 class="flex flex-wrap items-center gap-2 text-base font-medium text-fp-hi">
        <a href="/posts/{{ $post->id }}" class="rounded-sm hover:text-fp-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">{{ $post->title }}</a>
        <x-public.post-status :post="$post" />
      </h3>
      <p class="mt-1 font-mono text-xs text-fp-low">
        <time datetime="{{ $post->created_at->toIso8601String() }}">{{ $post->created_at->diffForHumans() }}</time> by {{ $post->contact->name }}
      </p>
    </div>
  </div>
  <x-public.activity :comments="$post->comments" class="max-sm:pl-16" />
</li>
