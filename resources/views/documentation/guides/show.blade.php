<x-docs.shell title="{{ $guide['title'] }}" active="guides">
    <div class="mx-auto flex max-w-(--breakpoint-2xl)">
        {{-- Left: guide navigation --}}
        <aside class="sticky top-15 hidden h-[calc(100vh-3.75rem)] w-64 shrink-0 overflow-y-auto border-r border-gray-200 px-4 py-8 lg:block">
            <nav class="space-y-7 text-sm" aria-label="Guides navigation">
                @foreach ($groupedGuides as $category => $guides)
                    <div>
                        <p class="px-2 text-[11px] font-semibold uppercase tracking-wider text-gray-400">{{ $category }}</p>
                        <ul class="mt-2 space-y-0.5">
                            @foreach ($guides as $item)
                                @php($current = $item['slug'] === $guide['slug'])
                                <li>
                                    <a href="{{ route('documentation.guide', $item['slug']) }}"
                                       @if ($current) aria-current="page" @endif
                                       class="block rounded-md px-2 py-1.5 {{ $current ? 'bg-orange-50 font-medium text-orange-600' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                                        {{ $item['title'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>
        </aside>

        {{-- Center: guide body --}}
        <main class="min-w-0 flex-1 px-4 py-10 sm:px-8 lg:px-12">
            <nav class="mb-6 text-sm" aria-label="Breadcrumb">
                <a href="{{ route('documentation') }}" class="text-gray-500 hover:text-gray-900">Guides</a>
                <span class="px-1.5 text-gray-300">/</span>
                <span class="text-gray-900">{{ $guide['title'] }}</span>
            </nav>

            <article class="prose prose-gray max-w-3xl prose-headings:font-bold prose-headings:tracking-tight prose-a:text-orange-600 prose-a:no-underline prose-a:hover:text-orange-500 prose-code:rounded-sm prose-code:bg-gray-100 prose-code:px-1 prose-code:py-0.5 prose-code:font-normal prose-code:before:content-none prose-code:after:content-none prose-pre:bg-gray-900">
                {!! $guide['html'] !!}
            </article>

            <footer class="mt-14 max-w-3xl border-t border-gray-200 pt-8">
                <a href="{{ route('documentation') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-orange-600 hover:text-orange-500">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 5l-5 5 5 5" />
                    </svg>
                    All guides
                </a>
            </footer>
        </main>
    </div>
</x-docs.shell>
