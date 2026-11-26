@props(['post'])

@php($label = $post->publicStatusLabel())

@if($label !== null)
  @if($post->publicStatusIsComplete())
    <span class="inline-flex items-center rounded-md bg-fp-success/10 px-2 py-0.5 font-mono text-[11px] uppercase tracking-wide text-fp-success ring-1 ring-inset ring-fp-success/20">{{ $label }}</span>
  @else
    <span class="inline-flex items-center rounded-md bg-fp-accent/10 px-2 py-0.5 font-mono text-[11px] uppercase tracking-wide text-fp-accent ring-1 ring-inset ring-fp-accent/20">{{ $label }}</span>
  @endif
@endif
