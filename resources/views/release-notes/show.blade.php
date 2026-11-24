<x-app-layout>
    <div class="sm:px-6 lg:px-8">
      <div class="flex items-baseline justify-between sm:border-b border-white/5 pb-4">
        <div class="flex items-baseline space-x-4">
          <div>
            <div>
              <h1 class="px-4 sm:px-0 text-3xl font-semibold tracking-tight text-fp-hi">{{ $releaseNote->title }}</h1>
            </div> 
            @if($releaseNote->isPublished())
              <p class="text-sm px-4 sm:px-0 text-fp-mid">Published at {{ $releaseNote->published_at->format('H:i \o\n M jS, Y') }}</p>
            @endif
          </div>
        </div>
        <div class="space-x-4 px-4 sm:px-0 flex items-center">
          <div>
            <a href="/release-notes/{{ $releaseNote->id }}/edit" class="rounded-md bg-fp-surface px-2.5 py-1.5 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface">Edit</a>
          </div>
        </div>
      </div>

      <section aria-labelledby="release-note-content" class="pb-24 pt-6">
        <div class="grid grid-cols-1 gap-x-8 gap-y-10 lg:grid-cols-5">
          <div>
            <div class="inline-flex w-full px-4 sm:px-0">
              @if($releaseNote->isPublished())
                <form action="/release-notes/{{ $releaseNote->id }}/publication" method="POST" class="w-full">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="justify-center w-full relative flex items-center rounded-md bg-fp-surface px-3 py-2 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface focus:z-10">Unpublish</button> 
                </form>
              @else
                <form action="/release-notes/{{ $releaseNote->id }}/publication" method="POST" class="w-full">
                  @csrf
                  <button type="submit" class="justify-center w-full relative flex items-center rounded-md bg-fp-surface px-3 py-2 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface focus:z-10">Publish</button> 
                </form>
              @endif
            </div>
          </div>
          <div class="lg:col-span-4 ">
            <div class="overflow-hidden bg-fp-surface sm:rounded-lg">
              <div class="px-4 py-4 sm:px-6">
                  <div class="flex justify-between items-baseline">
                    <div class="flex space-x-2 items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                      </svg>
                      <h3 class="text-lg font-semibold leading-7 text-fp-hi">
                        Content
                      </h3>
                    </div>
                  </div>
                </div>
              <div class="border-t border-white/5">
                <div class="px-4 py-6">
                  <x-markdown class="prose max-w-full">{{ $releaseNote->content }}</x-markdown>
                </div>
              </div>
            </div>
            <div class="mt-8">
              <div class="overflow-hidden bg-fp-surface sm:rounded-lg">
                <div class="px-4 py-4 sm:px-6">
                  <div class="flex justify-between items-baseline">
                    <div class="flex space-x-2 items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.068.157 2.148.279 3.238.364.466.037.893.281 1.153.671L12 21l2.652-3.978c.26-.39.687-.634 1.153-.67 1.09-.086 2.17-.208 3.238-.365 1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                      </svg>
                      <h3 class="text-lg font-semibold leading-7 text-fp-hi">
                        Comments
                      </h3>
                    </div>
                  </div>
                </div>
                <div class="border-t border-white/5">
                  <dl class="divide-y divide-white/5">
                    @forelse($releaseNote->comments as $comment)
                      <div class="px-4 py-4 ">
                        <p class="text-xs text-fp-mid">
                          <a class="hover:underline text-fp-hi font-medium " href="/contacts/{{ $comment->contact_id }}">{{ $comment->contact->email }}</a> commented {{ $comment->created_at->diffForHumans() }}
                        </p>
                        <p class="mt-2 text-sm text-fp-mid">
                          {{ Str::limit($comment->content, 100) }}
                        </p>
                      </div>
                    @empty
                    <x-empty-state class="mx-4 mt-6 mb-8" title="No Comments" description="Start the conversation with the first comment." href="/release-notes/{{ $releaseNote->id }}/comments/create" action="Add comment" />
                    @endforelse
                  </dl>
                </div>
              </div> 
            </div>
          </div>
        </div>
      </section>
    </div>
</x-app-layout>
