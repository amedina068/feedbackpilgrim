<template>
    <div
        v-if="!destroyed"
        class="grid gap-2 px-4 py-5 sm:grid-cols-[12rem_minmax(0,1fr)] sm:items-center sm:gap-6 sm:px-6"
    >
        <input
            type="text"
            :id="fieldKey"
            aria-label="Field name"
            class="w-full rounded-md border-0 bg-fp-surface py-1.5 text-sm font-medium text-fp-hi ring-1 ring-white/10 ring-inset placeholder:text-fp-low focus:ring-2 focus:ring-fp-accent focus:ring-inset"
            v-model="fieldKey"
        />
        <div class="flex items-center gap-x-4">
            <input
                type="text"
                :name="`custom_fields[${fieldKey}]`"
                :id="field"
                :aria-label="`Value of ${fieldKey}`"
                class="w-full max-w-sm min-w-0 rounded-md border-0 bg-fp-surface py-1.5 text-sm text-fp-hi ring-1 ring-white/10 ring-inset placeholder:text-fp-low focus:ring-2 focus:ring-fp-accent focus:ring-inset"
                :value="field"
            />
            <button
                type="button"
                class="shrink-0 rounded-md px-2 py-1.5 text-sm font-medium text-fp-danger hover:bg-fp-danger/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent"
                @click="destroy"
            >
                Remove field
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, toRefs } from "vue";

const emit = defineEmits(["destroyed"]);

const props = defineProps({
    fieldKey: {
        type: String,
        required: true,
    },
    field: {
        type: String,
        required: true,
    },
});

const fieldKey = ref(props.fieldKey);
const field = ref(props.field);

const destroyed = ref(false);

const destroy = () => {
    emit("destroyed");
    destroyed.value = true;
};
</script>
