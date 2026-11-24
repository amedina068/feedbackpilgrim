<x-docs.shell title="Guides" active="guides">
    <main class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:py-16">
        <header class="border-b border-gray-200 pb-8">
            <p class="font-mono text-xs font-semibold uppercase tracking-wider text-orange-600">Documentation</p>
            <h1 class="mt-2 text-4xl font-bold tracking-tight text-gray-900">Guides</h1>
            <p class="mt-4 max-w-2xl text-lg leading-relaxed text-gray-600">
                Everything you need to collect feedback, plan your roadmap, and wire Feedback Pilgrim
                into the rest of your stack. Looking for endpoints? See the
                <a href="{{ route('documentation.reference') }}" class="font-medium text-orange-600 hover:text-orange-500">API reference</a>.
            </p>
        </header>

        <div class="mt-12 space-y-14">
            @foreach ($groupedGuides as $category => $guides)
                <section aria-labelledby="cat-{{ Str::slug($category) }}">
                    <h2 id="cat-{{ Str::slug($category) }}" class="text-sm font-semibold uppercase tracking-wider text-gray-400">{{ $category }}</h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        @foreach ($guides as $guide)
                            <a href="{{ route('documentation.guide', $guide['slug']) }}"
                               class="group flex flex-col rounded-xl border border-gray-200 bg-white p-5 transition hover:border-orange-300 hover:shadow-xs">
                                <h3 class="text-base font-semibold text-gray-900 group-hover:text-orange-600">{{ $guide['title'] }}</h3>
                                <p class="mt-1.5 text-sm leading-relaxed text-gray-600">{{ $guide['summary'] }}</p>
                                <span class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-orange-600">
                                    Read guide
                                    <svg class="h-4 w-4 transition group-hover:translate-x-0.5" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 5l5 5-5 5" />
                                    </svg>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </main>
</x-docs.shell>
