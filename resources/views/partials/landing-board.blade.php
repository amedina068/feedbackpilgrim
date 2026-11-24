{{-- Static example feedback board used as the landing hero visual. --}}
<div class="rounded-lg border border-fp-line bg-fp-base">
  {{-- Board header --}}
  <div class="flex items-center justify-between border-b border-fp-line px-4 py-3">
    <div class="flex items-center gap-2">
      <span class="font-mono text-xs text-fp-low">acme/feedback</span>
    </div>
    <div class="inline-flex overflow-hidden rounded-md border border-fp-line text-xs font-medium">
      <span class="px-2.5 py-1 text-fp-low">Top voted</span>
      <span class="bg-fp-accent px-2.5 py-1 font-mono text-white">Revenue ↓</span>
    </div>
  </div>

  {{-- Feedback rows --}}
  <ul role="list" class="divide-y divide-white/5">
    {{-- Paying customer, fewer votes — sorted to the top by revenue --}}
    <li class="flex items-start gap-4 px-4 py-4">
      <button type="button" class="relative flex w-12 shrink-0 flex-col items-center gap-1 rounded-md border border-fp-accent/40 bg-fp-accent/10 py-2 text-fp-accent focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">
        <svg class="h-3.5 w-3.5" viewBox="0 0 12 8" fill="none" aria-hidden="true"><path d="M6 1 1 7h10L6 1Z" fill="currentColor"/></svg>
        <span class="font-mono text-sm font-medium">47</span>
        <span class="absolute left-1/2 top-1/2 size-12 -translate-x-1/2 -translate-y-1/2" aria-hidden="true"></span>
      </button>
      <div class="min-w-0 flex-1">
        <h3 class="truncate text-sm font-medium text-fp-hi">Bulk CSV import for contacts</h3>
        <p class="mt-1 line-clamp-1 text-sm text-fp-mid">Onboarding a 9k-contact list by hand is the only thing keeping me on the old tool.</p>
        <div class="mt-2 flex flex-wrap items-center gap-2">
          <span class="rounded-sm bg-fp-accent/10 px-1.5 py-0.5 font-mono text-[11px] uppercase tracking-wide text-fp-accent">Planned</span>
          <span class="font-mono text-xs text-fp-low">april@acme</span>
          <span class="rounded-md bg-fp-accent/10 px-1.5 py-0.5 font-mono text-xs text-fp-accent">$500/mo</span>
        </div>
      </div>
    </li>

    {{-- Another paying customer, in progress --}}
    <li class="flex items-start gap-4 px-4 py-4">
      <button type="button" class="relative flex w-12 shrink-0 flex-col items-center gap-1 rounded-md border border-fp-line py-2 text-fp-low hover:border-fp-line-strong focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">
        <svg class="h-3.5 w-3.5" viewBox="0 0 12 8" fill="none" aria-hidden="true"><path d="M6 1 1 7h10L6 1Z" fill="currentColor"/></svg>
        <span class="font-mono text-sm font-medium">18</span>
        <span class="absolute left-1/2 top-1/2 size-12 -translate-x-1/2 -translate-y-1/2" aria-hidden="true"></span>
      </button>
      <div class="min-w-0 flex-1">
        <h3 class="truncate text-sm font-medium text-fp-hi">Webhook retries with backoff</h3>
        <p class="mt-1 line-clamp-1 text-sm text-fp-mid">A dropped delivery costs us a real customer email. Needs to retry.</p>
        <div class="mt-2 flex flex-wrap items-center gap-2">
          <span class="rounded-sm bg-fp-warning/10 px-1.5 py-0.5 font-mono text-[11px] uppercase tracking-wide text-fp-warning">In progress</span>
          <span class="font-mono text-xs text-fp-low">ops@scale</span>
          <span class="rounded-md bg-fp-accent/10 px-1.5 py-0.5 font-mono text-xs text-fp-accent">$1.2k/mo</span>
        </div>
      </div>
    </li>

    {{-- Loudest free user, most votes, zero revenue — sorted down --}}
    <li class="flex items-start gap-4 px-4 py-4 opacity-70">
      <button type="button" class="relative flex w-12 shrink-0 flex-col items-center gap-1 rounded-md border border-fp-line py-2 text-fp-low hover:border-fp-line-strong focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">
        <svg class="h-3.5 w-3.5" viewBox="0 0 12 8" fill="none" aria-hidden="true"><path d="M6 1 1 7h10L6 1Z" fill="currentColor"/></svg>
        <span class="font-mono text-sm font-medium">312</span>
        <span class="absolute left-1/2 top-1/2 size-12 -translate-x-1/2 -translate-y-1/2" aria-hidden="true"></span>
      </button>
      <div class="min-w-0 flex-1">
        <h3 class="truncate text-sm font-medium text-fp-hi">Add a Slack integration</h3>
        <p class="mt-1 line-clamp-1 text-sm text-fp-mid">Would be cool to get pings in Slack instead of email.</p>
        <div class="mt-2 flex flex-wrap items-center gap-2">
          <span class="rounded-sm bg-white/5 px-1.5 py-0.5 font-mono text-[11px] uppercase tracking-wide text-fp-low">Open</span>
          <span class="font-mono text-xs text-fp-low">freebie_dev</span>
          <span class="rounded-md bg-white/5 px-1.5 py-0.5 font-mono text-xs text-fp-low">$0</span>
        </div>
      </div>
    </li>
  </ul>

  {{-- Footer note --}}
  <div class="border-t border-fp-line px-4 py-3">
    <p class="font-mono text-xs text-fp-low">Sorted by revenue. The 312-vote request is from a free user, so it sits at the bottom.</p>
  </div>
</div>
