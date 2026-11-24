@php($title = $title ?? null)
<div class="mt-3 overflow-hidden rounded-xl border border-gray-800 bg-gray-900">
    @if ($title)
        <div class="border-b border-white/10 px-4 py-2 text-[11px] font-semibold uppercase tracking-wider text-gray-400">{{ $title }}</div>
    @endif
    <pre class="overflow-x-auto px-4 py-3.5 text-[13px] leading-relaxed text-gray-100"><code>{{ $code }}</code></pre>
</div>
