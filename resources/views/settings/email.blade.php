@php
    $state = $sendingDomain?->displayState();
    $chip = [
        'pending' => ['Pending', 'bg-white/5 text-fp-mid ring-white/10'],
        'verified' => ['Verified', 'bg-fp-success/10 text-fp-success ring-fp-success/20'],
        'lost' => ['Verification lost', 'bg-fp-warning/10 text-fp-warning ring-fp-warning/20'],
        'failed' => ['Failed', 'bg-fp-danger/10 text-fp-danger ring-fp-danger/20'],
    ][$state] ?? null;
    $primaryButton = 'rounded-md bg-fp-accent px-3 py-1.5 text-sm font-semibold text-white hover:bg-fp-accent-hover disabled:cursor-not-allowed disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent';
@endphp

<x-settings.layout active="email">
    <div class="mx-auto max-w-3xl">
        @if (session('success'))
            <div class="mb-6 rounded-md bg-fp-success/10 p-4 text-sm text-fp-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-6 rounded-md bg-fp-danger/10 p-4 text-sm text-fp-danger">{{ $errors->first() }}</div>
        @endif

        <div class="border-b border-white/10 pb-5">
            <h2 class="text-xl font-semibold tracking-tight text-fp-hi">Email</h2>
            <p class="mt-1 text-sm text-fp-mid">Send emails to your customers from your own domain.</p>
        </div>

        @if ($sendingDomain)
            <section class="mt-6 rounded-xl border border-white/5 bg-fp-surface p-4 sm:p-5">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                    <h3 v-pre class="min-w-0 break-all font-mono text-sm font-semibold text-fp-hi">{{ $sendingDomain->domain }}</h3>
                    <span class="rounded-md px-2 py-0.5 font-mono text-xs uppercase tracking-wide ring-1 ring-inset {{ $chip[1] }}">{{ $chip[0] }}</span>
                    @if ($sendingDomain->last_checked_at)
                        <p class="font-mono text-xs text-fp-low sm:ml-auto">Last checked {{ $sendingDomain->last_checked_at->diffForHumans() }}</p>
                    @endif
                </div>

                <div class="mt-4 space-y-4">
                    @if ($state === 'pending')
                        <p class="text-sm text-fp-mid">Add these records at your DNS provider. Emails will come from <span v-pre class="font-mono text-fp-hi">{{ $sendingDomain->sender_local_part }}&#64;{{ $sendingDomain->domain }}</span> after SES verifies the domain.</p>
                        @include('settings.partials.sending-domain-dns-records')
                    @elseif ($state === 'lost')
                        <p class="rounded-md bg-fp-warning/10 p-3 text-sm text-fp-warning ring-1 ring-inset ring-fp-warning/20">This domain was verified, but SES can no longer confirm it. Emails are sending from {{ $fallbackAddress }} until this is fixed.</p>
                        @include('settings.partials.sending-domain-dns-records')
                    @elseif ($state === 'verified')
                        <p class="text-sm text-fp-mid">Emails to your customers go out from <span v-pre class="break-all font-mono text-fp-hi">{{ $mailSender->name }} &lt;{{ $mailSender->address }}&gt;</span>.</p>
                        <details class="group">
                            <summary class="w-fit cursor-pointer rounded-md text-sm font-medium text-fp-mid hover:text-fp-hi focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">
                                <span class="group-open:hidden">Show DNS records</span>
                                <span class="hidden group-open:inline">Hide DNS records</span>
                            </summary>
                            <div class="mt-4">
                                @include('settings.partials.sending-domain-dns-records')
                            </div>
                        </details>
                    @else
                        <p class="text-sm text-fp-hi">SES looked for the DKIM records for 72 hours and did not find them. Emails are sending from {{ $fallbackAddress }} until this is fixed.</p>
                        <p class="text-sm text-fp-mid">Try again creates new records. Replace the old CNAME records with the new ones.</p>
                    @endif
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-white/5 pt-4">
                    @if (in_array($state, ['pending', 'lost'], true))
                        <form action="{{ route('settings.email.check') }}" method="POST"
                            onsubmit="const button = this.querySelector('button'); button.disabled = true; button.textContent = 'Checking…';">
                            @csrf
                            <button type="submit" class="{{ $primaryButton }}">Check now</button>
                        </form>
                    @endif
                    @if ($state === 'failed')
                        <form action="{{ route('settings.email.retry') }}" method="POST">
                            @csrf
                            <button type="submit" class="{{ $primaryButton }}">Try again</button>
                        </form>
                    @endif
                    <form action="{{ route('settings.email.destroy') }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit" onclick="return confirm({{ \Illuminate\Support\Js::from("Remove this domain? Emails will go out from {$fallbackAddress} until you connect a new one.") }})"
                            class="rounded-md px-3 py-1.5 text-sm font-semibold text-fp-danger ring-1 ring-inset ring-fp-danger/30 hover:bg-fp-danger/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-danger">Remove domain</button>
                    </form>
                </div>
            </section>
        @else
            <section class="mt-6 rounded-xl border border-white/5 bg-fp-surface p-4 sm:p-5">
                <h3 class="text-sm font-semibold text-fp-hi">Connect a domain</h3>
                <p class="mt-0.5 text-sm text-fp-mid">Until you connect a domain, emails come from {{ $fallbackAddress }} with your name. Use a sender address that someone reads, because replies go there.</p>

                {{-- The oninput handler below builds the From preview in JS. Keep it in step with the initial render in the <output> element. --}}
                <form action="{{ route('settings.email.store') }}" method="POST" class="mt-5 space-y-4"
                    oninput="const preview = this.querySelector('output'); preview.value = preview.dataset.name + ' <' + (this.elements.sender_local_part.value.trim().toLowerCase() || 'feedback') + '@' + (this.elements.domain.value.trim().toLowerCase() || 'example.com') + '>';">
                    @csrf
                    <div>
                        <label for="domain" class="block text-sm font-medium text-fp-mid">Domain</label>
                        <input id="domain" type="text" name="domain" value="{{ old('domain') }}" required
                            placeholder="example.com" autocomplete="off" spellcheck="false"
                            class="mt-1.5 block w-full rounded-md border-0 bg-fp-base py-1.5 font-mono text-sm text-fp-hi ring-1 ring-inset ring-white/10 placeholder:text-fp-low focus:ring-2 focus:ring-inset focus:ring-fp-accent max-sm:text-base">
                    </div>
                    <div>
                        <label for="sender_local_part" class="block text-sm font-medium text-fp-mid">Sender address</label>
                        <input id="sender_local_part" type="text" name="sender_local_part" value="{{ old('sender_local_part', 'feedback') }}" required
                            autocomplete="off" spellcheck="false" aria-describedby="sender_local_part_help"
                            class="mt-1.5 block w-full rounded-md border-0 bg-fp-base py-1.5 font-mono text-sm text-fp-hi ring-1 ring-inset ring-white/10 placeholder:text-fp-low focus:ring-2 focus:ring-inset focus:ring-fp-accent max-sm:text-base">
                        <p id="sender_local_part_help" class="mt-1.5 text-xs text-fp-low">The part before the &#64;.</p>
                    </div>
                    {{-- The initial From preview. Keep this formula in step with the oninput handler on the form. --}}
                    <p class="text-sm text-fp-mid">From: <output v-pre for="domain sender_local_part" data-name="{{ $mailSender->name }}" class="break-all font-mono text-fp-hi">{{ $mailSender->name }} &lt;{{ strtolower(old('sender_local_part') ?: 'feedback').'@'.strtolower(old('domain') ?: 'example.com') }}&gt;</output></p>
                    <button type="submit" class="rounded-md bg-fp-accent px-3 py-1.5 text-sm font-semibold text-white hover:bg-fp-accent-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">Connect domain</button>
                </form>
            </section>
        @endif
    </div>
</x-settings.layout>
