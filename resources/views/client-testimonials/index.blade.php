<x-public-layout>
  <x-public.breadcrumbs :client="$client">
    <x-public.breadcrumbs.item>Testimonials</x-public.breadcrumbs.item>
  </x-public.breadcrumbs>

  <div class="mt-8">
  <x-public.heading>
    Testimonials
    <x-slot:actions>
      @if(auth()->guard('contact')->check() && auth()->guard('contact')->user()->client_id == $client->id)
        <a href="/testimonials/create" class="inline-flex items-center rounded-md bg-fp-accent px-3 py-2 text-sm font-medium text-white hover:bg-fp-accent-hover focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">New testimonial</a>
      @endif
    </x-slot:actions>
  </x-public.heading>

  <div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @foreach($testimonials as $testimonial)
    <a href="/testimonials/{{ $testimonial->id }}" class="block rounded-lg bg-fp-surface p-6 ring-1 ring-white/5 transition-colors hover:ring-white/10">
      <div class="mb-4 flex items-center justify-between">
        @if($client->testimonial_rating_enabled && $testimonial->rating)
        <div class="flex items-center gap-x-0.5">
          @for($i = 1; $i <= 5; $i++)
            <svg class="h-4 w-4 shrink-0 {{ $i <= $testimonial->rating ? 'fill-fp-warning' : 'fill-white/10' }}" viewBox="0 0 20 20" aria-hidden="true">
              <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
            </svg>
          @endfor
        </div>
        @else
        <div></div>
        @endif
        <span class="flex items-center gap-x-2">
          @if(! $testimonial->approved_at)
            <span class="inline-flex items-center rounded-md bg-fp-warning/10 px-2 py-0.5 font-mono text-[11px] uppercase tracking-wide text-fp-warning ring-1 ring-inset ring-fp-warning/20">Pending review</span>
          @endif
          <span class="font-mono text-xs text-fp-low">{{ $testimonial->created_at->format('M j, Y') }}</span>
        </span>
      </div>

      <h2 class="mb-2 text-base font-medium text-fp-hi">{{ $testimonial->title }}</h2>

      <p class="mb-4 line-clamp-3 text-sm text-fp-mid">{{ $testimonial->description }}</p>

      <div class="flex items-center justify-between">
        <div class="flex min-w-0 items-center gap-x-3">
          <img class="h-8 w-8 shrink-0 rounded-full bg-fp-base outline-solid outline-1 -outline-offset-1 outline-white/10" src="{{ $testimonial->avatar_image }}" alt="{{ $testimonial->display_username }}">
          <div class="min-w-0">
            <p class="truncate text-sm font-medium text-fp-hi">{{ $testimonial->display_username }}</p>
            @if($testimonial->subtitle)
              <p class="truncate text-xs text-fp-low">{{ $testimonial->subtitle }}</p>
            @endif
          </div>
        </div>

        <div class="flex shrink-0 items-center gap-x-3 text-fp-low">
          <div class="flex items-center gap-x-1">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 011.037-.443 48.282 48.282 0 005.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>
            </svg>
            <span class="font-mono text-xs tabular-nums">{{ $testimonial->comments_count }}</span>
          </div>
          <div class="flex items-center gap-x-1">
            <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
              <path fill-rule="evenodd" d="M10 3a.75.75 0 0 1 .55.24l5.25 5.5a.75.75 0 1 1-1.1 1.02L10.75 6.3V16a.75.75 0 0 1-1.5 0V6.3L5.3 9.76a.75.75 0 0 1-1.1-1.02l5.25-5.5A.75.75 0 0 1 10 3Z" clip-rule="evenodd"/>
            </svg>
            <span class="font-mono text-xs tabular-nums">{{ $testimonial->votes_count }}</span>
          </div>
        </div>
      </div>
    </a>
    @endforeach
  </div>

  @if($testimonials->isEmpty())
    <div class="py-16 text-center">
      <h3 class="text-sm font-medium text-fp-hi">No testimonials yet</h3>
      <p class="mt-1 text-sm text-fp-low">Be the first to share your experience.</p>
      @if(auth()->guard('contact')->check() && auth()->guard('contact')->user()->client_id == $client->id)
        <div class="mt-6">
          <a href="/testimonials/create" class="inline-flex items-center rounded-md bg-fp-surface px-3 py-2 text-sm font-medium text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-elevated">
            Create testimonial
          </a>
        </div>
      @endif
    </div>
  @endif

  <div class="mt-8">
    {{ $testimonials->withQueryString()->links() }}
  </div>
</x-public-layout>
