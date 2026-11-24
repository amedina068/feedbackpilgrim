<x-public-layout>
  <x-public.breadcrumbs :client="$client">
    <x-public.breadcrumbs.item href="/testimonials">Testimonials</x-public.breadcrumbs.item>
    <x-public.breadcrumbs.item>{{ $testimonial->title }}</x-public.breadcrumbs.item>
  </x-public.breadcrumbs>

    <div class="mt-8 flex items-center gap-x-4">
      <x-generic-vote-form :model="$testimonial" :client="$client" />
      <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
        <h1 class="text-2xl font-semibold tracking-tight text-fp-hi sm:text-3xl">{{ $testimonial->title }}</h1>
        @if(! $testimonial->approved_at)
          <span class="inline-flex items-center rounded-md bg-fp-warning/10 px-2 py-0.5 font-mono text-[11px] uppercase tracking-wide text-fp-warning ring-1 ring-inset ring-fp-warning/20">Pending review</span>
        @endif
        @if($testimonial->contact && auth()->guard('contact')->check() && $testimonial->contact_id === auth()->guard('contact')->id())
          <a href="/testimonials/{{ $testimonial->id }}/edit" class="text-sm font-medium text-fp-accent hover:text-fp-accent-hover">Edit</a>
        @endif
      </div>
    </div>

    <div class="mt-8 flex flex-col md:flex-row md:space-x-8">
      <div class="min-w-0 flex-1">
        @if($client->testimonial_rating_enabled && $testimonial->rating)
        <div class="mb-6 flex items-center gap-x-2">
          <div class="flex items-center gap-x-0.5">
            @for($i = 1; $i <= 5; $i++)
              <svg class="h-5 w-5 shrink-0 {{ $i <= $testimonial->rating ? 'fill-fp-warning' : 'fill-white/10' }}" viewBox="0 0 20 20" aria-hidden="true">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
              </svg>
            @endfor
          </div>
          <span class="font-mono text-sm tabular-nums text-fp-mid">{{ $testimonial->rating }}/5</span>
        </div>
        @endif

        <div class="prose prose-invert max-w-full">
          <p>{{ $testimonial->description }}</p>
        </div>

        <div class="mt-6">
          <div class="flex items-center gap-x-3">
            <img src="{{ $testimonial->avatar_image }}" alt="{{ $testimonial->display_username }}" class="h-10 w-10 rounded-full bg-fp-surface outline-solid outline-1 -outline-offset-1 outline-white/10">
            <div>
              <p class="text-sm font-medium text-fp-hi">{{ $testimonial->display_username }}</p>
              @if($testimonial->subtitle)
                <p class="text-sm text-fp-mid">{{ $testimonial->subtitle }}</p>
              @endif
            </div>
          </div>
        </div>
      </div>
      <div class="mt-8 w-full md:mt-0 md:w-72">
        <h4 class="text-sm font-medium text-fp-mid">Voters</h4>
        <ul role="list" class="mt-2 divide-y divide-white/5">
          @foreach($testimonial->votes as $vote)
          <li class="flex items-center gap-x-3 py-2.5">
            <img class="h-6 w-6 flex-none rounded-full bg-fp-surface outline-solid outline-1 -outline-offset-1 outline-white/10" src="{{ $vote->contact->avatar }}" alt="">
            <p class="min-w-0 truncate text-sm font-medium text-fp-hi">{{ $vote->contact->name ?? 'N/A' }}</p>
          </li>
          @endforeach
          @if($testimonial->votes_count > $testimonial->votes->count())
            <li class="py-2.5">
              <p class="text-sm text-fp-low">and {{ $testimonial->votes_count - $testimonial->votes->count() }} more</p>
            </li>
          @endif
        </ul>
      </div>
    </div>
    <div class="mt-12">
      <h2 class="text-lg font-medium text-fp-hi">Comments</h2>
      <x-public-comment-form
        :client="$client"
        :model="$testimonial"
      />
      <ul role="list" class="mt-8 divide-y divide-white/5">
        @foreach($testimonial->comments as $comment)
        <x-public.comment :comment="$comment" />
        @endforeach
      </ul>
    </div>
</x-public-layout>
