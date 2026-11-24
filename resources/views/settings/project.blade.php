<x-settings.layout active="project">
    <div class="mx-auto max-w-3xl">
        <div class="border-b border-white/10 pb-5">
            <h2 class="text-xl font-semibold tracking-tight text-fp-hi">Project</h2>
            <p class="mt-1 text-sm text-fp-mid">The name and the logo show on the public site and in the dashboard.</p>
        </div>

        <form action="{{ route('client.update') }}" method="POST" enctype="multipart/form-data" class="mt-6 space-y-5 rounded-xl border border-white/5 bg-fp-surface p-4 sm:p-5">
            @csrf
            @method('PATCH')

            <div>
                <label for="name" class="block text-sm font-medium text-fp-mid">Name</label>
                <input id="name" type="text" name="name" value="{{ old('name', $client->name) }}" required maxlength="255"
                    class="mt-1.5 block w-full rounded-md border-0 bg-fp-surface py-1.5 text-sm text-fp-hi ring-1 ring-inset ring-white/10 focus:ring-2 focus:ring-inset focus:ring-fp-accent">
                @error('name')
                    <p class="mt-1.5 text-sm font-medium text-fp-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="domain" class="block text-sm font-medium text-fp-mid">Website</label>
                <input id="domain" type="text" name="domain" value="{{ old('domain', $website) }}" maxlength="255" placeholder="example.com" aria-describedby="domain-hint"
                    class="mt-1.5 block w-full rounded-md border-0 bg-fp-surface py-1.5 text-sm text-fp-hi ring-1 ring-inset ring-white/10 placeholder:text-fp-low focus:ring-2 focus:ring-inset focus:ring-fp-accent">
                <p id="domain-hint" class="mt-1.5 text-xs text-fp-low">The public site shows a "Back to" link to this address. Leave it empty to hide the link.</p>
                @error('domain')
                    <p class="mt-1.5 text-sm font-medium text-fp-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="logo" class="block text-sm font-medium text-fp-mid">Logo</label>
                <div class="mt-1.5 flex items-center gap-4">
                    @if ($client->logo)
                        <img src="{{ $client->logo }}" alt="Current logo of {{ $client->name }}" class="h-12 w-auto rounded-md bg-fp-elevated p-1">
                    @endif
                    <input id="logo" type="file" name="logo" accept="image/jpeg,image/png,image/gif,image/webp" aria-describedby="logo-hint"
                        class="block w-full text-sm text-fp-mid file:mr-3 file:rounded-md file:border-0 file:bg-fp-accent/10 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-fp-accent hover:file:bg-fp-accent/20">
                </div>
                <p id="logo-hint" class="mt-1.5 text-xs text-fp-low">JPG, PNG, GIF or WebP, 1 MB maximum.</p>
                @error('logo')
                    <p class="mt-1.5 text-sm font-medium text-fp-danger">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="rounded-md bg-fp-accent px-3 py-2 text-sm font-semibold text-white hover:bg-fp-accent focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">Save project</button>
        </form>
    </div>
</x-settings.layout>
