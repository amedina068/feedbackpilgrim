<x-settings.layout active="api-tokens">
    <div class="space-y-10">
        @include('settings.partials.api-token-generation')

        <hr class="border-white/10">

        @include('settings.partials.mcp-setup')
    </div>
</x-settings.layout>
