<x-settings.layout active="widget">
    @php
        $scriptSnippet = '<script src="'.url('/pilgrim.js').'" data-client="'.$client->publishable_key.'"></script>';
        $hashSnippet = '$userHash = hash_hmac(\'sha256\', strtolower(trim($email)), $identitySecret);';
        $initSnippet = '<script>
  pilgrim({
    contact: {
      email: "jane@example.com", // required
      name: "Jane Doe", // optional
      mrr: 49, // optional, monthly revenue of this customer
      ltv: 1200, // optional, lifetime value of this customer
    },
    userHash: "HASH_FROM_YOUR_SERVER",
    releaseNotes: { show: true, open: true }, // optional
    testimonials: true, // optional
  })
</script>';
        $linkSnippet = '<a href="#" data-pilgrim-link>Feedback</a>
<a href="#" data-pilgrim-release-notes>What is new</a>
<a href="#" data-pilgrim-testimonials>Leave a review</a>';
    @endphp

    <div class="mx-auto max-w-3xl">
        @if ($errors->any())
            <div role="alert" class="mb-6 rounded-lg bg-fp-danger/10 px-4 py-3 text-sm text-fp-danger ring-1 ring-fp-danger/20">{{ $errors->first() }}</div>
        @endif

        <div class="border-b border-white/10 pb-5">
            <h2 class="text-xl font-semibold tracking-tight text-fp-hi">{{ __('Embed widget') }}</h2>
            <p class="mt-1 text-sm text-fp-mid">{{ __('Add pilgrim.js to your app so that signed-in users reach your board without a second login.') }}</p>
        </div>

        <section aria-labelledby="publishable-key-heading" class="mt-6 rounded-xl border border-white/5 bg-fp-surface p-4 sm:p-5">
            <h3 id="publishable-key-heading" class="text-sm font-semibold text-fp-hi">{{ __('Publishable key') }}</h3>
            <p class="mt-0.5 text-sm text-fp-mid">{{ __('This key identifies your project. It is public and safe to put in your HTML. It gives no API access.') }}</p>

            <div class="mt-3 flex items-stretch gap-x-2">
                <code class="min-w-0 flex-1 break-all select-all rounded-lg bg-fp-base px-3 py-2 font-mono text-xs text-fp-hi ring-1 ring-white/10" v-pre>{{ $client->publishable_key }}</code>
                <copy-button text="{{ $client->publishable_key }}" label="Copy key"></copy-button>
            </div>

            <p class="mt-4 text-sm text-fp-mid">{{ __('Add this snippet to the head of your app.') }}</p>
            <pre class="mt-2 overflow-x-auto rounded-lg bg-fp-base p-3 font-mono text-xs text-fp-hi ring-1 ring-white/10" v-pre><code>{{ $scriptSnippet }}</code></pre>
        </section>

        <section aria-labelledby="identity-heading" class="mt-6 rounded-xl border border-white/5 bg-fp-surface p-4 sm:p-5">
            <h3 id="identity-heading" class="text-sm font-semibold text-fp-hi">{{ __('Identity verification') }}</h3>
            <p class="mt-0.5 text-sm text-fp-mid">
                {{ __('Your server signs the email of the signed-in user with the identity secret and sends the result as userHash. Feedback Pilgrim refuses a wrong hash. Keep the secret on your server. Never put it in HTML or JavaScript.') }}
            </p>

            <p id="identity_secret_label" class="mt-4 block text-sm font-medium text-fp-mid">{{ __('Identity secret') }}</p>
            @if ($identitySecret = $client->readableIdentitySecret())
                <div class="mt-1.5 flex items-stretch gap-x-2">
                    <code id="identity_secret" aria-labelledby="identity_secret_label" class="min-w-0 flex-1 break-all select-all rounded-lg bg-fp-base px-3 py-2 font-mono text-xs text-fp-hi ring-1 ring-white/10" v-pre>{{ $identitySecret }}</code>
                    <copy-button text="{{ $identitySecret }}" label="Copy secret"></copy-button>
                </div>
            @else
                <p id="identity_secret" class="mt-1.5 text-sm text-fp-danger">
                    {{ __('The app cannot read the identity secret. The APP_KEY of the server changed after the secret was saved. Regenerate the secret, then put the new secret in your app.') }}
                </p>
            @endif

            <form method="post" action="{{ route('settings.widget.identity-secret') }}" class="mt-3"
                  onsubmit="return confirm('{{ __('Regenerate the identity secret? Signed-in users fail verification until your app uses the new secret.') }}')">
                @csrf
                <button type="submit" class="rounded-lg px-2.5 py-1.5 text-sm font-medium text-fp-danger hover:bg-fp-danger/10">
                    {{ __('Regenerate secret') }}
                </button>
            </form>

            <p class="mt-4 text-sm text-fp-mid">{{ __('Make the hash on your server. Use the lowercase, trimmed email.') }}</p>
            <pre class="mt-2 overflow-x-auto rounded-lg bg-fp-base p-3 font-mono text-xs text-fp-hi ring-1 ring-white/10" v-pre><code>{{ $hashSnippet }}</code></pre>

            <p class="mt-4 text-sm text-fp-mid">{{ __('Then call pilgrim() at the bottom of your body tag.') }}</p>
            <pre class="mt-2 overflow-x-auto rounded-lg bg-fp-base p-3 font-mono text-xs text-fp-hi ring-1 ring-white/10" v-pre><code>{{ $initSnippet }}</code></pre>
            <p class="mt-2 text-sm text-fp-mid">{{ __('mrr and ltv show next to the posts of this customer. Leave out releaseNotes and testimonials to turn them off.') }}</p>

            <p class="mt-4 text-sm text-fp-mid">{{ __('Last, add an attribute to the links in your app. Without these links, the widget shows nothing.') }}</p>
            <pre class="mt-2 overflow-x-auto rounded-lg bg-fp-base p-3 font-mono text-xs text-fp-hi ring-1 ring-white/10" v-pre><code>{{ $linkSnippet }}</code></pre>
            <dl class="mt-3 grid grid-cols-1 gap-x-4 gap-y-2 text-sm sm:grid-cols-[auto_1fr]">
                <dt class="font-mono text-xs text-fp-hi sm:pt-0.5">data-pilgrim-link</dt>
                <dd class="text-fp-mid">{{ __('Opens your public board with the user signed in.') }}</dd>
                <dt class="font-mono text-xs text-fp-hi sm:pt-0.5">data-pilgrim-release-notes</dt>
                <dd class="text-fp-mid">{{ __('Opens the release notes. Needs releaseNotes.show.') }}</dd>
                <dt class="font-mono text-xs text-fp-hi sm:pt-0.5">data-pilgrim-testimonials</dt>
                <dd class="text-fp-mid">{{ __('Opens the testimonial form. Needs testimonials: true.') }}</dd>
            </dl>

            <form method="post" action="{{ route('settings.widget.update') }}" class="mt-5">
                @csrf
                @method('patch')

                <div class="flex items-start gap-x-3 rounded-xl p-4 ring-1 ring-white/10">
                    <input id="require_identity_verification" name="require_identity_verification" type="checkbox" value="1"
                               {{ $client->require_identity_verification ? 'checked' : '' }}
                               class="mt-1 size-4 shrink-0 rounded-sm border-white/10 text-fp-accent focus:ring-fp-accent">
                    <div class="min-w-0">
                        <label for="require_identity_verification" class="text-sm font-semibold text-fp-hi">
                            {{ __('Require identity verification') }}
                        </label>
                        <p class="mt-0.5 text-sm text-fp-mid">
                            {{ __('When this is on, the widget refuses a request that has no userHash. Turn it on after your app sends userHash. Without userHash, the widget does not sign in a contact that already exists.') }}
                        </p>
                    </div>
                </div>

                <div class="mt-4">
                    <x-primary-button>{{ __('Save changes') }}</x-primary-button>
                </div>
            </form>
        </section>
    </div>
</x-settings.layout>
