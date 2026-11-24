<x-app-layout>
    <div class="py-8">
        <div class="sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-6 rounded-md bg-fp-danger/10 p-4 text-sm text-fp-danger">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="flex items-baseline justify-between border-b border-white/5 px-4 pb-4 sm:px-0">
                <div>
                    <h1 class="text-3xl font-semibold tracking-tight text-fp-hi">{{ $activeBoard->name }}</h1>
                    <p class="mt-1 text-sm text-fp-mid">Drag posts between statuses to update their stage.</p>
                </div>
                <a href="{{ route('boards.index') }}" class="text-sm font-medium text-fp-accent hover:text-fp-accent">Manage boards</a>
            </div>

            @if ($boards->count() > 1)
                <nav class="mt-6 flex flex-wrap gap-2 px-4 sm:px-0" aria-label="Boards">
                    @foreach ($boards as $board)
                        <a href="{{ route('board.index', ['board' => $board->id]) }}"
                           @class([
                               'rounded-full px-3 py-1.5 text-sm font-medium',
                               'bg-fp-accent text-white' => $board->id === $activeBoard->id,
                               'bg-fp-surface text-fp-mid ring-1 ring-inset ring-white/10 hover:bg-fp-surface' => $board->id !== $activeBoard->id,
                           ])>
                            {{ $board->name }}
                        </a>
                    @endforeach
                </nav>
            @endif

            <details class="my-6 rounded-lg border border-white/5 bg-fp-surface px-4 py-3">
                <summary class="cursor-pointer text-sm font-semibold text-fp-hi marker:text-fp-low">Manage statuses</summary>

                <div class="mt-4 divide-y divide-white/10">
                    @foreach ($stages as $stage)
                        <div class="flex flex-wrap items-center gap-2 py-3 first:pt-0">
                            <form action="/stages/{{ $stage->id }}" method="POST" class="flex flex-wrap items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <input type="text" aria-label="Status name" name="name" value="{{ $stage->name }}" class="w-40 rounded-md border-0 py-1.5 text-sm text-fp-hi ring-1 ring-inset ring-white/10 focus:ring-2 focus:ring-fp-accent" required>
                                <input type="color" aria-label="Status color" name="color" value="{{ $stage->color }}" class="h-8 w-10 rounded-md border border-white/10 p-0.5">
                                <select name="type" aria-label="Status type" class="rounded-md border-0 py-1.5 pl-3 pr-8 text-sm text-fp-hi ring-1 ring-inset ring-white/10 focus:ring-2 focus:ring-fp-accent">
                                    @foreach (\App\Models\Stage::TYPES as $type)
                                        <option value="{{ $type }}" @selected($stage->type === $type)>{{ ucfirst($type) }}</option>
                                    @endforeach
                                </select>
                                <label class="flex items-center gap-1.5 text-sm text-fp-mid">
                                    <input type="hidden" name="show_on_roadmap" value="0">
                                    <input type="checkbox" name="show_on_roadmap" value="1" @checked($stage->show_on_roadmap) class="h-4 w-4 rounded-sm border-white/10 text-fp-accent focus:ring-fp-accent">
                                    Roadmap
                                </label>
                                <button type="submit" class="rounded-md bg-fp-surface px-2.5 py-1.5 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface">Save</button>
                            </form>
                            <form action="/stages/{{ $stage->id }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm font-medium text-fp-danger hover:text-fp-danger" onclick="return confirm('Delete this status? Its posts move to your first open status.')">Delete</button>
                            </form>
                        </div>
                    @endforeach

                    <form action="/stages" method="POST" class="flex flex-wrap items-center gap-2 pt-4">
                        @csrf
                        <input type="text" name="name" aria-label="New status name" placeholder="New status" class="w-40 rounded-md border-0 py-1.5 text-sm text-fp-hi ring-1 ring-inset ring-white/10 placeholder:text-fp-low focus:ring-2 focus:ring-fp-accent" required>
                        <input type="color" name="color" aria-label="New status color" value="#94a3b8" class="h-8 w-10 rounded-md border border-white/10 p-0.5">
                        <select name="type" aria-label="New status type" class="rounded-md border-0 py-1.5 pl-3 pr-8 text-sm text-fp-hi ring-1 ring-inset ring-white/10 focus:ring-2 focus:ring-fp-accent">
                            @foreach (\App\Models\Stage::TYPES as $type)
                                <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                            @endforeach
                        </select>
                        <label class="flex items-center gap-1.5 text-sm text-fp-mid">
                            <input type="hidden" name="show_on_roadmap" value="0">
                            <input type="checkbox" name="show_on_roadmap" value="1" class="h-4 w-4 rounded-sm border-white/10 text-fp-accent focus:ring-fp-accent">
                            Roadmap
                        </label>
                        <button type="submit" class="rounded-md bg-fp-accent px-2.5 py-1.5 text-sm font-semibold text-white hover:bg-fp-accent">Add status</button>
                    </form>
                </div>
            </details>

            <stages-index :stages="{{ $stages }}"></stages-index>
        </div>
    </div>
</x-app-layout>
