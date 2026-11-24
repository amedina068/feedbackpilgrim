<div>
    <h4 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-400">Request</h4>
    <div class="overflow-hidden rounded-xl border border-gray-800 bg-gray-900" data-code-card>
        <div class="flex items-center gap-1 border-b border-white/10 px-2">
            @foreach ($examples as $i => $example)
                <button type="button"
                    class="code-tab shrink-0 border-b-2 px-3 py-2 text-xs font-medium transition-colors {{ $i === 0 ? 'border-gray-300 text-gray-100' : 'border-transparent text-gray-500 hover:text-gray-300' }}"
                    data-lang="{{ $example['lang'] }}">{{ $example['label'] }}</button>
            @endforeach
            <button type="button" class="copy-btn ml-auto shrink-0 px-2 py-1 text-[11px] font-medium text-gray-400 hover:text-gray-100">Copy</button>
        </div>
        @foreach ($examples as $i => $example)
            <pre class="code-pane overflow-x-auto px-4 py-3.5 text-[13px] leading-relaxed text-gray-100 {{ $i === 0 ? '' : 'hidden' }}" data-lang="{{ $example['lang'] }}"><code class="language-{{ $example['lang'] }}">{{ $example['code'] }}</code></pre>
        @endforeach
    </div>
</div>
