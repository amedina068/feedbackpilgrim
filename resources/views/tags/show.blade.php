<x-app-layout>
    <div class="sm:px-6 lg:px-8">
      <div class="flex items-baseline justify-between sm:border-b border-white/5 pb-4">
        <h1 class="px-4 sm:px-0 text-3xl font-semibold tracking-tight text-fp-hi">{{ $tag->title }}</h1>
        <form action="/tags/{{ $tag->id }}" method="POST" class="px-4 sm:px-0">
          @csrf
          @method('DELETE')
          <button type="submit" class="text-sm font-medium text-fp-danger hover:text-fp-danger" onclick="return confirm('Delete this tag? It is removed from all posts, contacts and release notes.')">Delete tag</button>
        </form>
      </div>
      <form action="/tags/{{ $tag->id }}" method="POST" class="mt-6 px-4 sm:px-0 max-w-md">
        @csrf
        @method('PATCH')
        <x-base.label for="title">Rename</x-base.label>
        <div class="mt-1 flex gap-2">
          <x-base.input type="text" id="title" name="title" :value="old('title', $tag->title)" required/>
          <button type="submit" class="rounded-md bg-fp-surface px-2.5 py-1.5 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface">Save</button>
        </div>
        @error('title')
          <p class="mt-1 text-sm text-fp-danger font-medium">{{ $message }}</p>
        @enderror
      </form>
      <section class="pb-24 pt-6">
        <div class="">
          <div class="">
            <div>
              <ul role="list" class="divide-y mt-8 divide-white/5 overflow-hidden">
                <div class="bg-fp-surface px-4 py-4 sm:px-6">
                  <div class="flex flex-wrap items-baseline justify-between sm:flex-nowrap">
                    <div class="flex space-x-2 items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                      </svg>
                      <h3 class="text-lg font-semibold leading-7 text-fp-hi">
                        Posts
                      </h3>
                    </div>
                  </div>
                </div>
                  <ul role="list" class="divide-y divide-white/5 overflow-hidden bg-fp-surface">
                    @forelse($tag->posts as $post)
                      <li class="relative gap-x-6">
                        <a href="/posts/{{ $post->id }}" class="block p-4 hover:bg-fp-surface sm:px-6">
                          <div class="flex justify-between text-sm items-baseline">
                            <div class="text-sm text-fp-mid">
                              <span class="">{{ $post->created_at->diffForHumans() }}</span> 
                              @if($post->contact_id)
                                <span> by {{ $post->contact->name }}</span>
                              @endif
                            </div>
                          </div>
                          <div>
                            <div class="mt-2 flex items-baseline space-x-2">
                              <h3 class="text-base text-fp-hi font-semibold">
                                {{$post->title }}
                              </h3>
                              @if($post->pending())
                                <span class="inline-flex items-center rounded-md bg-fp-warning/10 px-2 py-1 text-xs font-medium text-fp-warning ring-1 ring-inset ring-fp-warning/20">Pending</span>
                              @endif
                              @if($post->inProgress())
                                <span class="inline-flex items-center rounded-md bg-fp-accent/10 px-2 py-1 text-xs font-medium text-fp-accent ring-1 ring-inset ring-fp-accent/20">In Progress</span>
                              @endif
                              @if($post->completed())
                                <span class="inline-flex items-center rounded-md bg-fp-success/10 px-2 py-1 text-xs font-medium text-fp-success ring-1 ring-inset ring-fp-success/20">completed</span>
                              @endif
                              @if($post->archived_at)
                                <span class="inline-flex items-center rounded-md bg-fp-surface px-2 py-1 text-xs font-medium text-fp-hi ring-1 ring-inset ring-white/10">Archived</span>
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
                        </a>
                      </li>
                    @empty
                      <li class="px-4 pt-6 pb-8">
                        <x-empty-state title="No Posts" description="Get started by adding a post." href="/posts/create" action="Add posts" />
                      </li>
                    @endforelse
                  </ul>
              </ul>
            </div>
          </div>
        </div>
        <div class="mt-8">
                <div class="overflow-hidden">
                  <div class="px-4 py-4 sm:px-6">
                    <div class="flex justify-between items-baseline">
                      <div class="flex space-x-2 items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                        <h3 class="text-lg font-semibold leading-7 text-fp-hi">
                          Contacts
                        </h3>
                      </div>
                    </div>
                  </div>
                  <div class="border-t border-white/5">
                    <ul class="divide-y divide-white/5">
                      @forelse($tag->contacts as $contact)
                        <li class="relative flex items-center justify-between gap-x-6 px-4 py-5 hover:bg-fp-surface sm:px-6">
                          <div class="flex min-w-0 items-center gap-x-4">
                            <img src="{{ $contact->avatar }}" alt="" class="size-10 shrink-0 rounded-full outline-1 -outline-offset-1 outline-white/10">
                            <div class="flex min-w-0 flex-col">
                              <a href="/contacts/{{ $contact->id }}" class="truncate rounded-sm text-sm/6 font-medium text-fp-hi after:absolute after:inset-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">{{ $contact->name ?? 'N/A' }}</a>
                              <a href="mailto:{{ $contact->email }}" target="_blank" class="relative truncate rounded-sm text-xs/5 text-fp-low hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">{{ $contact->email }}</a>
                            </div>
                          </div>
                          <p class="shrink-0 font-mono text-sm text-fp-mid tabular-nums max-sm:hidden">{{ $contact->posts_count }} Posts created</p>
                        </li>
                      @empty
                        <li class="px-4 pt-6 pb-8">
                          <x-empty-state title="No Contacts yet" />
                        </li>
                      @endforelse
                    </ul>
                  </div>
                </div>
              </div>
      </section>
    </div>
</x-app-layout>
