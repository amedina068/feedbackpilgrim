<template>
  <div class="pb-2">
    <form ref="clientSelector" action="/update-client" method="POST">
      <input type="hidden" name="_token" :value="csrfToken">
      <select @change="selectOption" name="client_id" id="client_id" aria-label="Select client" class="w-full py-2 text-sm font-medium px-2.5 bg-fp-surface text-fp-hi border border-white/10 rounded-lg focus:ring-2 focus:ring-fp-accent focus:border-fp-accent">
        <option
          v-for="clientOption in clients"
          :key="clientOption.id"
          :selected="clientOption.id == client.id"
          :value="clientOption.id"
        >{{ clientOption.name }}</option>
        <option value="" data-href="/onboarding/project">New project</option>
      </select>
    </form>
  </div>
</template>

<script setup>
  import { computed, ref } from 'vue'

  const props = defineProps({
    clients: {
      type: Array,
      required: true,
    },

    client: {
      type: Object,
      required: true,
    }
  })

  const clientSelector = ref(null)

  const csrfToken = computed(() => {
    return document.querySelector('meta[name=csrf-token]').content
  })

  const selectOption = (event) => {
    const href = event.target.selectedOptions[0]?.dataset.href

    if (href) {
      window.location.assign(href)

      return
    }

    clientSelector.value.submit()
  }
</script>