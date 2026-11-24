<x-public-layout>
  <x-public.breadcrumbs :client="$client">
    <x-public.breadcrumbs.item href="/posts">Posts</x-public.breadcrumbs.item>
    <x-public.breadcrumbs.item>{{ $post->title }}</x-public.breadcrumbs.item>
  </x-public.breadcrumbs>

    <div class="mt-8 flex items-center gap-x-4">
      <x-vote-form :model="$post" :client="$client" />
      <div class="flex min-w-0 flex-wrap items-center gap-3">
        <h1 class="text-2xl font-semibold tracking-tight text-fp-hi sm:text-3xl">{{ $post->title }}</h1>
        <x-public.post-status :post="$post" />
      </div>
    </div>
    <div class="mt-8 flex flex-col md:flex-row md:space-x-8">
      <div class="min-w-0 flex-1">
        <div class="prose prose-invert max-w-full">
          <x-markdown>{{ $post->content }}</x-markdown>
        </div>
        @if($post->getMedia('attachments')->isNotEmpty())
          <div class="mt-6">
            <h4 class="text-sm font-medium text-fp-hi">Attachments</h4>
            <ul role="list" class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-3">
              @foreach($post->getMedia('attachments') as $media)
                <li>
                  <a href="{{ $media->getUrl() }}" target="_blank" rel="noopener" class="group block">
                    @if(Str::startsWith($media->mime_type, 'image/'))
                      <img src="{{ $media->getUrl() }}" alt="{{ $media->name }}" class="h-32 w-full rounded-lg object-cover outline-solid outline-1 -outline-offset-1 outline-white/10 group-hover:opacity-90">
                    @else
                      <div class="flex h-32 w-full items-center justify-center rounded-lg bg-fp-surface ring-1 ring-inset ring-white/10 group-hover:bg-fp-elevated">
                        <span class="break-all px-2 text-center text-xs font-medium text-fp-mid">{{ $media->name }}.{{ $media->extension }}</span>
                      </div>
                    @endif
                  </a>
                </li>
              @endforeach
            </ul>
          </div>
        @endif
        <div class="mt-6">
          <div class="flex items-center gap-x-3">
            <img src="{{ $post->contact->avatar }}" alt="{{ $post->contact->name }}" class="h-10 w-10 rounded-full bg-fp-surface outline-solid outline-1 -outline-offset-1 outline-white/10">
            <p class="text-sm text-fp-mid">By <span class="font-medium text-fp-hi">{{ $post->contact->name }}</span></p>
          </div>
        </div>
      </div>
      <div class="mt-8 w-full md:mt-0 md:w-72">
        <h4 class="text-sm font-medium text-fp-mid">Voters</h4>
        <ul role="list" class="mt-2 divide-y divide-white/5">
          @foreach($post->votes as $vote)
          <li class="flex items-center gap-x-3 py-2.5">
            <img class="h-6 w-6 flex-none rounded-full bg-fp-surface outline-solid outline-1 -outline-offset-1 outline-white/10" src="{{ $vote->contact->avatar }}" alt="">
            <p class="min-w-0 truncate text-sm font-medium text-fp-hi">{{ $vote->contact->name ?? 'N/A' }}</p>
          </li>
          @endforeach
          @if($post->votes_count > $post->votes->count())
            <li class="py-2.5">
              <p class="text-sm text-fp-low">and {{ $post->votes_count - $post->votes->count() }} more</p>
            </li>
          @endif
        </ul>
      </div>
    </div>
    <div class="mt-12">
      <h2 class="text-lg font-medium text-fp-hi">Comments</h2>
      <x-public-comment-form
        :client="$client"
        :model="$post"
      />
      <ul role="list" class="mt-8 divide-y divide-white/5">
        @foreach($post->comments as $comment)
        <x-public.comment :comment="$comment" />
        @endforeach
      </ul>
    </div>
</x-public-layout>
