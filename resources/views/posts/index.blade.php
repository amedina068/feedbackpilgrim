<x-app-layout>

<div>
  <div>
    <x-drawer id="filters" label="Filters" side="right">
      <form action="/posts" method="GET">
        <div class="py-6">
          <!-- Search input moved to top of posts list -->
          <input type="hidden" name="search" value="{{ request()->search }}">
          @if(request()->filled('board'))
            <input type="hidden" name="board" value="{{ request('board') }}">
          @endif
          <div>
            <label for="sort_by" class="block text-sm font-medium leading-6 text-fp-hi">Sort</label>
            <select id="sort_by" name="sort_by" class="mt-2 block w-full rounded-md border-0 py-1.5 pl-3 pr-10 text-fp-hi ring-1 ring-inset ring-white/10 focus:ring-2 focus:ring-fp-accent sm:text-sm sm:leading-6">
              <option @selected((request()->sort_by ?? 'created_at__DESC') == 'created_at__DESC') value="created_at__DESC">Newest</option>
              <option @selected(request()->sort_by == 'created_at__ASC') value="created_at__ASC">Oldest</option>
              <option @selected(request()->sort_by == 'votes_count__DESC') value="votes_count__DESC">Most Voted</option>
              <option @selected(request()->sort_by == 'votes_count__ASC') value="votes_count__ASC">Least Voted</option>
              <option @selected(request()->sort_by == 'score__DESC') value="score__DESC">Highest Priority</option>
              <option @selected(request()->sort_by == 'score__ASC') value="score__ASC">Lowest Priority</option>
            </select>
          </div>
          <div class="pt-6">
            <div class="flex items-center">
                <input @checked(request()->filled('completed')) id="filter-completed-status" name="completed" type="checkbox" class="h-4 w-4 rounded-sm border-white/10 text-fp-accent focus:ring-fp-accent">
                <label for="filter-completed-status" class="ml-3 text-sm text-fp-mid">Show Completed</label>
              </div>
          </div>
          <div class="pt-6">
            <div class="flex items-center">
                <input @checked(request()->filled('archived')) id="filter-archived-status" name="archived" type="checkbox" class="h-4 w-4 rounded-sm border-white/10 text-fp-accent focus:ring-fp-accent">
                <label for="filter-archived-status" class="ml-3 text-sm text-fp-mid">Show archived</label>
              </div>
          </div>
        </div>
        <div class="border-t border-white/5 py-6">
          <x-filters.group title="Tags">
              @foreach($tags as $tag)
                <div class="flex items-center">
                  <input @checked(in_array($tag->id, request()->get('tags', []))) id="filter-tag-{{ $tag->id }}" name="tags[]" value="{{ $tag->id }}" type="checkbox" class="h-4 w-4 rounded-sm border-white/10 text-fp-accent focus:ring-fp-accent">
                  <label for="filter-tag-{{ $tag->id }}" class="ml-3 text-sm text-fp-mid">{{ $tag->title }}</label>
                </div>
              @endforeach
          </x-filters.group>
        </div>
        <div class="flex justify-end">
          <button type="submit" class="rounded-md bg-fp-surface px-2.5 py-1.5 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface">Submit</button>
        </div>
      </form>
    </x-drawer>

    <div class="sm:px-6 lg:px-8">
      <div class="flex items-baseline justify-between sm:border-b border-white/5 pb-4">
        <h1 class="px-4 sm:px-0 text-3xl font-semibold tracking-tight text-fp-hi">Posts</h1>

        <div class="space-x-4 px-4 sm:px-0 flex items-center">
          <x-filters.button />
          <div>
            <a href="/posts/create" class="rounded-md bg-fp-surface px-2.5 py-1.5 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface">Create Post</a>
          </div>
        </div>
      </div>

      @isset($boards)
        <div class="flex flex-wrap items-center gap-2 px-4 pt-4 sm:px-0">
          <a href="/posts" class="rounded-full px-3 py-1 text-sm font-medium {{ request()->filled('board') ? 'text-fp-mid ring-1 ring-inset ring-white/10 hover:bg-fp-surface' : 'bg-fp-elevated text-white' }}">All boards</a>
          @foreach ($boards as $board)
            <a href="{{ url('/posts?board='.$board->id) }}" class="rounded-full px-3 py-1 text-sm font-medium {{ (int) request('board') === $board->id ? 'bg-fp-elevated text-white' : 'text-fp-mid ring-1 ring-inset ring-white/10 hover:bg-fp-surface' }}">{{ $board->name }}</a>
          @endforeach
          <a href="{{ route('boards.index') }}" class="ml-auto text-sm font-medium text-fp-accent hover:text-fp-accent">Manage boards</a>
        </div>
      @endisset

      <!-- Search input at top of posts list -->
      <div class="px-4 sm:px-0 pt-6 pb-4">
        <form action="/posts" method="GET" class="max-w-lg">
          <!-- Preserve existing filters -->
          @if(request()->filled('sort_by'))
            <input type="hidden" name="sort_by" value="{{ request()->sort_by }}">
          @endif
          @if(request()->filled('completed'))
            <input type="hidden" name="completed" value="on">
          @endif
          @if(request()->filled('archived'))
            <input type="hidden" name="archived" value="on">
          @endif
          @if(request()->filled('board'))
            <input type="hidden" name="board" value="{{ request('board') }}">
          @endif
          @if(request()->filled('tags'))
            @foreach(request()->get('tags', []) as $tagId)
              <input type="hidden" name="tags[]" value="{{ $tagId }}">
            @endforeach
          @endif
          
          <div class="relative">
            <label for="search" class="sr-only">Search posts</label>
            <input 
              id="search" 
              name="search" 
              type="search" 
              placeholder="Search posts..." 
              class="block w-full rounded-md border-0 py-2 pl-4 pr-12 text-fp-hi ring-1 ring-inset ring-white/10 placeholder:text-fp-low focus:ring-2 focus:ring-inset focus:ring-fp-accent sm:text-sm sm:leading-6" 
              value="{{ request()->search }}"
            >
            <button type="submit" class="absolute inset-y-0 right-0 flex items-center pr-3">
              <svg class="h-5 w-5 text-fp-low hover:text-fp-low" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
              </svg>
              <span class="sr-only">Search</span>
            </button>
          </div>
        </form>
      </div>

      <section class="pb-24">
        <div>
          <div>
            <div>
              <div>
                  <ul role="list" class="divide-y divide-white/5 overflow-hidden">
                    @forelse($posts as $post)
                      <li class="relative gap-x-6">
                        <a href="/posts/{{ $post->id }}" class="block p-4 hover:bg-fp-surface sm:px-6">
                          <div class="md:flex md:items-center md:justify-between">
                            <div>
                              <div class="flex justify-between text-sm items-center">
                                <div class="text-sm text-fp-mid">
                                  <span class="">{{ $post->created_at->diffForHumans() }}</span> 
                                  @if($post->contact_id)
                                    <span> by {{ $post->contact->name }}</span>
                                    @if($post->contact->top_customer)
                                    <span class="ml-2 inline-flex items-center rounded-md bg-fp-success/10 px-2 py-1 text-xs font-medium text-fp-success ring-1 ring-inset ring-fp-success/20">Top Customer</span>
                                    @endif
                                  @endif
                                </div>
                              </div>
                              <div>
                                <div class="mt-2 md:flex md:items-center md:space-x-2">
                                  <h3 class="text-base text-fp-hi font-semibold">
                                    {{$post->title }}
                                  </h3>
                                  @if($post->inProgress())
                                    <span class="inline-flex items-center gap-x-1.5 rounded-full px-1.5 py-0.5 text-xs font-medium text-fp-accent">
                                      <svg class="h-1.5 w-1.5 fill-fp-accent" viewBox="0 0 6 6" aria-hidden="true">
                                        <circle cx="3" cy="3" r="3" />
                                      </svg>
                                      In Progress
                                    </span>
                                  @endif
                                  @if($post->completed())
                                    <span class="inline-flex items-center gap-x-1.5 rounded-full px-1.5 py-0.5 text-xs font-medium text-fp-success">
                                      <svg class="h-1.5 w-1.5 fill-fp-success" viewBox="0 0 6 6" aria-hidden="true">
                                        <circle cx="3" cy="3" r="3" />
                                      </svg>
                                      Completed
                                    </span>
                                  @endif
                                  @if($post->archived_at)
                                    <span class="inline-flex items-center gap-x-1.5 rounded-full px-1.5 py-0.5 text-xs font-medium text-fp-mid">
                                      <svg class="h-1.5 w-1.5 fill-fp-low" viewBox="0 0 6 6" aria-hidden="true">
                                        <circle cx="3" cy="3" r="3" />
                                      </svg>
                                      Archived
                                    </span>
                                  @endif
                                </div>
                              </div>
                              @if($post->tags->count())
                                <div class="mt-4 flex flex-wrap">
                                  @foreach($post->tags as $tag)
                                    <span class="mb-2 mr-2 inline-flex items-center rounded-md bg-fp-surface px-2 py-1 text-xs font-medium text-fp-mid ring-1 ring-inset ring-white/10">{{ $tag->title }}</span>
                                  @endforeach
                                </div>
                              @endif
                            </div>
                            <div class="flex space-x-4 items-center mt-2 md:mt-0">
                              @isset($post->score)
                                <div class="inline-flex items-center" title="Priority: votes weighted by voter MRR">
                                  <span class="rounded-md bg-fp-accent/10 px-2 py-1 text-xs font-medium text-fp-accent ring-1 ring-inset ring-fp-accent/20">★ {{ (int) round($post->score) }}</span>
                                </div>
                              @endisset
                              <div class="inline-flex items-center space-x-2">
                                <span class="text-sm text-fp-mid">{{ $post->votes_count }}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M6.633 10.25c.806 0 1.533-.446 2.031-1.08a9.041 9.041 0 0 1 2.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 0 0 .322-1.672V2.75a.75.75 0 0 1 .75-.75 2.25 2.25 0 0 1 2.25 2.25c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.725 1.282m0 0h3.126c1.026 0 1.945.694 2.054 1.715.045.422.068.85.068 1.285a11.95 11.95 0 0 1-2.649 7.521c-.388.482-.987.729-1.605.729H13.48c-.483 0-.964-.078-1.423-.23l-3.114-1.04a4.501 4.501 0 0 0-1.423-.23H5.904m10.598-9.75H14.25M5.904 18.5c.083.205.173.405.27.602.197.4-.078.898-.523.898h-.908c-.889 0-1.713-.518-1.972-1.368a12 12 0 0 1-.521-3.507c0-1.553.295-3.036.831-4.398C3.387 9.953 4.167 9.5 5 9.5h1.053c.472 0 .745.556.5.96a8.958 8.958 0 0 0-1.302 4.665c0 1.194.232 2.333.654 3.375Z" />
                                </svg>
                              </div>
                              <div class="inline-flex items-center space-x-2">
                                <span class="text-sm text-fp-mid">{{ $post->comments_count }}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.068.157 2.148.279 3.238.364.466.037.893.281 1.153.671L12 21l2.652-3.978c.26-.39.687-.634 1.153-.67 1.09-.086 2.17-.208 3.238-.365 1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                                </svg>
                              </div>
                            </div>
                          </div>
                        </a>
                      </li>
                    @empty
                      <li class="px-4 pt-6 pb-8">
                        <x-empty-state title="No Posts" description="Get started by adding a post." href="/posts/create" action="Add posts" />
                      </li>
                    @endforelse
                  </ul>
                  <div class="mt-5 px-4 sm:px-0">
                      {{ $posts->withQueryString()->links() }}
                  </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</div>
</x-app-layout>
