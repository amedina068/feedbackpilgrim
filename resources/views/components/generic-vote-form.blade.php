@php($canVote = $contact && $contact->client_id == $client->id)

@if($canVote)
  <form action="/votes" method="POST" class="shrink-0">
    @csrf
    @if($hasVoted)
      @method('DELETE')
    @endif
    <input type="hidden" name="model_type" value="{{ get_class($model) }}">
    <input type="hidden" name="model_id" value="{{ $model->id }}">
    <button
      type="submit"
      aria-label="{{ $hasVoted ? 'Remove your vote' : 'Upvote' }}"
      aria-pressed="{{ $hasVoted ? 'true' : 'false' }}"
      class="relative flex w-12 flex-col items-center gap-0.5 rounded-md px-2 py-1.5 ring-1 ring-inset focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent {{ $hasVoted ? 'bg-fp-accent/10 text-fp-accent ring-fp-accent/40' : 'text-fp-mid ring-white/10 hover:bg-white/5 hover:text-fp-hi' }}"
    >
      <span class="absolute left-1/2 top-1/2 h-12 w-12 -translate-x-1/2 -translate-y-1/2" aria-hidden="true"></span>
      <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="M10 3a.75.75 0 0 1 .55.24l5.25 5.5a.75.75 0 1 1-1.1 1.02l-3.95-4.14V16a.75.75 0 0 1-1.5 0V5.62L5.3 9.76a.75.75 0 0 1-1.1-1.02l5.25-5.5A.75.75 0 0 1 10 3Z" clip-rule="evenodd" />
      </svg>
      <span class="font-mono text-sm font-medium tabular-nums leading-none">{{ $votesCount }}</span>
    </button>
  </form>
@else
  <div class="flex w-12 shrink-0 flex-col items-center gap-0.5 rounded-md px-2 py-1.5 text-fp-low ring-1 ring-inset ring-white/10" title="Votes">
    <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
      <path fill-rule="evenodd" d="M10 3a.75.75 0 0 1 .55.24l5.25 5.5a.75.75 0 1 1-1.1 1.02l-3.95-4.14V16a.75.75 0 0 1-1.5 0V5.62L5.3 9.76a.75.75 0 0 1-1.1-1.02l5.25-5.5A.75.75 0 0 1 10 3Z" clip-rule="evenodd" />
    </svg>
    <span class="font-mono text-sm font-medium tabular-nums leading-none">{{ $votesCount }}</span>
  </div>
@endif
