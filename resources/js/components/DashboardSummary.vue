<template>
  <div class="overflow-hidden">
    <div class="px-4 py-4 sm:px-6">
      <div class="flex justify-between items-baseline">
        <div class="flex space-x-2 items-center">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z" />
          </svg>
          <h3 class="text-lg font-semibold leading-7 text-fp-hi">
            Weekly Summary
          </h3>
        </div>
      </div>
    </div>
    <div class="border-t border-white/5">
      <dl class="grid grid-cols-1 gap-px bg-white/5 sm:grid-cols-2 lg:grid-cols-4">
        <div class="bg-fp-base px-4 py-6 sm:px-6 lg:px-8">
          <dt class="truncate text-sm font-medium leading-6 text-fp-mid">Active Contacts</dt>
          <dd class="mt-2 flex items-baseline gap-x-2">
            <span class="font-mono text-4xl font-semibold tracking-tight text-fp-hi tabular-nums">{{ summary.active_contacts }}</span>
            <span class="font-mono text-sm text-fp-low">/ {{ summary.total_contacts }}</span>
          </dd>
          <div class="mt-2">
            <div class="flex items-center text-sm">
              <span class="text-fp-low">Activity rate:</span>
              <span class="ml-1 font-mono font-medium" :class="activityRateColor">{{ summary.activity_rate }}%</span>
            </div>
          </div>
        </div>

        <div class="bg-fp-base px-4 py-6 sm:px-6 lg:px-8">
          <dt class="truncate text-sm font-medium leading-6 text-fp-mid">Posts Created</dt>
          <dd class="mt-2 flex items-baseline gap-x-2">
            <span class="font-mono text-4xl font-semibold tracking-tight text-fp-hi tabular-nums">{{ summary.posts_created }}</span>
          </dd>
          <div class="mt-2">
            <div class="flex items-center text-sm text-fp-low">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
              </svg>
              New feedback items
            </div>
          </div>
        </div>

        <div class="bg-fp-base px-4 py-6 sm:px-6 lg:px-8">
          <dt class="truncate text-sm font-medium leading-6 text-fp-mid">Posts Published</dt>
          <dd class="mt-2 flex items-baseline gap-x-2">
            <span class="font-mono text-4xl font-semibold tracking-tight text-fp-hi tabular-nums">{{ summary.posts_published }}</span>
          </dd>
          <div class="mt-2">
            <div class="flex items-center text-sm text-fp-low">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
              </svg>
              Made public
            </div>
          </div>
        </div>

        <div class="bg-fp-base px-4 py-6 sm:px-6 lg:px-8">
          <dt class="truncate text-sm font-medium leading-6 text-fp-mid">Comments</dt>
          <dd class="mt-2 flex items-baseline gap-x-2">
            <span class="font-mono text-4xl font-semibold tracking-tight text-fp-hi tabular-nums">{{ summary.comments_created }}</span>
          </dd>
          <div class="mt-2">
            <div class="flex items-center text-sm text-fp-low">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.068.157 2.148.279 3.238.364.466.037.893.281 1.153.671L12 21l2.652-3.978c.26-.39.687-.634 1.153-.67 1.09-.086 2.17-.208 3.238-.365 1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
              </svg>
              Engagement
            </div>
          </div>
        </div>
      </dl>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  summary: {
    type: Object,
    required: true
  }
})

const activityRateColor = computed(() => {
  const rate = props.summary.activity_rate
  if (rate >= 75) return 'text-fp-success'
  if (rate >= 50) return 'text-fp-warning'
  if (rate >= 25) return 'text-fp-accent'
  return 'text-fp-danger'
})
</script>