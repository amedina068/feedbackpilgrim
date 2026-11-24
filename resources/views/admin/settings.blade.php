@php
    $modes = [
        [
            'value' => \App\Support\Registration::OPEN,
            'title' => 'Open',
            'description' => 'Anyone can sign up on the registration page and create projects.',
        ],
        [
            'value' => \App\Support\Registration::CLOSED,
            'title' => 'Closed',
            'description' => 'Nobody can sign up. The registration page returns 404.',
        ],
    ];
@endphp

<x-admin.layout active="settings">
    <form method="POST" action="/admin/settings" class="max-w-2xl">
        @csrf
        @method('PUT')

        <fieldset>
            <legend class="text-base font-semibold text-fp-hi">Registration</legend>
            <p class="mt-1 text-sm text-fp-mid">
                Who can create an account. Without a stored choice, <code class="font-mono text-xs text-fp-hi">REGISTRATION_ENABLED</code> gives the mode. It gives <span class="font-mono text-xs text-fp-hi">{{ $defaultMode }}</span> now.
            </p>

            <div class="mt-4 space-y-3">
                @foreach ($modes as $mode)
                    <label class="flex cursor-pointer gap-3 rounded-lg p-4 ring-1 ring-inset ring-white/10 hover:bg-fp-surface has-checked:bg-fp-accent/5 has-checked:ring-fp-accent/30">
                        <input type="radio" name="registration_mode" value="{{ $mode['value'] }}" @checked($registrationMode === $mode['value'])
                               class="mt-0.5 border-white/20 bg-transparent text-fp-accent focus:ring-fp-accent">
                        <span>
                            <span class="block text-sm font-medium text-fp-hi">{{ $mode['title'] }}</span>
                            <span class="mt-0.5 block text-sm text-fp-mid">{{ $mode['description'] }}</span>
                        </span>
                    </label>
                @endforeach
            </div>

            <x-input-error :messages="$errors->get('registration_mode')" class="mt-2" />
        </fieldset>

        <div class="mt-6">
            <x-primary-button>Save settings</x-primary-button>
        </div>
    </form>
</x-admin.layout>
