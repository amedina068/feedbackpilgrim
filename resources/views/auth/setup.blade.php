<x-auth-layout>
    <x-slot:title>Set up this instance</x-slot:title>

    <p class="text-sm text-fp-mid">
        Create the owner account and the first project. The owner manages the users and the settings of this instance.
    </p>

    <form method="POST" action="/setup" class="mt-8 space-y-5">
        @csrf

        <div>
            <x-input-label for="token" value="Setup token" />
            <x-text-input id="token" class="mt-1 block w-full font-mono" type="text" name="token" :value="old('token', $token)" required autocomplete="off" spellcheck="false" aria-describedby="token-help" />
            <x-input-error :messages="$errors->get('token')" class="mt-2" />
            <div id="token-help" class="mt-2 rounded-md bg-fp-surface p-3 text-xs leading-5 text-fp-mid ring-1 ring-white/5">
                The token proves that you operate this server. The setup URL contains it. Find the URL here:
                <ul class="mt-1 list-inside list-disc">
                    <li>the log of the web container: <code class="font-mono text-fp-hi">docker compose logs web</code></li>
                    <li>the output of <code class="font-mono text-fp-hi">php artisan fp:setup-url</code></li>
                </ul>
            </div>
        </div>

        <div>
            <x-input-label for="name" value="Your name" />
            <x-text-input id="name" class="mt-1 block w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Password" />
            <x-text-input id="password" class="mt-1 block w-full" type="password" name="password" required autocomplete="new-password" aria-describedby="password-help" />
            <p id="password-help" class="mt-1 text-xs text-fp-low">8 characters or more.</p>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Confirm password" />
            <x-text-input id="password_confirmation" class="mt-1 block w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
        </div>

        <div class="border-t border-white/5 pt-5">
            <x-input-label for="client_name" value="Project name" />
            <x-text-input id="client_name" class="mt-1 block w-full" type="text" name="client_name" :value="old('client_name')" required autocomplete="organization" />
            <x-input-error :messages="$errors->get('client_name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="subdomain" value="Project subdomain" />
            <div class="mt-1 flex items-center gap-x-1 rounded-md bg-fp-surface pr-3 font-mono text-sm outline-1 -outline-offset-1 outline-white/10 has-[input:focus]:outline-2 has-[input:focus]:-outline-offset-2 has-[input:focus]:outline-fp-accent">
                <input type="text" name="subdomain" id="subdomain" value="{{ old('subdomain') }}" required autocomplete="off" spellcheck="false" aria-describedby="subdomain-help" class="min-w-0 grow border-0 bg-transparent py-1.5 pl-3 text-fp-hi placeholder:text-fp-low focus:ring-0" placeholder="my-project">
                <span class="shrink-0 text-fp-low select-none">.{{ config('app.domain') }}</span>
            </div>
            <p id="subdomain-help" class="mt-1 text-xs text-fp-low">The public feedback site of the project. Lowercase letters, numbers and hyphens.</p>
            <x-input-error :messages="$errors->get('subdomain')" class="mt-2" />
        </div>

        <x-primary-button class="w-full justify-center">
            Create the owner account
        </x-primary-button>
    </form>
</x-auth-layout>
