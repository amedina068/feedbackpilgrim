@php
    /** @var \App\Support\ApiReference $reference */
    $info = $reference->info();
    $groups = $reference->groups();
    $schemas = $reference->schemas();
    $baseUrl = $reference->baseUrl();

    $methodBadge = [
        'GET' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'POST' => 'bg-blue-50 text-blue-700 ring-blue-600/20',
        'PATCH' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'PUT' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'DELETE' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
    ];
    $methodText = [
        'GET' => 'text-emerald-600',
        'POST' => 'text-blue-600',
        'PATCH' => 'text-amber-600',
        'PUT' => 'text-amber-600',
        'DELETE' => 'text-rose-600',
    ];
    $statusTone = fn (string $s) => $s[0] === '2' ? 'text-emerald-700' : ($s[0] === '4' || $s[0] === '5' ? 'text-rose-700' : 'text-gray-700');
@endphp
<x-docs.shell title="{{ $info['title'] ?? 'API Reference' }}" active="reference">
    <x-slot:actions>
        <span class="hidden rounded-full bg-gray-100 px-2 py-0.5 font-mono text-[11px] text-gray-500 sm:inline">v{{ $info['version'] ?? '1' }}</span>
        <a href="{{ route('documentation.openapi.download') }}" class="text-gray-600 hover:text-gray-900">OpenAPI&nbsp;spec</a>
    </x-slot:actions>

    <div class="mx-auto flex max-w-(--breakpoint-2xl)">
        {{-- Left: reference navigation --}}
        <aside class="sticky top-15 hidden h-[calc(100vh-3.75rem)] w-60 shrink-0 overflow-y-auto border-r border-gray-200 px-4 py-8 lg:block">
            <nav class="space-y-7 text-sm" aria-label="Reference navigation">
                <div>
                    <p class="px-2 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Getting started</p>
                    <ul class="mt-2 space-y-0.5">
                        <li><a href="#overview" class="block rounded-md px-2 py-1.5 text-gray-600 hover:bg-gray-50 hover:text-gray-900">Overview</a></li>
                        <li><a href="#authentication" class="block rounded-md px-2 py-1.5 text-gray-600 hover:bg-gray-50 hover:text-gray-900">Authentication</a></li>
                        <li><a href="#conventions" class="block rounded-md px-2 py-1.5 text-gray-600 hover:bg-gray-50 hover:text-gray-900">Conventions</a></li>
                    </ul>
                </div>
                @foreach ($groups as $group)
                    <div>
                        <p class="px-2 text-[11px] font-semibold uppercase tracking-wider text-gray-400">{{ $group['name'] }}</p>
                        <ul class="mt-2 space-y-0.5">
                            @foreach ($group['operations'] as $op)
                                <li>
                                    <a href="#op-{{ $op['id'] }}" class="group flex items-center gap-2 rounded-md px-2 py-1.5 text-gray-600 hover:bg-gray-50 hover:text-gray-900">
                                        <span class="w-11 shrink-0 font-mono text-[10px] font-semibold uppercase {{ $methodText[$op['method']] ?? 'text-gray-500' }}">{{ $op['method'] }}</span>
                                        <span class="truncate">{{ $op['summary'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
                @if (count($mcpTools))
                    <div>
                        <p class="px-2 text-[11px] font-semibold uppercase tracking-wider text-gray-400">MCP server</p>
                        <ul class="mt-2 space-y-0.5">
                            <li><a href="#mcp" class="block rounded-md px-2 py-1.5 text-gray-600 hover:bg-gray-50 hover:text-gray-900">Overview</a></li>
                            @foreach ($mcpTools as $tool)
                                <li><a href="#mcp-tool-{{ $tool['name'] }}" class="block rounded-md px-2 py-1.5 font-mono text-[13px] text-gray-600 hover:bg-gray-50 hover:text-gray-900">{{ $tool['name'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <div>
                    <p class="px-2 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Schemas</p>
                    <ul class="mt-2 space-y-0.5">
                        @foreach ($schemas as $schema)
                            <li><a href="#schema-{{ $schema['name'] }}" class="block rounded-md px-2 py-1.5 font-mono text-[13px] text-gray-600 hover:bg-gray-50 hover:text-gray-900">{{ $schema['name'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </nav>
        </aside>

        {{-- Center + right --}}
        <main class="min-w-0 flex-1 px-4 py-10 sm:px-8 lg:px-12">
            {{-- Intro (single column) --}}
            <div class="max-w-3xl space-y-16">
                <section id="overview" aria-labelledby="overview-heading">
                    <p class="font-mono text-xs font-semibold uppercase tracking-wider text-blue-600">Public REST API</p>
                    <h1 id="overview-heading" class="mt-2 text-4xl font-bold tracking-tight">{{ $info['title'] ?? 'API Reference' }}</h1>
                    <p class="mt-4 text-lg leading-relaxed text-gray-600">
                        Integrate feedback collection and management into your own applications. Every request is
                        authenticated with a personal API token and automatically scoped to your client — you only ever
                        read and write data that belongs to you.
                    </p>
                    <div class="mt-6 flex flex-col gap-3 rounded-2xl border border-gray-200 bg-gray-50 p-5 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Base URL</p>
                            <code class="mt-0.5 block break-all font-mono text-sm text-gray-900">{{ $baseUrl }}</code>
                        </div>
                        <a href="{{ route('documentation.openapi.download') }}" class="shrink-0 rounded-lg border border-gray-300 bg-white px-3 py-2 text-center text-sm font-medium text-gray-700 hover:border-gray-400">Download OpenAPI&nbsp;3.1</a>
                    </div>
                </section>

                <section id="authentication" aria-labelledby="auth-heading" class="scroll-mt-24">
                    <h2 id="auth-heading" class="text-2xl font-bold tracking-tight">Authentication</h2>
                    <p class="mt-3 leading-relaxed text-gray-600">
                        Send your API token as a Bearer token with each request. Make and delete tokens in
                        <a href="{{ route('settings.index') }}" class="font-medium text-blue-600 hover:text-blue-800">Settings → API Tokens</a>.
                        A request without a valid token gets <code class="rounded-sm bg-gray-100 px-1 py-0.5 text-[13px] text-gray-700">401</code>.
                        Each error comes back as JSON with a <code class="rounded-sm bg-gray-100 px-1 py-0.5 text-[13px] text-gray-700">message</code> that says what went wrong.
                    </p>
                    <p class="mt-3 leading-relaxed text-gray-600">
                        Only <code class="rounded-sm bg-gray-100 px-1 py-0.5 text-[13px] text-gray-700">POST /api/v1/init</code> takes the publishable key
                        (<code class="rounded-sm bg-gray-100 px-1 py-0.5 text-[13px] text-gray-700">pk_</code>) instead. The widget script sends it from the browser.
                        Each user can make 120 requests each minute. After that, the API returns <code class="rounded-sm bg-gray-100 px-1 py-0.5 text-[13px] text-gray-700">429</code> with <code class="rounded-sm bg-gray-100 px-1 py-0.5 text-[13px] text-gray-700">Retry-After</code>.
                    </p>
                    @include('partials.api-code', ['title' => 'Request headers', 'code' => "Authorization: Bearer YOUR_API_TOKEN\nAccept: application/json\nContent-Type: application/json"])
                </section>

                <section id="conventions" aria-labelledby="conv-heading" class="scroll-mt-24">
                    <h2 id="conv-heading" class="text-2xl font-bold tracking-tight">Conventions</h2>
                    <ul class="mt-4 space-y-3 text-gray-600">
                        <li class="flex gap-3"><span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-blue-500"></span><span>List endpoints take <code class="rounded-sm bg-gray-100 px-1 py-0.5 text-[13px] text-gray-700">per_page</code> (1 to 100) and <code class="rounded-sm bg-gray-100 px-1 py-0.5 text-[13px] text-gray-700">page</code>, and return a Laravel paginator: <code class="rounded-sm bg-gray-100 px-1 py-0.5 text-[13px] text-gray-700">data</code>, <code class="rounded-sm bg-gray-100 px-1 py-0.5 text-[13px] text-gray-700">current_page</code>, <code class="rounded-sm bg-gray-100 px-1 py-0.5 text-[13px] text-gray-700">total</code>, <code class="rounded-sm bg-gray-100 px-1 py-0.5 text-[13px] text-gray-700">next_page_url</code> and more.</span></li>
                        <li class="flex gap-3"><span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-blue-500"></span><span>Validation failures return <code class="rounded-sm bg-gray-100 px-1 py-0.5 text-[13px] text-gray-700">422</code> with a <code class="rounded-sm bg-gray-100 px-1 py-0.5 text-[13px] text-gray-700">message</code> and field-level <code class="rounded-sm bg-gray-100 px-1 py-0.5 text-[13px] text-gray-700">errors</code>.</span></li>
                        <li class="flex gap-3"><span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-blue-500"></span><span>Resources outside your client scope return <code class="rounded-sm bg-gray-100 px-1 py-0.5 text-[13px] text-gray-700">404</code>; unsupported deletes return <code class="rounded-sm bg-gray-100 px-1 py-0.5 text-[13px] text-gray-700">405</code>.</span></li>
                    </ul>
                </section>
            </div>

            {{-- Endpoint groups (two-column: fields | code) --}}
            @foreach ($groups as $group)
                <section id="tag-{{ $group['slug'] }}" aria-labelledby="tag-{{ $group['slug'] }}-heading" class="mt-16 scroll-mt-24">
                    <div class="border-b border-gray-200 pb-4">
                        <h2 id="tag-{{ $group['slug'] }}-heading" class="text-2xl font-bold tracking-tight">{{ $group['name'] }}</h2>
                        @if ($group['description'])
                            <p class="mt-2 text-gray-600">{{ $group['description'] }}</p>
                        @endif
                    </div>

                    <div class="divide-y divide-gray-200">
                        @foreach ($group['operations'] as $op)
                            <article id="op-{{ $op['id'] }}" class="scroll-mt-24 py-10">
                                <div class="flex flex-wrap items-center gap-3">
                                    <span class="inline-flex items-center rounded-md px-2 py-1 font-mono text-xs font-bold uppercase ring-1 ring-inset {{ $methodBadge[$op['method']] ?? 'bg-gray-50 text-gray-700 ring-gray-600/20' }}">{{ $op['method'] }}</span>
                                    <code class="break-all font-mono text-sm font-medium text-gray-900">{{ $op['path'] }}</code>
                                </div>
                                <h3 class="mt-3 text-lg font-semibold text-gray-900">{{ $op['summary'] }}</h3>
                                @if ($op['description'])
                                    <p class="mt-2 max-w-3xl leading-relaxed text-gray-600">{{ $op['description'] }}</p>
                                @endif

                                <div class="mt-6 grid items-start gap-x-10 gap-y-8 xl:grid-cols-2">
                                    {{-- Center: fields --}}
                                    <div class="min-w-0 space-y-6">
                                        @if (count($op['parameters']))
                                            <div>
                                                <h4 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-400">Parameters</h4>
                                                <div class="overflow-hidden rounded-xl border border-gray-200">
                                                    <table class="w-full border-collapse text-sm">
                                                        <tbody class="divide-y divide-gray-100">
                                                            @foreach ($op['parameters'] as $param)
                                                                <tr class="align-top">
                                                                    <td class="w-2/5 py-3 pl-4 pr-3">
                                                                        <code class="font-mono text-[13px] font-semibold text-gray-900">{{ $param['name'] }}</code>
                                                                        <div class="mt-0.5 text-[11px] text-gray-400">{{ $param['in'] }}@if ($param['required']) · <span class="text-rose-600">required</span>@endif</div>
                                                                    </td>
                                                                    <td class="py-3 pl-3 pr-4">
                                                                        <div class="flex flex-wrap items-center gap-2">
                                                                            <span class="font-mono text-xs text-violet-700">{{ $param['type'] }}</span>
                                                                            @if (! is_null($param['default']))
                                                                                <span class="text-[11px] text-gray-400">default <code class="text-gray-600">{{ var_export($param['default'], true) }}</code></span>
                                                                            @endif
                                                                        </div>
                                                                        @if (! empty($param['enum']))
                                                                            <div class="mt-1.5 flex flex-wrap gap-1">
                                                                                @foreach ($param['enum'] as $value)
                                                                                    <code class="rounded-sm bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-700">{{ $value }}</code>
                                                                                @endforeach
                                                                            </div>
                                                                        @endif
                                                                        @if ($param['description'])
                                                                            <p class="mt-1.5 leading-relaxed text-gray-500">{{ $param['description'] }}</p>
                                                                        @endif
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        @endif

                                        @if ($op['body'])
                                            <div>
                                                <h4 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-400">
                                                    Body parameters
                                                    @if ($op['body']['required'])<span class="ml-1 text-rose-600">required</span>@endif
                                                </h4>
                                                @include('partials.api-properties', ['rows' => $op['body']['rows']])
                                            </div>
                                        @endif

                                        <div>
                                            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-400">Responses</h4>
                                            <div class="overflow-hidden rounded-xl border border-gray-200">
                                                <table class="w-full border-collapse text-sm">
                                                    <tbody class="divide-y divide-gray-100">
                                                        @foreach ($op['responses'] as $response)
                                                            <tr class="align-top">
                                                                <td class="w-16 py-2.5 pl-4 pr-3 font-mono text-sm font-bold {{ $statusTone($response['status']) }}">{{ $response['status'] }}</td>
                                                                <td class="py-2.5 pl-3 pr-4 text-gray-600">{{ $response['description'] }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Right: code examples --}}
                                    <div class="min-w-0 space-y-6 xl:sticky xl:top-24">
                                        @include('partials.api-request', ['examples' => $op['examples']])
                                        @if ($op['response'])
                                            @include('partials.api-response', ['status' => $op['response']['status'], 'code' => $op['response']['example']])
                                        @endif
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach

            {{-- MCP server --}}
            @if (count($mcpTools))
                @php
                    $mcpUrl = rtrim(config('app.url'), '/').'/mcp';
                    $mcpClientConfig = json_encode([
                        'mcpServers' => [
                            'feedback-pilgrim' => [
                                'type' => 'http',
                                'url' => $mcpUrl,
                                'headers' => ['Authorization' => 'Bearer YOUR_API_TOKEN'],
                            ],
                        ],
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                    $mcpExampleCall = json_encode([
                        'jsonrpc' => '2.0',
                        'id' => 1,
                        'method' => 'tools/call',
                        'params' => ['name' => 'list_posts', 'arguments' => ['per_page' => 2, 'status' => 'pending']],
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                    $mcpExampleResponse = json_encode([
                        'records' => [['id' => 12, 'title' => 'Dark mode', 'status' => 'pending']],
                        'pagination' => ['page' => 1, 'per_page' => 2, 'total' => 1, 'has_more' => false],
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                    $mcpExampleError = json_encode(['content' => [['type' => 'text', 'text' => 'No post with ID 42 exists for the client of this token.']], 'isError' => true], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                @endphp
                <section id="mcp" aria-labelledby="mcp-heading" class="mt-16 scroll-mt-24">
                    <div class="max-w-3xl border-b border-gray-200 pb-4">
                        <h2 id="mcp-heading" class="text-2xl font-bold tracking-tight">MCP server</h2>
                        <p class="mt-2 leading-relaxed text-gray-600">
                            The Model Context Protocol (MCP) server lets AI agents work with your Feedback Pilgrim
                            data through the same logic as the REST API — it is just another transport. Every tool is
                            scoped to the client of the Sanctum token you authenticate with; you never pass a
                            <code class="rounded-sm bg-gray-100 px-1 py-0.5 text-[12px]">client_id</code>.
                        </p>
                    </div>

                    <div class="mt-8 grid gap-8 lg:grid-cols-2">
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Connecting</h3>
                                <p class="mt-1.5 leading-relaxed text-gray-600">
                                    Point your MCP client at <code class="rounded-sm bg-gray-100 px-1 py-0.5 text-[12px]">{{ $mcpUrl }}</code>
                                    over streamable HTTP and authenticate with the same bearer token used for the REST API.
                                    The endpoint is stateless and bearer-only; session cookies are rejected.
                                </p>
                            </div>
                            @include('partials.api-code', ['title' => 'Client configuration', 'code' => $mcpClientConfig])
                        </div>
                        <div class="space-y-4">
                            @include('partials.api-request', ['examples' => [['label' => 'tools/call', 'lang' => 'json', 'code' => $mcpExampleCall]]])
                            @include('partials.api-response', ['status' => '200', 'code' => $mcpExampleResponse])
                            @include('partials.api-response', ['status' => 'error', 'code' => $mcpExampleError])
                        </div>
                    </div>

                    <h3 class="mt-12 text-lg font-semibold text-gray-900">Tools</h3>
                    <p class="mt-1.5 max-w-3xl leading-relaxed text-gray-600">List tools return a paginated envelope (<code class="rounded-sm bg-gray-100 px-1 py-0.5 text-[12px]">records</code> + <code class="rounded-sm bg-gray-100 px-1 py-0.5 text-[12px]">pagination</code>); get tools take an <code class="rounded-sm bg-gray-100 px-1 py-0.5 text-[12px]">id</code>.</p>
                    <div class="mt-6 grid gap-4 lg:grid-cols-2">
                        @foreach ($mcpTools as $tool)
                            @include('partials.mcp-tool-card', ['tool' => $tool])
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Schemas (single column) --}}
            <section id="schemas" aria-labelledby="schemas-heading" class="mt-16 scroll-mt-24">
                <div class="max-w-3xl border-b border-gray-200 pb-4">
                    <h2 id="schemas-heading" class="text-2xl font-bold tracking-tight">Schemas</h2>
                    <p class="mt-2 text-gray-600">The object shapes returned by the API.</p>
                </div>
                <div class="mt-8 max-w-3xl space-y-10">
                    @foreach ($schemas as $schema)
                        <article id="schema-{{ $schema['name'] }}" class="scroll-mt-24">
                            <h3 class="font-mono text-base font-semibold text-gray-900">{{ $schema['name'] }}</h3>
                            @if ($schema['description'])
                                <p class="mt-1.5 leading-relaxed text-gray-600">{{ $schema['description'] }}</p>
                            @endif
                            <div class="mt-3">@include('partials.api-properties', ['rows' => $schema['rows']])</div>
                        </article>
                    @endforeach
                </div>
            </section>

            <footer class="mt-16 max-w-3xl border-t border-gray-200 pt-8 text-sm text-gray-400">
                New here? Start with the <a href="{{ route('documentation') }}" class="text-blue-600 hover:text-blue-800">Guides</a>,
                or grab a token in your <a href="{{ route('settings.index') }}" class="text-blue-600 hover:text-blue-800">Settings</a>.
            </footer>
        </main>
    </div>
</x-docs.shell>
