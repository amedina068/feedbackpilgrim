<div>
    <h4 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-400">Response</h4>
    <div class="overflow-hidden rounded-xl border border-gray-800 bg-gray-900" data-code-card>
        <div class="flex items-center gap-2 border-b border-white/10 px-4 py-2">
            <span class="rounded-sm bg-emerald-500/15 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-emerald-400">{{ $status }}</span>
            <span class="text-[11px] text-gray-500">application/json</span>
            <button type="button" class="copy-btn ml-auto shrink-0 px-2 py-1 text-[11px] font-medium text-gray-400 hover:text-gray-100">Copy</button>
        </div>
        <pre class="code-pane overflow-x-auto px-4 py-3.5 text-[13px] leading-relaxed text-gray-100"><code class="language-json">{{ $code }}</code></pre>
    </div>
</div>
