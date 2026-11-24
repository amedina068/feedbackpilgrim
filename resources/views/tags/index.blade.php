<x-app-layout>

<div>
  <div>
    <div class="sm:px-6 lg:px-8">
      <div class="flex items-baseline justify-between sm:border-b border-white/5 pb-4">
        <h1 class="px-4 sm:px-0 text-3xl font-semibold tracking-tight text-fp-hi">Tags</h1>

        <div class="space-x-4 px-4 sm:px-0 flex items-center">
          <div>
            <a href="/tags/create" class="rounded-md bg-fp-surface px-2.5 py-1.5 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface">Create Tag</a>
          </div>
        </div>
      </div>

      <section class="pb-24 pt-6">
        <div>
          <div class="lg:col-span-4">
            <div>
              <div>
                  <ul role="list" class="divide-y divide-white/5 overflow-hidden">
                    @forelse($tags as $tag)
                      <li class="relative gap-x-6">
                        <a href="/tags/{{ $tag->id }}" class="block p-4 hover:bg-fp-surface sm:px-6">
                          <div class="flex justify-between text-sm items-baseline">
                            <div class="text-sm text-fp-mid">
                              <span class="">{{ $tag->created_at->diffForHumans() }}</span> 
                            </div>
                          </div>
                          <div>
                            <div class="mt-2 flex justify-between items-baseline space-x-2">
                              <h3 class="text-base text-fp-hi font-semibold">
                                {{ $tag->title }}
                              </h3>
                              <div class="flex space-x-4 items-center">
                                <div class="inline-flex items-center space-x-2">
                                  <span class="text-sm text-fp-mid">{{ $tag->posts_count }}</span>
                                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                  </svg>
                                </div>
                                <div class="inline-flex items-center space-x-2">
                                  <span class="text-sm text-fp-mid">{{ $tag->contacts_count }}</span>
                                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                  </svg>
                                </div>
                              </div>
                            </div>
                          </div>
                        </a>
                      </li>
                    @empty
                      <li class="px-4 pt-6 pb-8">
                        <x-empty-state title="No Tags" description="Get started by creating a tag." href="/tags/create" action="Add tags" />
                      </li>
                    @endforelse
                  </ul>
                  <div class="mt-5 px-4 sm:px-0">
                      {{ $tags->withQueryString()->links() }}
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
