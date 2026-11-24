<template>
    <Transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition ease-in duration-500"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <p v-if="show" role="status" aria-live="polite" :class="textClass">
            <slot />
        </p>
    </Transition>
</template>

<script setup>
import { onMounted, ref } from "vue";

const props = defineProps({
    delay: { type: Number, default: 2000 },
    textClass: { type: String, default: "text-sm text-fp-low" },
});

const show = ref(true);

onMounted(() => {
    setTimeout(() => (show.value = false), props.delay);
});
</script>
