<x-settings.layout active="webhooks">
    <div class="mx-auto max-w-3xl">
        @if ($errors->any())
            <div class="mb-6 rounded-md bg-fp-danger/10 p-4 text-sm text-fp-danger">{{ $errors->first() }}</div>
        @endif

        <div class="border-b border-white/10 pb-5">
            <h2 class="text-xl font-semibold tracking-tight text-fp-hi">Webhooks</h2>
            <p class="mt-1 text-sm text-fp-mid">POST events to your endpoints. Each request is signed with
                <code class="rounded-sm bg-fp-elevated px-1 py-0.5 text-xs text-fp-mid">X-FeedbackPilgrim-Signature-V2</code> &mdash; an HMAC-SHA256 of
                <code class="rounded-sm bg-fp-elevated px-1 py-0.5 text-xs text-fp-mid">timestamp.body</code> with the endpoint secret. The timestamp is in
                <code class="rounded-sm bg-fp-elevated px-1 py-0.5 text-xs text-fp-mid">X-FeedbackPilgrim-Timestamp</code>.</p>
        </div>

        <div class="mt-6 space-y-4">
            @forelse ($webhooks as $webhook)
                <div class="rounded-xl border border-white/5 bg-fp-surface p-4 sm:p-5">
                    <form action="/webhooks/{{ $webhook->id }}" method="POST" class="space-y-4">
                        @csrf
                        @method('PATCH')

                        <div class="flex items-center justify-between gap-3">
                            @if ($webhook->is_active)
                                <span class="inline-flex items-center gap-x-1.5 rounded-md bg-fp-success/10 px-2 py-1 text-xs font-medium text-fp-success ring-1 ring-inset ring-fp-success/20">
                                    <svg class="size-1.5 fill-fp-success" viewBox="0 0 6 6" aria-hidden="true"><circle cx="3" cy="3" r="3" /></svg>
                                    Active
                                </span>
                            @else
                                <span class="inline-flex items-center gap-x-1.5 rounded-md bg-fp-surface px-2 py-1 text-xs font-medium text-fp-mid ring-1 ring-inset ring-white/10">
                                    <svg class="size-1.5 fill-fp-low" viewBox="0 0 6 6" aria-hidden="true"><circle cx="3" cy="3" r="3" /></svg>
                                    Paused
                                </span>
                            @endif
                        </div>

                        <div>
                            <label for="url-{{ $webhook->id }}" class="block text-sm font-medium text-fp-mid">Endpoint URL</label>
                            <input id="url-{{ $webhook->id }}" type="url" name="url" value="{{ $webhook->url }}" class="mt-1.5 block w-full rounded-md border-0 py-1.5 text-sm text-fp-hi ring-1 ring-inset ring-white/10 focus:ring-2 focus:ring-inset focus:ring-fp-accent" required>
                        </div>

                        <fieldset>
                            <legend class="text-sm font-medium text-fp-mid">Events</legend>
                            <div class="mt-2 flex flex-wrap gap-x-5 gap-y-2">
                                @foreach (\App\Models\Webhook::EVENTS as $event)
                                    <label class="flex items-center gap-2 text-sm text-fp-mid">
                                        <input type="checkbox" name="events[]" value="{{ $event }}" @checked($webhook->subscribesTo($event)) class="h-4 w-4 rounded-sm border-white/10 text-fp-accent focus:ring-fp-accent">
                                        <code class="text-xs text-fp-mid">{{ $event }}</code>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <div class="flex items-center justify-between gap-3 border-t border-white/10 pt-4">
                            <label class="flex items-center gap-2 text-sm text-fp-mid">
                                <input type="checkbox" name="is_active" value="1" @checked($webhook->is_active) class="h-4 w-4 rounded-sm border-white/10 text-fp-accent focus:ring-fp-accent">
                                Active
                            </label>
                            <div class="flex items-center gap-3">
                                <button type="submit" class="rounded-md bg-fp-surface px-2.5 py-1.5 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface">Save</button>
                            </div>
                        </div>

                        <div class="rounded-md bg-fp-surface px-3 py-2">
                            <span class="text-xs font-medium text-fp-low">Signing secret</span>
                            <code class="mt-0.5 block break-all text-xs text-fp-mid">{{ $webhook->secret }}</code>
                        </div>
                    </form>

                    @if ($webhook->deliveries->isNotEmpty())
                        <div class="mt-4 border-t border-white/10 pt-3">
                            <span class="text-xs font-medium text-fp-low">Recent deliveries</span>
                            <ul role="list" class="mt-2 divide-y divide-white/10">
                                @foreach ($webhook->deliveries as $delivery)
                                    <li class="flex items-center gap-2 py-1.5 text-xs">
                                        @if ($delivery->success)
                                            <span class="inline-flex items-center gap-x-1 rounded-md bg-fp-success/10 px-1.5 py-0.5 font-medium text-fp-success ring-1 ring-inset ring-fp-success/20">{{ $delivery->status_code }}</span>
                                        @else
                                            <span class="inline-flex items-center gap-x-1 rounded-md bg-fp-danger/10 px-1.5 py-0.5 font-medium text-fp-danger ring-1 ring-inset ring-fp-danger/20">{{ $delivery->status_code ?? 'failed' }}</span>
                                        @endif
                                        <code class="text-fp-mid">{{ $delivery->event }}</code>
                                        <span class="ml-auto text-fp-low">{{ $delivery->created_at->diffForHumans() }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="/webhooks/{{ $webhook->id }}" method="POST" class="mt-3 border-t border-white/10 pt-3">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm font-medium text-fp-danger hover:text-fp-danger" onclick="return confirm('Delete this webhook? Events will stop being delivered to this endpoint.')">Delete endpoint</button>
                    </form>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-white/10 px-6 py-10 text-center">
                    <h3 class="text-sm font-semibold text-fp-hi">No webhooks yet</h3>
                    <p class="mt-1 text-sm text-fp-low">Add an endpoint below to start receiving events.</p>
                </div>
            @endforelse
        </div>

        <form action="/webhooks" method="POST" class="mt-8 space-y-4 border-t border-white/5 pt-6">
            @csrf
            <h3 class="text-sm font-semibold text-fp-hi">Add endpoint</h3>
            <div>
                <label for="new-url" class="block text-sm font-medium text-fp-mid">Endpoint URL</label>
                <input id="new-url" type="url" name="url" placeholder="https://example.com/webhook" class="mt-1.5 block w-full rounded-md border-0 py-1.5 text-sm text-fp-hi ring-1 ring-inset ring-white/10 placeholder:text-fp-low focus:ring-2 focus:ring-inset focus:ring-fp-accent" required>
            </div>
            <fieldset>
                <legend class="text-sm font-medium text-fp-mid">Events</legend>
                <div class="mt-2 flex flex-wrap gap-x-5 gap-y-2">
                    @foreach (\App\Models\Webhook::EVENTS as $event)
                        <label class="flex items-center gap-2 text-sm text-fp-mid">
                            <input type="checkbox" name="events[]" value="{{ $event }}" class="h-4 w-4 rounded-sm border-white/10 text-fp-accent focus:ring-fp-accent">
                            <code class="text-xs text-fp-mid">{{ $event }}</code>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <button type="submit" class="rounded-md bg-fp-accent px-3 py-2 text-sm font-semibold text-white hover:bg-fp-accent focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">Create webhook</button>
        </form>
    </div>
</x-settings.layout>
