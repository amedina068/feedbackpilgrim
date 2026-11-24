<x-app-layout>

<div>
  <div>
    <x-drawer id="filters" label="Filters" side="right">
      <form action="/release-notes" method="GET">
        <div class="py-6">
          <div>
            <label for="sort_by" class="block text-sm font-medium leading-6 text-fp-hi">Sort</label>
            <select id="sort_by" name="sort_by" class="mt-2 block w-full rounded-md border-0 py-1.5 pl-3 pr-10 text-fp-hi ring-1 ring-inset ring-white/10 focus:ring-2 focus:ring-fp-accent sm:text-sm sm:leading-6">
              <option @selected((request()->sort_by ?? 'created_at__DESC') == 'created_at__DESC') value="created_at__DESC">Newest</option>
              <option @selected(request()->sort_by == 'created_at__ASC') value="created_at__ASC">Oldest</option>
              <option @selected(request()->sort_by == 'votes_count__DESC') value="votes_count__DESC">Most voted</option>
              <option @selected(request()->sort_by == 'votes_count__ASC') value="votes_count__ASC">Least voted</option>
            </select>
          </div>
        </div>
        <div class="border-t border-white/5 py-6">
          <x-filters.group title="Tags">
              @foreach($tags as $tag)
                <div class="flex items-center">
                  <input @checked(in_array($tag->id, (array) request()->get('tags', []))) id="filter-tag-{{ $tag->id }}" name="tags[]" value="{{ $tag->id }}" type="checkbox" class="h-4 w-4 rounded-sm border-white/10 text-fp-accent focus:ring-fp-accent">
                  <label for="filter-tag-{{ $tag->id }}" class="ml-3 text-sm text-fp-mid">{{ $tag->title }}</label>
                </div>
              @endforeach
          </x-filters.group>
        </div>
        <div class="flex justify-end">
          <button type="submit" class="rounded-md bg-fp-surface px-2.5 py-1.5 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface">Apply filters</button>
        </div>
      </form>
    </x-drawer>

    <div class="sm:px-6 lg:px-8">
      <div class="flex items-baseline justify-between sm:border-b border-white/5 pb-4">
        <h1 class="px-4 sm:px-0 text-3xl font-semibold tracking-tight text-fp-hi">Release Notes</h1>

        <div class="space-x-4 px-4 sm:px-0 flex items-center">
          <x-filters.button />
          <div>
            <a href="/release-notes/create" class="rounded-md bg-fp-surface px-2.5 py-1.5 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface">Create release note</a>
          </div>
        </div>
      </div>

      <section class="pb-24 pt-6">
        <div>
            <ul role="list" class="divide-y divide-white/5 overflow-hidden">
              @forelse($releaseNotes as $releaseNote)
                <li class="relative gap-x-6">
                  <a href="/release-notes/{{ $releaseNote->id }}" class="block p-4 hover:bg-fp-surface sm:px-6">
                    <div class="flex justify-between text-sm items-baseline">
                      <div class="flex items-center gap-x-2">
                        @if($releaseNote->published_at)
                          <span class="inline-flex items-center rounded-md bg-fp-success/10 px-2 py-0.5 font-mono text-xs font-medium uppercase tracking-wide text-fp-success">Published</span>
                        @else
                          <span class="inline-flex items-center rounded-md bg-white/5 px-2 py-0.5 font-mono text-xs font-medium uppercase tracking-wide text-fp-mid">Draft</span>
                        @endif
                        <span class="text-sm text-fp-mid">{{ $releaseNote->created_at->diffForHumans() }}</span>
                      </div>
                      
                      <div>
                        <p class="font-mono text-lg tabular-nums text-fp-low">
                          {{ $releaseNote->votes_count }}
                          <span class="font-sans text-sm font-medium">{{ Str::plural('vote', $releaseNote->votes_count) }}</span>
                        </p>
                      </div>
                    </div>
                    <div>
                        <h3 class="mt-2 text-lg text-fp-hi font-semibold">
                            {{$releaseNote->title }}
                        </h3>
                        <p class="text-sm mt-1 text-fp-mid mt max-w-6xl">{{ Str::limit(trim(strip_tags(Str::markdown($releaseNote->content ?? ''))), 200) }}</p>
                    </div>
                    @if($releaseNote->tags->count())
                    <div class="mt-4 flex flex-wrap">
                      @foreach($releaseNote->tags as $tag)
                        <span class="mb-2 mr-2 inline-flex items-center rounded-md bg-fp-surface px-2 py-1 text-xs font-medium text-fp-mid ring-1 ring-inset ring-white/10">{{ $tag->title }}</span>
                      @endforeach
                    </div>
                    @endif
                  </a>
                </li>
              @empty
                <li class="px-4 pt-6 pb-8">
                  <x-empty-state title="{{ request()->hasAny(['tags']) ? 'No release notes match these filters' : 'No release notes yet' }}" description="{{ request()->hasAny(['tags']) ? 'Clear the tag filter to see all release notes.' : 'Write the first one when you ship something.' }}" href="/release-notes/create" action="Create release note" />
                </li>
              @endforelse
            </ul>
            <div class="mt-5 px-4 sm:px-0">
                {{ $releaseNotes->withQueryString()->links() }}
            </div>
        </div>
      </section>
    </div>
  </div>
</div>
</x-app-layout>
