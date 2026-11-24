<x-public-layout>
  <x-public.breadcrumbs :client="$client">
    <x-public.breadcrumbs.item href="/release-notes">Release Notes</x-public.breadcrumbs.item>
    <x-public.breadcrumbs.item>{{ $releaseNote->title }}</x-public.breadcrumbs.item>
  </x-public.breadcrumbs>

    <div class="mt-8 flex items-center gap-x-4">
      <x-vote-form :model="$releaseNote" :client="$client" />
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-fp-hi sm:text-3xl">{{ $releaseNote->title }}</h1>
        <p class="mt-1 font-mono text-xs text-fp-low">
          <time datetime="{{ $releaseNote->created_at->toIso8601String() }}">{{ $releaseNote->created_at->format('M j, Y') }}</time>
        </p>
      </div>
    </div>
    <div class="prose prose-invert mt-8 max-w-full">
        <x-markdown>{{ $releaseNote->content }}</x-markdown>
    </div>
    <div class="mt-12">
      <h2 class="text-lg font-medium text-fp-hi">Comments</h2>
      <x-public-comment-form
        :client="$client"
        :model="$releaseNote"
      />
      <ul role="list" class="mt-8 divide-y divide-white/5">
        @foreach($releaseNote->comments as $comment)
        <x-public.comment :comment="$comment" />
        @endforeach
      </ul>
    </div>
</x-public-layout>
