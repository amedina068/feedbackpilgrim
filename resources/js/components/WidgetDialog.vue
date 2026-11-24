<template>
    <dialog
        ref="dialog"
        :aria-labelledby="titleId"
        :class="[
            'bg-white p-0 text-left text-slate-900 shadow-xl ring-1 ring-black/5 backdrop:bg-slate-900/60 transition duration-150 ease-out motion-reduce:transition-none',
            panel
                ? 'fixed inset-y-0 right-0 left-auto m-0 h-dvh max-h-none w-full max-w-md starting:open:translate-x-full'
                : 'fixed inset-0 m-auto h-fit max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-lg rounded-lg starting:open:scale-95 starting:open:opacity-0',
        ]"
        @click="closeOnBackdrop"
        @close="onNativeClose"
    >
        <div :class="['flex flex-col gap-y-4', panel ? 'h-full overflow-y-auto py-5' : 'p-5 sm:p-6']">
            <div :class="['flex items-start justify-between gap-x-4', panel && 'px-4']">
                <h2 :id="titleId" class="min-w-0 text-base font-semibold text-slate-900">
                    <slot name="title" />
                </h2>
                <button
                    type="button"
                    class="relative -my-1.5 -mr-1.5 flex shrink-0 rounded-md p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
                    @click="$emit('close')"
                >
                    <span class="absolute top-1/2 left-1/2 size-[max(100%,3rem)] -translate-1/2 pointer-fine:hidden" aria-hidden="true" />
                    <span class="sr-only">Close</span>
                    <XMarkIcon class="size-5 shrink-0" aria-hidden="true" />
                </button>
            </div>

            <slot />
        </div>
    </dialog>
</template>

<script setup>
import { onMounted, ref, useId, watch } from "vue";
import { XMarkIcon } from "@heroicons/vue/20/solid";

/**
 * A dialog of the embeddable widget, on the native <dialog> element.
 * The browser keeps the focus in the dialog and closes it on Escape.
 * "panel" shows it as a full-height panel on the right side.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    panel: { type: Boolean, default: false },
});

const emit = defineEmits(["close"]);

const dialog = ref(null);
const titleId = useId();

const sync = (open) => {
    if (open && !dialog.value.open) {
        dialog.value.showModal();
    }

    if (!open && dialog.value.open) {
        dialog.value.close();
    }
};

onMounted(() => sync(props.open));
watch(() => props.open, sync);

const onNativeClose = () => {
    if (props.open) {
        emit("close");
    }
};

const closeOnBackdrop = (event) => {
    if (event.target === dialog.value) {
        emit("close");
    }
};
</script>
