<x-app-layout>
    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-6 rounded-md bg-fp-danger/10 p-4 text-sm text-fp-danger">{{ $errors->first() }}</div>
            @endif

            <div class="border-b border-white/5 pb-4">
                <h1 class="text-3xl font-semibold tracking-tight text-fp-hi">Boards</h1>
                <p class="mt-1 text-sm text-fp-mid">Group feedback into separate boards. Public boards appear on your client site and roadmap.</p>
            </div>

            <div class="mt-6 divide-y divide-white/10">
                @foreach ($boards as $board)
                    <div class="flex flex-wrap items-center gap-3 py-4 first:pt-0">
                        <form action="{{ route('boards.update', $board) }}" method="POST" class="flex flex-1 flex-wrap items-center gap-3">
                            @csrf
                            @method('PATCH')
                            <input type="text" name="name" value="{{ $board->name }}" aria-label="Board name" class="w-48 rounded-md border-0 py-1.5 text-sm text-fp-hi ring-1 ring-inset ring-white/10 focus:ring-2 focus:ring-fp-accent" required>
                            <span class="inline-flex items-center rounded-md bg-fp-surface px-2 py-1 text-xs font-medium text-fp-mid ring-1 ring-inset ring-white/10">{{ $board->posts_count }} {{ Str::plural('post', $board->posts_count) }}</span>
                            <label class="flex items-center gap-2 text-sm text-fp-mid">
                                <input type="hidden" name="is_public" value="0">
                                <input type="checkbox" name="is_public" value="1" @checked($board->is_public) class="h-4 w-4 rounded-sm border-white/10 text-fp-accent focus:ring-fp-accent">
                                Public
                            </label>
                            <button type="submit" class="rounded-md bg-fp-surface px-2.5 py-1.5 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface">Save</button>
                        </form>
                        <a href="{{ route('board.index', ['board' => $board->id]) }}" class="text-sm font-medium text-fp-accent hover:text-fp-accent">Open board</a>
                        @if ($boards->count() > 1)
                            <form action="{{ route('boards.destroy', $board) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm font-medium text-fp-danger hover:text-fp-danger" onclick="return confirm('Delete this board? Its posts move to another board.')">Delete</button>
                            </form>
                        @endif
                    </div>
                @endforeach

                <form action="{{ route('boards.store') }}" method="POST" class="flex flex-wrap items-center gap-3 pt-4">
                    @csrf
                    <input type="text" name="name" placeholder="New board" aria-label="New board name" class="w-48 rounded-md border-0 py-1.5 text-sm text-fp-hi ring-1 ring-inset ring-white/10 placeholder:text-fp-low focus:ring-2 focus:ring-fp-accent" required>
                    <label class="flex items-center gap-2 text-sm text-fp-mid">
                        <input type="hidden" name="is_public" value="0">
                        <input type="checkbox" name="is_public" value="1" checked class="h-4 w-4 rounded-sm border-white/10 text-fp-accent focus:ring-fp-accent">
                        Public
                    </label>
                    <button type="submit" class="rounded-md bg-fp-accent px-2.5 py-1.5 text-sm font-semibold text-white hover:bg-fp-accent">Add board</button>
                </form>
            </div>

            @isset($client)
                <div class="mt-10 rounded-xl border border-white/5 bg-fp-surface p-4 sm:p-5">
                    <h2 class="text-sm font-semibold text-fp-hi">Embed your roadmap</h2>
                    <p class="mt-1 text-sm text-fp-mid">Drop this snippet into your site to embed the public roadmap (public boards only).</p>
                    <pre class="mt-3 overflow-x-auto rounded-md bg-fp-surface p-3 text-xs text-fp-mid ring-1 ring-inset ring-white/10"><code>&lt;iframe src="{{ $client->roadmapIframeUrl }}" width="100%" height="600" style="border:0" title="Roadmap"&gt;&lt;/iframe&gt;</code></pre>
                </div>
            @endisset
        </div>
    </div>
</x-app-layout>
