<template>
    <slot name="trigger" :open="open" />

    <dialog
        ref="dialog"
        :aria-labelledby="labelledby"
        :class="[
            'fixed inset-0 m-auto h-fit max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] rounded-lg bg-fp-elevated p-0 text-fp-hi shadow-md ring-1 ring-white/10 backdrop:bg-black/70 transition duration-150 ease-out motion-reduce:transition-none starting:open:scale-95 starting:open:opacity-0',
            maxWidthClass,
        ]"
        @click="closeOnBackdrop"
    >
        <slot :close="close" />
    </dialog>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";

/**
 * A dashboard dialog on the native <dialog> element. The "trigger" slot gets
 * an `open` function, and the default slot gets a `close` function. The
 * browser keeps the focus in the dialog and closes it on Escape.
 */
const props = defineProps({
    labelledby: { type: String, default: null },
    openOnLoad: { type: Boolean, default: false },
    maxWidth: { type: String, default: "lg" },
});

const dialog = ref(null);

const maxWidthClass = computed(
    () =>
        ({
            sm: "max-w-sm",
            md: "max-w-md",
            lg: "max-w-lg",
            xl: "max-w-xl",
            "2xl": "max-w-2xl",
        })[props.maxWidth] ?? "max-w-lg",
);

const open = () => {
    if (!dialog.value.open) {
        dialog.value.showModal();
    }
};

const close = () => dialog.value.close();

const closeOnBackdrop = (event) => {
    if (event.target === dialog.value) {
        close();
    }
};

onMounted(() => {
    if (props.openOnLoad) {
        open();
    }
});
</script>
