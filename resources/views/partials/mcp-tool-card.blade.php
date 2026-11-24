@php($tool = $tool ?? [])
<article id="mcp-tool-{{ $tool['name'] }}" class="scroll-mt-24 rounded-xl border border-gray-200 bg-white p-5">
    <div class="flex flex-wrap items-center gap-2">
        <code class="font-mono text-base font-semibold text-gray-900">{{ $tool['name'] }}</code>
        <span class="rounded-full bg-violet-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-violet-700 ring-1 ring-violet-600/20">tool</span>
    </div>
    @if (! empty($tool['description']))
        <p class="mt-2 leading-relaxed text-gray-600">{{ $tool['description'] }}</p>
    @endif
    @if (! empty($tool['params']))
        <div class="mt-4 overflow-x-auto">
            @include('partials.api-properties', ['rows' => $tool['params']])
        </div>
    @else
        <p class="mt-3 text-sm text-gray-400">No parameters.</p>
    @endif
</article>
