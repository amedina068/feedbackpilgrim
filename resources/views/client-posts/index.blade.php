<x-public-layout>
  <x-public.breadcrumbs :client="$client">
    <x-public.breadcrumbs.item>Posts</x-public.breadcrumbs.item>
  </x-public.breadcrumbs>

  <div class="mt-8">
  <x-public.heading>
    Posts
    <x-slot:actions>
      <base-dropdown>
        <template v-slot:handler>
          <div class="inline-flex items-center gap-x-1 rounded-md px-3 py-2 text-sm font-medium text-fp-mid ring-1 ring-inset ring-white/10 hover:bg-white/5 hover:text-fp-hi">
            <div>{{ $showingPostsFilter }}</div>
            <svg class="h-4 w-4 shrink-0 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" aria-hidden="true">
              <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
            </svg>
        </div>
        </template>
        <template v-slot:default>
          <menu-item v-slot="{ active }">
              <a
                href="/posts?sort_by=created_at__DESC{{ request()->filled('completed') ? '&completed=on' : ''}}"
                :class="[active ? 'bg-white/5 text-fp-hi' : 'text-fp-mid', 'block px-4 py-2 text-sm']"
              >Newest</a>
          </menu-item>
          <menu-item v-slot="{ active }">
            <a
              href="/posts?sort_by=created_at__ASC{{ request()->filled('completed') ? '&completed=on' : ''}}"
              :class="[active ? 'bg-white/5 text-fp-hi' : 'text-fp-mid', 'block px-4 py-2 text-sm']"
            >Oldest</a>
          </menu-item>
          <menu-item v-slot="{ active }">
            <a
              href="/posts?sort_by=votes_count__DESC{{ request()->filled('completed') ? '&completed=on' : ''}}"
              :class="[active ? 'bg-white/5 text-fp-hi' : 'text-fp-mid', 'block px-4 py-2 text-sm']"
            >Most voted</a>
          </menu-item>
          <menu-item v-slot="{ active }">
            <a
              href="/posts?sort_by=votes_count__ASC{{ request()->filled('completed') ? '&completed=on' : ''}}"
              :class="[active ? 'bg-white/5 text-fp-hi' : 'text-fp-mid', 'block px-4 py-2 text-sm']"
            >Least voted</a>
          </menu-item>
          <menu-item v-slot="{ active }" class="border-t border-white/5" id="posts-form" as="form" action="/posts" method="GET">
              <input type="hidden" name="sort_by" value="{{ $sortBy }}">
              <span
                :class="[active ? 'bg-white/5 text-fp-hi' : 'text-fp-mid', 'flex items-center px-4 py-2 text-sm']"
              >
                <input onchange="document.querySelector('#posts-form').submit()" @checked(request()->filled('completed')) id="filter-completed-status" name="completed" type="checkbox" class="h-4 w-4 rounded-sm border-white/10 bg-fp-surface text-fp-accent focus:ring-fp-accent/40">
                <label for="filter-completed-status" class="ml-3 text-sm text-fp-mid">Show completed</label>
              </span>
          </menu-item>
        </template>
      </base-dropdown>
      @if(auth()->guard('contact')->check() && auth()->guard('contact')->user()->client_id == $client->id)
        <a href="/posts/create" class="inline-flex items-center rounded-md bg-fp-accent px-3 py-2 text-sm font-medium text-white hover:bg-fp-accent-hover focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">New post</a>
      @endif
    </x-slot:actions>
  </x-public.heading>

  <ul role="list" class="mt-8 divide-y divide-white/5">
    @forelse($posts as $post)
    <x-public.post-row :post="$post" :client="$client" />
    @empty
    <li class="py-16">
      <div class="text-center">
        <h3 class="text-sm font-medium text-fp-hi">No posts yet</h3>
        <p class="mt-1 text-sm text-fp-low">Be the first to ask for something.</p>
      </div>
    </li>
    @endforelse
  </ul>

  <div class="mt-8">
    {{ $posts->withQueryString()->links() }}
  </div>
</x-public-layout>
