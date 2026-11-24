<x-app-layout>

<div>
  <div>
    <div class="sm:px-6 lg:px-8">
      <div class="flex items-baseline justify-between sm:border-b border-white/5 pb-4">
        <h1 class="px-4 sm:px-0 text-3xl font-semibold tracking-tight text-fp-hi">{{ $post->title }} Comments</h1>
      </div>

      <section class="pb-24 pt-6">
        <div>
          <div>
            <div role="list" class="overflow-hidden bg-fp-surface ring-1 ring-white/10 sm:rounded-xl">
              <dl class="divide-y divide-white/5">
                <div class="pb-8">
                  <x-comment-form action="/posts/{{ $post->id }}/comments" />
                </div>
                @forelse($comments as $comment)
                  <div class="px-4 py-4 ">
                    <p class="text-sm text-fp-mid">
                      <a class="hover:underline text-fp-hi font-medium " href="/contacts/{{ $comment->contact_id }}">{{ $comment->contact->name ?? $comment->contact->email }}</a> commented {{ $comment->created_at->diffForHumans() }}
                    </p>
                    <p class="mt-2 text-base text-fp-mid">
                      {{ $comment->content }}
                    </p>
                    @if($comment->getMedia('photos')->isNotEmpty())
                      <div class="mt-2 flex flex-wrap gap-2">
                        @foreach($comment->getMedia('photos') as $photo)
                          <a href="{{ $photo->getUrl() }}" target="_blank" rel="noopener">
                            <img src="{{ $photo->getUrl() }}" alt="{{ $photo->name }}" class="h-20 w-20 rounded-lg object-cover ring-1 ring-white/5 hover:opacity-90">
                          </a>
                        @endforeach
                      </div>
                    @endif
                  </div>
                @empty
                @endforelse
              </dl>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</div>
</x-app-layout>
