<x-iframe-layout>
    <div class="p-4 sm:p-6">
        @if ($boards->count() > 1)
            <div class="mb-6 flex flex-wrap gap-2">
                <a href="{{ url('/iframe/roadmap') }}" class="rounded-full px-3 py-1.5 text-sm font-medium {{ request('board') ? 'text-gray-600 ring-1 ring-inset ring-gray-300 hover:bg-gray-50' : 'bg-gray-900 text-white' }}">All</a>
                @foreach ($boards as $board)
                    <a href="{{ url('/iframe/roadmap?board='.$board->slug) }}" class="rounded-full px-3 py-1.5 text-sm font-medium {{ request('board') === $board->slug ? 'bg-gray-900 text-white' : 'text-gray-600 ring-1 ring-inset ring-gray-300 hover:bg-gray-50' }}">{{ $board->name }}</a>
                @endforeach
            </div>
        @endif

        @if ($stages->isEmpty())
            <p class="rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-600">Nothing on the roadmap yet.</p>
        @endif

        <div class="grid grid-cols-1 gap-x-6 gap-y-8 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($stages as $stage)
                <section>
                    <header class="flex items-center gap-2 border-b border-gray-950/10 pb-2.5">
                        <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $stage->color }}"></span>
                        <h3 class="text-sm font-semibold text-gray-900">{{ $stage->name }}</h3>
                        <span class="ml-auto rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">{{ $stage->posts->count() }}</span>
                    </header>

                    <ul role="list" class="mt-4 space-y-3">
                        @forelse ($stage->posts as $post)
                            <li class="rounded-xl border border-gray-200 bg-white p-4">
                                <a href="{{ $client->publicSite }}/posts/{{ $post->id }}" target="_blank" rel="noopener" class="text-sm font-semibold text-gray-900 hover:text-orange-600">{{ $post->title }}</a>
                                <p class="mt-1 line-clamp-2 text-sm text-gray-600">{{ $post->content_excerpt }}</p>
                                <div class="mt-3 inline-flex items-center gap-1.5 text-xs font-medium text-gray-500">
                                    <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M10 3a.75.75 0 0 1 .55.24l5.25 5.5a.75.75 0 1 1-1.1 1.02L10.75 6.3V16a.75.75 0 0 1-1.5 0V6.3L5.3 9.76a.75.75 0 0 1-1.1-1.02l5.25-5.5A.75.75 0 0 1 10 3Z" clip-rule="evenodd" />
                                    </svg>
                                    {{ $post->votes_count }} {{ Str::plural('vote', $post->votes_count) }}
                                </div>
                            </li>
                        @empty
                            <li class="rounded-xl border border-dashed border-gray-300 p-4 text-sm text-gray-600">Nothing here yet.</li>
                        @endforelse
                    </ul>
                </section>
            @endforeach
        </div>
    </div>
</x-iframe-layout>
