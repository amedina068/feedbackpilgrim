@props(['comments', 'votes' => null])

<div {{ $attributes->class('flex shrink-0 items-center gap-x-5 font-mono text-sm tabular-nums text-fp-mid') }}>
  @if ($comments->isNotEmpty())
    <ul role="list" aria-label="Commenters" class="flex items-center">
      @foreach ($comments->take(6) as $comment)
        <li class="not-first:-ml-1.5">
          <img class="size-6 shrink-0 rounded-full bg-fp-surface ring-2 ring-fp-base" src="{{ $comment->contact->avatar }}" alt="{{ $comment->contact->name }}">
        </li>
      @endforeach
    </ul>
  @endif

  @isset($votes)
    <p class="flex items-center gap-x-1">
      <svg class="size-4 shrink-0 text-fp-low" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="M8 14a.75.75 0 0 1-.75-.75V4.56L4.03 7.78a.75.75 0 0 1-1.06-1.06l4.5-4.5a.75.75 0 0 1 1.06 0l4.5 4.5a.75.75 0 0 1-1.06 1.06L8.75 4.56v8.69A.75.75 0 0 1 8 14Z" clip-rule="evenodd" />
      </svg>
      <span class="sr-only">Votes:</span>
      {{ $votes }}
    </p>
  @endisset

  <p class="flex items-center gap-x-1">
    <svg class="size-4 shrink-0 text-fp-low" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
      <path fill-rule="evenodd" d="M1 8c0-3.43 3.262-6 7-6s7 2.57 7 6-3.262 6-7 6c-.423 0-.838-.032-1.241-.094-.9.574-1.941.948-3.06 1.06a.75.75 0 0 1-.713-1.14c.232-.378.395-.804.469-1.26C1.979 11.486 1 9.86 1 8Z" clip-rule="evenodd" />
    </svg>
    <span class="sr-only">Comments:</span>
    {{ $comments->count() }}
  </p>
</div>
