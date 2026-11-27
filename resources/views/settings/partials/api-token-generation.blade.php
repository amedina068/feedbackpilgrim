<section aria-labelledby="api-tokens-heading">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="max-w-xl">
            <h2 id="api-tokens-heading" class="text-lg font-semibold text-fp-hi">{{ __('API token generation') }}</h2>
            <p class="mt-1 text-base text-fp-mid sm:text-sm">
                {{ __('Generate tokens to access your data through the REST API and the MCP server. Each token is scoped to this project.') }}
            </p>
            <a href="{{ route('documentation.postman.download') }}" download
               class="mt-2 inline-flex items-center gap-x-1.5 text-sm font-medium text-fp-accent hover:text-fp-accent">
                <svg viewBox="0 0 16 16" fill="currentColor" class="h-4 w-4 shrink-0" aria-hidden="true">
                    <path d="M8 1a.75.75 0 0 1 .75.75v6.69l1.72-1.72a.75.75 0 1 1 1.06 1.06l-3 3a.75.75 0 0 1-1.06 0l-3-3a.75.75 0 0 1 1.06-1.06l1.72 1.72V1.75A.75.75 0 0 1 8 1ZM3 12.5a.75.75 0 0 1 .75.75v.25c0 .14.11.25.25.25h8a.25.25 0 0 0 .25-.25v-.25a.75.75 0 0 1 1.5 0v.25A1.75 1.75 0 0 1 12 15.25H4a1.75 1.75 0 0 1-1.75-1.75v-.25A.75.75 0 0 1 3 12.5Z" />
                </svg>
                {{ __('Download the Postman collection') }}
            </a>
        </div>

        <form method="post" action="{{ route('settings.api-keys.store') }}" class="w-full shrink-0 sm:w-64">
            @csrf
            <label for="token-name" class="block text-sm font-medium text-fp-mid">{{ __('Token name') }}</label>
            <div class="mt-1 flex gap-x-2">
                <input type="text" id="token-name" name="name" value="{{ old('name') }}" maxlength="100" placeholder="{{ __('For example, Zapier') }}"
                       class="block min-w-0 flex-1 rounded-md border-0 bg-fp-surface py-1.5 text-base text-fp-hi ring-1 ring-inset ring-white/10 placeholder:text-fp-low focus:ring-2 focus:ring-inset focus:ring-fp-accent sm:text-sm sm:leading-6">
                <x-primary-button type="submit">{{ __('Generate token') }}</x-primary-button>
            </div>
            <x-input-error class="mt-2" :messages="$errors->get('name')" />

            <label for="token-access" class="mt-3 block text-sm font-medium text-fp-mid">{{ __('Access') }}</label>
            <x-base.select name="access" id="token-access">
                <option value="full">{{ __('REST API and MCP, read and write') }}</option>
                <option value="mcp">{{ __('MCP, read and write') }}</option>
                <option value="mcp_read">{{ __('MCP, read only') }}</option>
                <option value="api">{{ __('REST API only') }}</option>
            </x-base.select>
            <x-input-error class="mt-2" :messages="$errors->get('access')" />

            <label for="token-expiry" class="mt-3 block text-sm font-medium text-fp-mid">{{ __('Expires') }}</label>
            <x-base.select name="expires_in_days" id="token-expiry">
                <option value="">{{ __('Never') }}</option>
                @foreach (\App\Http\Controllers\ApiKeyController::EXPIRY_DAYS as $days)
                    <option value="{{ $days }}">{{ trans_choice(':count day|:count days', $days) }}</option>
                @endforeach
            </x-base.select>
            <x-input-error class="mt-2" :messages="$errors->get('expires_in_days')" />
        </form>
    </div>

    @if (session('api_success'))
        <div role="status" class="mt-5 rounded-lg bg-fp-success/10 px-4 py-3 text-sm text-fp-success ring-1 ring-fp-success/20">
            {{ session('api_success') }}
        </div>
    @endif

    @if (session('api_error'))
        <div role="alert" class="mt-5 rounded-lg bg-fp-danger/10 px-4 py-3 text-sm text-fp-danger ring-1 ring-fp-danger/20">
            {{ session('api_error') }}
        </div>
    @endif

    @if (session('new_token'))
        <div class="mt-5 rounded-xl bg-fp-accent/10 p-4 ring-1 ring-fp-accent/20">
            <div class="flex items-start gap-x-2">
                <svg viewBox="0 0 16 16" fill="currentColor" class="mt-0.5 h-4 w-4 shrink-0 text-fp-accent" aria-hidden="true">
                    <path d="M8 1.5A6.5 6.5 0 1 0 14.5 8 6.5 6.5 0 0 0 8 1.5Zm.75 3.25a.75.75 0 0 0-1.5 0v3.5a.75.75 0 0 0 .4.664l2.5 1.25a.75.75 0 1 0 .7-1.328L8.75 7.79Z" />
                </svg>
                <p class="text-sm font-semibold text-fp-accent">{{ __('Copy your token now. It will not be shown again.') }}</p>
            </div>
            <div class="mt-3 flex items-stretch gap-x-2">
                <code class="min-w-0 flex-1 break-all select-all rounded-lg bg-fp-surface px-3 py-2 font-mono text-xs text-fp-hi ring-1 ring-white/10">{{ session('new_token') }}</code>
                <copy-button text="{{ session('new_token') }}" label="Copy token"></copy-button>
            </div>
        </div>
    @endif

    <div class="mt-6">
        <h3 class="text-sm font-semibold text-fp-hi">{{ __('Active tokens') }}</h3>

        @if ($apiKeys->count() > 0)
            <ul role="list" class="mt-3 divide-y divide-white/10 overflow-hidden rounded-xl ring-1 ring-white/10">
                @foreach ($apiKeys as $token)
                    <li class="flex items-center justify-between gap-x-4 bg-fp-surface px-4 py-3.5">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-fp-hi">{{ $token->name }}</p>
                            <p class="mt-0.5 text-xs text-fp-low">
                                {{ __('Created') }} {{ $token->created_at->format('M j, Y') }}
                                <span aria-hidden="true">&middot;</span>
                                @if ($token->last_used_at)
                                    {{ __('last used') }} {{ $token->last_used_at->diffForHumans() }}
                                @else
                                    {{ __('never used') }}
                                @endif
                            </p>
                            <p class="mt-0.5 text-xs text-fp-low">
                                @if (in_array('*', (array) $token->abilities, true))
                                    {{ __('Full access (old token)') }}
                                @else
                                    {{ collect((array) $token->abilities)->map(fn ($ability) => ['mcp:read' => 'MCP read', 'mcp:write' => 'MCP write', 'api' => 'REST API'][$ability] ?? $ability)->join(', ') }}
                                @endif
                                <span aria-hidden="true">&middot;</span>
                                @if ($token->expires_at)
                                    {{ $token->expires_at->isPast() ? __('expired') : __('expires') }} {{ $token->expires_at->format('M j, Y') }}
                                @else
                                    {{ __('no expiry') }}
                                @endif
                            </p>
                        </div>
                        <form method="post" action="{{ route('settings.api-keys.destroy', $token) }}"
                              onsubmit="return confirm('{{ __('Delete this token? Apps using it will lose access.') }}')">
                            @csrf
                            @method('delete')
                            <button type="submit" class="rounded-lg px-2.5 py-1.5 text-sm font-medium text-fp-danger hover:bg-fp-danger/10">
                                {{ __('Delete') }}
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="mt-3 rounded-xl border border-dashed border-white/10 px-4 py-8 text-center">
                <p class="text-sm text-fp-low">{{ __('No tokens yet. Generate one to get started.') }}</p>
            </div>
        @endif
    </div>
</section>
