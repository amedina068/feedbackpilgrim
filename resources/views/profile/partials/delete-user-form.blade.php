<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-fp-hi">
            {{ __('Delete Account') }}
        </h2>

        <p class="mt-1 text-sm text-fp-mid">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
        </p>
    </header>

    <modal labelledby="confirm-user-deletion-title" :open-on-load="@js($errors->userDeletion->isNotEmpty())">
        <template v-slot:trigger="{ open }">
            <x-danger-button type="button" v-on:click="open">{{ __('Delete Account') }}</x-danger-button>
        </template>
        <template v-slot:default="{ close }">
            <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
                @csrf
                @method('delete')

                <h2 id="confirm-user-deletion-title" class="text-lg font-medium text-fp-hi">
                    {{ __('Are you sure you want to delete your account?') }}
                </h2>

                <p class="mt-1 text-sm text-fp-mid">
                    {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
                </p>

                <div class="mt-6">
                    <x-input-label for="password" value="{{ __('Password') }}" class="sr-only" />

                    <x-text-input
                        id="password"
                        name="password"
                        type="password"
                        class="mt-1 block w-3/4"
                        placeholder="{{ __('Password') }}"
                    />

                    <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-end">
                    <x-secondary-button v-on:click="close">
                        {{ __('Cancel') }}
                    </x-secondary-button>

                    <x-danger-button class="ms-3">
                        {{ __('Delete Account') }}
                    </x-danger-button>
                </div>
            </form>
        </template>
    </modal>
</section>
