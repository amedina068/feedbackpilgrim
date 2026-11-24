<x-app-layout>
    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-6 rounded-md bg-fp-danger/10 p-4 text-sm text-fp-danger">{{ $errors->first() }}</div>
            @endif

            <div class="border-b border-white/5 pb-4">
                <h1 class="text-3xl font-semibold tracking-tight text-fp-hi">Votes</h1>
                <p class="mt-1 text-sm text-fp-mid">{{ $votes->count() }} {{ Str::plural('vote', $votes->count()) }} on &ldquo;{{ $post->title }}&rdquo;.</p>
            </div>

            <form action="{{ route('posts-vote.store', $post->id) }}" method="POST" class="mt-6 flex flex-wrap items-center gap-3">
                @csrf
                <label for="contact_id" class="sr-only">Contact</label>
                <select id="contact_id" name="contact_id" class="w-64 rounded-md border-0 py-1.5 pl-3 pr-8 text-sm text-fp-hi ring-1 ring-inset ring-white/10 focus:ring-2 focus:ring-fp-accent" required>
                    <option value="">Add a vote on behalf of…</option>
                    @foreach ($contacts as $contact)
                        <option value="{{ $contact->id }}">{{ $contact->name ?? $contact->email }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-md bg-fp-accent px-2.5 py-1.5 text-sm font-semibold text-white hover:bg-fp-accent">Add vote</button>
            </form>

            <ul role="list" class="mt-6 divide-y divide-white/10">
                @forelse ($votes as $vote)
                    <li class="flex items-center gap-3 py-3">
                        <a href="/contacts/{{ $vote->contact_id }}" class="text-sm font-medium text-fp-hi hover:text-fp-accent">{{ $vote->contact->name ?? 'Unknown' }}</a>
                        <span class="ml-auto text-xs text-fp-low">{{ $vote->created_at->diffForHumans() }}</span>
                    </li>
                @empty
                    <li class="py-6 text-center text-sm text-fp-low">No votes yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</x-app-layout>
