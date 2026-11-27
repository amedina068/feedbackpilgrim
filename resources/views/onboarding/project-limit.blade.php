<x-auth-layout>
    <x-slot:title>You cannot create a project</x-slot:title>

    <p role="status" class="rounded-lg bg-fp-accent/10 px-4 py-3 text-sm text-fp-hi ring-1 ring-fp-accent/20">{{ $message }}</p>

    <div class="mt-6 flex justify-end">
        <a href="/dashboard" class="text-sm text-fp-mid hover:text-fp-hi">Back to the dashboard</a>
    </div>
</x-auth-layout>
