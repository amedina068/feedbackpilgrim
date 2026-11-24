<template>
  <button
    type="button"
    @click="copy"
    :aria-label="status === 'idle' ? (ariaLabel || label) : undefined"
    class="inline-flex shrink-0 items-center gap-x-1.5 rounded-lg bg-fp-surface px-3 py-2 text-sm font-semibold text-fp-mid ring-1 ring-white/10 hover:bg-fp-surface"
  >
    <svg viewBox="0 0 16 16" fill="currentColor" class="h-4 w-4 shrink-0" aria-hidden="true">
      <path d="M11 2a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h4ZM5 4H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2v-.5H7A3 3 0 0 1 4 8.5V4Z" />
    </svg>
    <span aria-live="polite">{{ status === 'copied' ? copiedLabel : status === 'failed' ? failedLabel : label }}</span>
  </button>
</template>

<script setup>
import { ref, onBeforeUnmount } from 'vue'
import { copyText } from '../composables/clipboard.js'

const props = defineProps({
  text: { type: String, required: true },
  label: { type: String, default: 'Copy' },
  copiedLabel: { type: String, default: 'Copied' },
  failedLabel: { type: String, default: 'Copy failed. Select the text.' },
  ariaLabel: { type: String, default: '' },
})

const status = ref('idle')
let timer = null

const copy = async () => {
  status.value = (await copyText(props.text)) ? 'copied' : 'failed'
  clearTimeout(timer)
  timer = setTimeout(() => (status.value = 'idle'), status.value === 'copied' ? 1500 : 4000)
}

onBeforeUnmount(() => clearTimeout(timer))
</script>
