<x-admin.layout active="users">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-base font-semibold text-fp-hi">Users</h2>
            <p class="mt-1 text-sm text-fp-mid">Every account on this instance. A deactivated user keeps all data, but cannot sign in or use a token.</p>
        </div>

        <form method="GET" action="/admin/users" role="search" class="flex items-center gap-2">
            <label for="user-search" class="sr-only">Search by name or email</label>
            <input id="user-search" name="search" type="search" value="{{ $search }}" placeholder="Search by name or email"
                   class="block w-full rounded-md border-0 bg-fp-surface py-1.5 text-sm text-fp-hi ring-1 ring-inset ring-white/10 placeholder:text-fp-low focus:ring-2 focus:ring-inset focus:ring-fp-accent sm:w-72">
            <button type="submit" class="rounded-md px-3 py-1.5 text-sm font-medium text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">Search</button>
            @if ($search !== '')
                <a href="/admin/users" class="text-sm text-fp-mid hover:text-fp-hi">Clear</a>
            @endif
        </form>
    </div>

    <div class="mt-6 overflow-x-auto rounded-lg ring-1 ring-white/5">
        <table class="min-w-full divide-y divide-white/5 text-sm">
            <thead class="bg-fp-surface">
                <tr>
                    <th scope="col" class="px-4 py-2.5 text-left font-mono text-xs font-medium uppercase tracking-wide text-fp-low">Name</th>
                    <th scope="col" class="px-4 py-2.5 text-left font-mono text-xs font-medium uppercase tracking-wide text-fp-low">Email</th>
                    <th scope="col" class="px-4 py-2.5 text-left font-mono text-xs font-medium uppercase tracking-wide text-fp-low">Projects</th>
                    <th scope="col" class="px-4 py-2.5 text-left font-mono text-xs font-medium uppercase tracking-wide text-fp-low">Created</th>
                    <th scope="col" class="px-4 py-2.5 text-left font-mono text-xs font-medium uppercase tracking-wide text-fp-low">Status</th>
                    <th scope="col" class="px-4 py-2.5 text-right font-mono text-xs font-medium uppercase tracking-wide text-fp-low"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @forelse ($users as $member)
                    <tr class="hover:bg-fp-surface/60">
                        <td class="whitespace-nowrap px-4 py-3 font-medium text-fp-hi">
                            {{ $member->name ?: '-' }}
                            @if ($member->isOwner())
                                <span class="ml-2 rounded px-1.5 py-0.5 font-mono text-xs uppercase tracking-wide text-fp-accent bg-fp-accent/10">Owner</span>
                            @endif
                            @if ($member->id === Auth::id())
                                <span class="ml-1 rounded px-1.5 py-0.5 font-mono text-xs uppercase tracking-wide text-fp-mid bg-white/5">You</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-fp-mid">{{ $member->email }}</td>
                        <td class="px-4 py-3 text-fp-mid">{{ $member->clients->pluck('name')->join(', ') ?: '-' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-fp-low">
                            <time datetime="{{ $member->created_at?->toIso8601String() }}">{{ $member->created_at?->format('Y-m-d') }}</time>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">
                            @if ($member->isDeactivated())
                                <span class="rounded px-1.5 py-0.5 font-mono text-xs uppercase tracking-wide text-fp-mid bg-white/5" title="Since {{ $member->deactivated_at->format('Y-m-d H:i') }}">Deactivated</span>
                            @else
                                <span class="rounded px-1.5 py-0.5 font-mono text-xs uppercase tracking-wide text-[#3FB68B] bg-[#3FB68B]/10">Active</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            @if ($member->isOwner())
                                <span class="text-xs text-fp-low">-</span>
                            @elseif ($member->isDeactivated())
                                <form method="POST" action="{{ url("/admin/users/{$member->id}/deactivation") }}"
                                      onsubmit="return confirm(@js("Reactivate {$member->email}? This user can sign in and use tokens again."));">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-md px-2.5 py-1 text-sm font-medium text-fp-accent hover:bg-fp-accent/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">Reactivate</button>
                                </form>
                            @else
                                <form method="POST" action="{{ url("/admin/users/{$member->id}/deactivation") }}"
                                      onsubmit="return confirm(@js("Deactivate {$member->email}? This user cannot sign in. The API tokens of this user stop. A project that only deactivated users run stops its public site and widget. The data stays."));">
                                    @csrf
                                    <button type="submit" class="rounded-md px-2.5 py-1 text-sm font-medium text-fp-mid ring-1 ring-inset ring-white/10 hover:text-fp-hi hover:bg-fp-surface focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">Deactivate</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-fp-mid">
                            @if ($search !== '')
                                No user matches "{{ $search }}".
                            @else
                                No users yet.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($users->hasPages())
        <div class="mt-6">
            {{ $users->links() }}
        </div>
    @endif
</x-admin.layout>
