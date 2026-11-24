<x-auth-layout>
    <x-slot:title>Create your project</x-slot:title>
        <form method="POST" action="/onboarding/project">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" value="Project name" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="subdomain" value="Project subdomain" />
            <div class="flex items-center rounded-md bg-fp-surface px-3 text-sm outline-1 -outline-offset-1 outline-white/10 has-[input:focus]:outline-2 has-[input:focus]:-outline-offset-2 has-[input:focus]:outline-fp-accent sm:max-w-md">
              <span class="shrink-0 text-fp-low select-none">https://</span>
              <input type="text" name="subdomain" id="subdomain" value="{{ old('subdomain') }}" required aria-describedby="subdomain-hint" class="min-w-0 grow border-0 bg-transparent px-0.5 py-1.5 text-fp-hi placeholder:text-fp-low focus:ring-0" placeholder="my-awesome-project">
              <span class="shrink-0 text-fp-low select-none">.{{ config('app.domain') }}</span>
            </div>
            <p id="subdomain-hint" class="mt-2 text-sm text-fp-low">Use lowercase letters, numbers and hyphens. Your public board will be at this address.</p>
            <x-input-error :messages="$errors->get('subdomain')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (auth()->user()->clients()->exists())
                <a href="/dashboard" class="text-sm text-fp-mid hover:text-fp-hi">Cancel</a>
            @endif
            <x-primary-button class="ms-4">
                Create project
            </x-primary-button>
        </div>
    </form>
</x-auth-layout>
