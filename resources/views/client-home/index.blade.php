<x-public-layout>
  <x-public.breadcrumbs :client="$client"></x-public.breadcrumbs>

  <div class="mt-8">
    <x-public.heading>Home</x-public.heading>

    @if($releaseNotes->count())
      <div class="mt-12 flex items-center justify-between">
        <h2 class="text-sm font-medium text-fp-mid">Latest release notes</h2>
        <a href="/release-notes" class="text-sm font-medium text-fp-accent hover:text-fp-accent-hover">View all</a>
      </div>

      <ul role="list" class="mt-2 divide-y divide-white/5">
        @foreach($releaseNotes as $releaseNote)
          <x-public.release-note-row :release-note="$releaseNote" />
        @endforeach
      </ul>
    @endif

    <div class="mt-12 flex items-center justify-between">
      <h2 class="text-sm font-medium text-fp-mid">Latest posts</h2>
      @if(auth()->guard('contact')->check() && auth()->guard('contact')->user()->client_id == $client->id)
        <a href="/posts/create" class="inline-flex items-center rounded-md bg-fp-accent px-3 py-2 text-sm font-medium text-white hover:bg-fp-accent-hover focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">New post</a>
      @endif
    </div>

    <ul role="list" class="mt-2 divide-y divide-white/5">
      @forelse($posts as $post)
        <x-public.post-row :post="$post" :client="$client" />
      @empty
        <li class="py-8 text-sm text-fp-mid">No posts yet. Ask for the feature that you want.</li>
      @endforelse
    </ul>
    @if($posts->isNotEmpty())
      <a href="/posts" class="mt-4 inline-block text-sm font-medium text-fp-accent hover:text-fp-accent-hover">View all posts</a>
    @endif
  </div>
</x-public-layout>
