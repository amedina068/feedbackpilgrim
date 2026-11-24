@props(['post'])

@if($post->inProgress())
  <span class="inline-flex items-center rounded-md bg-fp-accent/10 px-2 py-0.5 font-mono text-[11px] uppercase tracking-wide text-fp-accent ring-1 ring-inset ring-fp-accent/20">In Progress</span>
@elseif($post->completed())
  <span class="inline-flex items-center rounded-md bg-fp-success/10 px-2 py-0.5 font-mono text-[11px] uppercase tracking-wide text-fp-success ring-1 ring-inset ring-fp-success/20">Shipped</span>
@endif
