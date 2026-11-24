<x-settings.layout active="integrations">
    <div class="mx-auto max-w-3xl">
        @if ($errors->any())
            <div class="mb-6 rounded-md bg-fp-danger/10 p-4 text-sm text-fp-danger">{{ $errors->first() }}</div>
        @endif

        <div class="border-b border-white/10 pb-5">
            <h2 class="text-xl font-semibold tracking-tight text-fp-hi">Integrations</h2>
            <p class="mt-1 text-sm text-fp-mid">Connect Feedback Pilgrim to the tools your team already uses.</p>
        </div>

        <section class="mt-6 rounded-xl border border-white/5 bg-fp-surface p-4 sm:p-5">
            <div class="flex items-start gap-3">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-fp-surface ring-1 ring-inset ring-white/10">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="size-5 text-fp-low" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold text-fp-hi">Slack</h3>
                    <p class="mt-0.5 text-sm text-fp-mid">Post a message to a Slack channel when feedback is submitted or a post changes status.</p>
                </div>
            </div>

            <form action="{{ route('settings.integrations.update') }}" method="POST" class="mt-5 space-y-4">
                @csrf
                @method('PATCH')
                <div>
                    <label for="slack_webhook_url" class="block text-sm font-medium text-fp-mid">Incoming webhook URL</label>
                    <input id="slack_webhook_url" type="url" name="slack_webhook_url" value="{{ old('slack_webhook_url', $slackWebhookUrl) }}"
                        placeholder="https://hooks.slack.com/services/..."
                        class="mt-1.5 block w-full rounded-md border-0 py-1.5 text-sm text-fp-hi ring-1 ring-inset ring-white/10 placeholder:text-fp-low focus:ring-2 focus:ring-inset focus:ring-fp-accent">
                    <p class="mt-1.5 text-xs text-fp-low">Create one in Slack under <span class="font-medium">Apps &rarr; Incoming Webhooks</span>. Leave blank to disable.</p>
                </div>
                <button type="submit" class="rounded-md bg-fp-accent px-3 py-2 text-sm font-semibold text-white hover:bg-fp-accent focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">Save Slack settings</button>
            </form>
        </section>
    </div>
</x-settings.layout>
