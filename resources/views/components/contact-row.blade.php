@props(['contact'])

{{-- One contact in a list. The name link covers the whole row. --}}
<li class="relative flex flex-col gap-4 px-4 py-5 hover:bg-fp-surface sm:px-6 md:flex-row md:items-center md:justify-between">
  <div class="flex min-w-0 flex-col gap-y-3">
    <div class="flex min-w-0 items-center gap-x-4">
      <img src="{{ $contact->avatar }}" alt="" class="size-10 shrink-0 rounded-full outline-1 -outline-offset-1 outline-white/10">
      <div class="flex min-w-0 flex-col">
        <div class="flex flex-wrap items-center gap-2">
          <a href="/contacts/{{ $contact->id }}" class="rounded-sm text-sm/6 font-medium text-fp-hi after:absolute after:inset-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">{{ $contact->name ?? 'N/A' }}</a>
          @if ($contact->top_customer)
            <span class="rounded-md bg-fp-success/10 px-1.5 py-0.5 font-mono text-xs font-medium text-fp-success ring-1 ring-fp-success/20 ring-inset">Top Customer</span>
          @endif
          @if ($contact->top_mrr)
            <span class="rounded-md bg-fp-accent/10 px-1.5 py-0.5 font-mono text-xs font-medium text-fp-accent ring-1 ring-fp-accent/20 ring-inset">Top MRR</span>
          @endif
          @if ($contact->top_ltv)
            <span class="rounded-md bg-fp-warning/10 px-1.5 py-0.5 font-mono text-xs font-medium text-fp-warning ring-1 ring-fp-warning/20 ring-inset">Top LTV</span>
          @endif
        </div>
        <p class="truncate text-xs/5 text-fp-low">{{ $contact->email }}</p>
      </div>
    </div>

    @if ($contact->tags->isNotEmpty())
      <ul role="list" class="flex flex-wrap gap-2">
        @foreach ($contact->tags as $tag)
          <li class="rounded-md bg-white/5 px-2 py-0.5 text-xs font-medium text-fp-mid ring-1 ring-white/10 ring-inset">{{ $tag->title }}</li>
        @endforeach
      </ul>
    @endif
  </div>

  <dl class="flex shrink-0 items-center gap-x-5 font-mono text-sm text-fp-mid tabular-nums">
    <div class="flex items-center gap-x-1.5">
      <dt class="sr-only">Comments</dt>
      <dd>{{ $contact->comments_count }}</dd>
      <svg class="size-6 shrink-0 text-fp-low" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.068.157 2.148.279 3.238.364.466.037.893.281 1.153.671L12 21l2.652-3.978c.26-.39.687-.634 1.153-.67 1.09-.086 2.17-.208 3.238-.365 1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
      </svg>
    </div>
    <div class="flex items-center gap-x-1.5">
      <dt class="sr-only">Votes</dt>
      <dd>{{ $contact->votes_count }}</dd>
      <svg class="size-6 shrink-0 text-fp-low" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6.633 10.25c.806 0 1.533-.446 2.031-1.08a9.041 9.041 0 0 1 2.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 0 0 .322-1.672V2.75a.75.75 0 0 1 .75-.75 2.25 2.25 0 0 1 2.25 2.25c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.725 1.282m0 0h3.126c1.026 0 1.945.694 2.054 1.715.045.422.068.85.068 1.285a11.95 11.95 0 0 1-2.649 7.521c-.388.482-.987.729-1.605.729H13.48c-.483 0-.964-.078-1.423-.23l-3.114-1.04a4.501 4.501 0 0 0-1.423-.23H5.904m10.598-9.75H14.25M5.904 18.5c.083.205.173.405.27.602.197.4-.078.898-.523.898h-.908c-.889 0-1.713-.518-1.972-1.368a12 12 0 0 1-.521-3.507c0-1.553.295-3.036.831-4.398C3.387 9.953 4.167 9.5 5 9.5h1.053c.472 0 .745.556.5.96a8.958 8.958 0 0 0-1.302 4.665c0 1.194.232 2.333.654 3.375Z" />
      </svg>
    </div>
    <div class="flex items-center gap-x-1.5">
      <dt class="sr-only">Posts</dt>
      <dd>{{ $contact->posts_count }}</dd>
      <svg class="size-6 shrink-0 text-fp-low" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
      </svg>
    </div>
  </dl>
</li>
