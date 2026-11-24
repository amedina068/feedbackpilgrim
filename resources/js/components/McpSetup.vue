<template>
  <section aria-labelledby="mcp-heading">
    <div class="max-w-2xl">
      <div class="flex items-center gap-x-2">
        <h2 id="mcp-heading" class="text-lg font-semibold text-fp-hi">MCP</h2>
        <span class="inline-flex items-center rounded-full bg-fp-accent/10 px-2 py-0.5 font-mono text-xs font-semibold text-fp-accent ring-1 ring-fp-accent/20">20 tools</span>
      </div>
      <p class="mt-1 text-base text-fp-mid sm:text-sm">
        Connect AI agents to your data over the Model Context Protocol. It exposes the same actions
        as the REST API and authenticates with an API token — paste one below to fill in the snippets.
      </p>
    </div>

    <!-- Connection details -->
    <dl class="mt-5 grid gap-4 sm:grid-cols-2">
      <div class="min-w-0">
        <dt class="text-xs font-semibold uppercase tracking-wide text-fp-low">Server URL</dt>
        <dd class="mt-1.5 flex items-stretch gap-x-2">
          <code class="min-w-0 flex-1 truncate rounded-lg bg-fp-surface px-3 py-2 font-mono text-sm text-fp-hi ring-1 ring-white/10">{{ url }}</code>
          <copy-button :text="url"></copy-button>
        </dd>
      </div>
      <div class="min-w-0">
        <dt class="text-xs font-semibold uppercase tracking-wide text-fp-low">Auth token</dt>
        <dd class="mt-1.5">
          <input
            v-model="token"
            type="text"
            autocomplete="off"
            spellcheck="false"
            aria-label="API token for MCP snippets"
            placeholder="Paste a token to fill snippets"
            class="block w-full rounded-lg border-0 bg-fp-surface px-3 py-2 font-mono text-sm text-fp-hi ring-1 ring-white/10 placeholder:font-sans placeholder:text-fp-low focus:ring-2 focus:ring-fp-accent max-sm:text-base"
          >
        </dd>
      </div>
    </dl>

    <!-- Agent tabs -->
    <div class="mt-6">
      <div role="tablist" aria-label="Agent setup" class="-mx-4 flex gap-x-1 overflow-x-auto border-b border-white/10 px-4 sm:mx-0 sm:px-0">
        <button
          v-for="t in tabs"
          :key="t.id"
          type="button"
          role="tab"
          :aria-selected="tab === t.id"
          @click="tab = t.id"
          class="-mb-px shrink-0 whitespace-nowrap border-b-2 px-3 py-2 text-sm font-semibold transition-colors"
          :class="tab === t.id ? 'border-fp-accent text-fp-accent' : 'border-transparent text-fp-low hover:text-fp-hi'"
        >{{ t.label }}</button>
      </div>

      <div v-for="t in tabs" :key="`panel-${t.id}`" v-show="tab === t.id" role="tabpanel" class="mt-4">
        <p class="text-sm text-fp-mid">{{ t.note }}</p>
        <div class="mt-2 overflow-hidden rounded-xl bg-black ring-1 ring-white/10">
          <div class="flex items-center justify-between gap-x-2 border-b border-white/10 px-4 py-2">
            <span class="font-mono text-xs text-fp-low">{{ t.file }}</span>
            <button
              type="button"
              @click="copy(t.snippet, t.id)"
              class="inline-flex items-center gap-x-1.5 rounded-md px-2 py-1 text-xs font-medium text-gray-300 hover:bg-white/10 hover:text-white"
            >
              <svg viewBox="0 0 16 16" fill="currentColor" class="h-4 w-4 shrink-0" aria-hidden="true">
                <path d="M11 2a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h4ZM5 4H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2v-.5H7A3 3 0 0 1 4 8.5V4Z" />
              </svg>
              <span aria-live="polite">{{ copied === t.id ? 'Copied' : copied === `failed:${t.id}` ? 'Copy failed. Select the text.' : 'Copy' }}</span>
            </button>
          </div>
          <pre class="overflow-x-auto px-4 py-3.5 text-[0.8125rem] leading-relaxed text-fp-hi"><code>{{ t.snippet }}</code></pre>
        </div>
      </div>
    </div>

    <p class="mt-4 text-xs text-fp-low">
      The endpoint is bearer-only and rejects session cookies. Generate a token above, then restart
      your agent after editing its config.
    </p>
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import { copyText } from '../composables/clipboard.js'

const props = defineProps({
  url: { type: String, required: true },
})

const token = ref('')
const tab = ref('claude-code')
const copied = ref('')
let timer = null

const tk = computed(() => token.value.trim() || 'YOUR_API_TOKEN')

const tabs = computed(() => {
  const url = props.url
  const bearer = `Bearer ${tk.value}`

  return [
    {
      id: 'claude-code',
      label: 'Claude Code',
      note: 'Run this in your terminal to register the server.',
      file: 'terminal',
      snippet: `claude mcp add --transport http feedback-pilgrim ${url} \\\n  --header "Authorization: ${bearer}"`,
    },
    {
      id: 'claude-desktop',
      label: 'Claude Desktop',
      note: 'Add to claude_desktop_config.json, then restart Claude Desktop.',
      file: 'claude_desktop_config.json',
      snippet: JSON.stringify(
        { mcpServers: { 'feedback-pilgrim': { type: 'http', url, headers: { Authorization: bearer } } } },
        null,
        2,
      ),
    },
    {
      id: 'codex',
      label: 'Codex',
      note: 'Add to ~/.codex/config.toml (bridges the remote server via mcp-remote).',
      file: '~/.codex/config.toml',
      snippet: `[mcp_servers.feedback-pilgrim]\ncommand = "npx"\nargs = ["-y", "mcp-remote", "${url}", "--header", "Authorization: ${bearer}"]`,
    },
    {
      id: 'generic',
      label: 'Cursor / other',
      note: 'Add to your client’s mcp.json (e.g. .cursor/mcp.json).',
      file: 'mcp.json',
      snippet: JSON.stringify(
        { mcpServers: { 'feedback-pilgrim': { url, headers: { Authorization: bearer } } } },
        null,
        2,
      ),
    },
  ]
})

const copy = async (text, id) => {
  copied.value = (await copyText(text)) ? id : `failed:${id}`
  clearTimeout(timer)
  timer = setTimeout(() => {
    if (copied.value === id || copied.value === `failed:${id}`) copied.value = ''
  }, 4000)
}

onBeforeUnmount(() => clearTimeout(timer))
</script>
