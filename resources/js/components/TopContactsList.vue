<template>
  <section class="flex flex-col">
    <h3 class="px-4 py-4 text-lg font-semibold text-fp-hi sm:px-6">{{ title }}</h3>

    <ol v-if="contacts.length" role="list" class="divide-y divide-white/5 border-t border-white/5">
      <li
        v-for="(contact, index) in contacts"
        :key="contact.id"
        class="relative flex items-center gap-x-4 px-4 py-4 hover:bg-fp-surface sm:px-6"
      >
        <p class="w-5 shrink-0 text-right font-mono text-sm text-fp-low tabular-nums">{{ index + 1 }}</p>
        <img :src="contact.avatar" alt="" class="size-10 shrink-0 rounded-full outline-1 -outline-offset-1 outline-white/10">
        <div class="flex min-w-0 flex-1 flex-col">
          <div class="flex min-w-0 flex-wrap items-center gap-2">
            <a
              :href="`/contacts/${contact.id}`"
              class="min-w-0 truncate rounded-sm text-sm/6 font-medium text-fp-hi after:absolute after:inset-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent"
            >{{ contact.name || 'N/A' }}</a>
            <span
              v-if="contact.top_customer"
              class="rounded-md bg-fp-success/10 px-1.5 py-0.5 font-mono text-xs font-medium text-fp-success ring-1 ring-fp-success/20 ring-inset"
            >Top Customer</span>
          </div>
          <p class="truncate text-xs/5 text-fp-low">{{ contact.email }}</p>
        </div>
        <div class="shrink-0 text-right">
          <p class="font-mono text-sm font-medium text-fp-hi tabular-nums">{{ getMetricValue(contact) }}</p>
          <p class="text-xs text-fp-low">{{ getMetricLabel(contact) }}</p>
        </div>
      </li>
    </ol>

    <div v-else class="mx-4 mt-2 mb-6 flex flex-col gap-y-1 rounded-lg border border-dashed border-white/10 px-5 py-6 sm:mx-6">
      <p class="text-sm font-medium text-fp-hi">No activity yet</p>
      <p class="text-sm text-fp-mid">No contacts have {{ emptyStateText }} in the past 7 days.</p>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  contacts: {
    type: Array,
    required: true
  },
  title: {
    type: String,
    required: true
  },
  type: {
    type: String,
    required: true,
    validator: value => ['logins', 'posts', 'activity'].includes(value)
  }
})

const emptyStateText = computed(() => {
  const texts = {
    logins: 'logged in',
    posts: 'published posts',
    activity: 'any activity'
  }
  return texts[props.type] || 'activity'
})

const getMetricValue = (contact) => {
  switch (props.type) {
    case 'logins':
      return contact.login_count
    case 'posts':
      return contact.published_posts_count
    case 'activity':
      return contact.total_activity
    default:
      return 0
  }
}

const getMetricLabel = (contact) => {
  switch (props.type) {
    case 'logins':
      return contact.login_count === 1 ? 'login' : 'logins'
    case 'posts':
      return contact.published_posts_count === 1 ? 'published post' : 'published posts'
    case 'activity':
      return 'total actions'
    default:
      return ''
  }
}
</script>
