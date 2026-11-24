<template>
    <WidgetDialog :open="open" panel @close="closeModal">
        <template #title>
            <span v-if="!selectedReleaseNote">Latest release notes</span>
            <span v-else class="flex items-center gap-x-2">
                <button
                    type="button"
                    class="relative flex shrink-0 rounded-md p-1 text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
                    @click="selectedReleaseNote = null"
                >
                    <span class="absolute top-1/2 left-1/2 size-[max(100%,3rem)] -translate-1/2 pointer-fine:hidden" aria-hidden="true" />
                    <span class="sr-only">Back to all release notes</span>
                    <ArrowUturnLeftIcon class="size-4 shrink-0" aria-hidden="true" />
                </button>
                <span class="min-w-0">{{ selectedReleaseNote.title }}</span>
            </span>
        </template>

        <div v-if="!selectedReleaseNote" class="flex flex-col gap-y-4">
            <p class="px-4 text-sm text-slate-700">
                Read about the latest features and bug fixes<template v-if="clientName"> in {{ clientName }}</template>.
            </p>

            <p v-if="loading" class="px-4 text-sm text-slate-600" role="status">Loading release notes…</p>
            <p v-else-if="loadError" class="px-4 text-sm text-red-700" role="alert">Could not load the release notes. Close this panel and try again.</p>
            <p v-else-if="!releaseNotes.length" class="px-4 text-sm text-slate-600">No release notes yet.</p>
            <ul v-else role="list" class="divide-y divide-slate-900/10 border-y border-slate-900/10">
                <li v-for="releaseNote in releaseNotes" :key="releaseNote.id">
                    <button
                        type="button"
                        class="group flex w-full flex-col gap-y-1 px-4 py-4 text-left hover:bg-slate-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-slate-900"
                        @click="selectedReleaseNote = releaseNote"
                    >
                        <span class="text-sm font-medium text-slate-900">{{ releaseNote.title }}</span>
                        <span class="text-xs text-slate-600">{{ releaseNote.content_excerpt }}</span>
                        <span class="font-mono text-xs text-slate-500">{{ releaseNote.readable_published_at }}</span>
                        <span class="pt-1 text-sm font-medium text-slate-700 group-hover:underline">Learn more &rarr;</span>
                    </button>
                </li>
            </ul>
        </div>

        <div v-else class="prose prose-sm px-4">
            <VueMarkdown :source="selectedReleaseNote.content" />
        </div>
    </WidgetDialog>
</template>

<script setup>
import { onMounted, ref } from "vue";
import VueMarkdown from "vue-markdown-render";
import { ArrowUturnLeftIcon } from "@heroicons/vue/16/solid";
import { useIframe } from "../composables/iframe.js";
import WidgetDialog from "./WidgetDialog.vue";

defineProps({
    clientName: {
        type: String,
        default: "",
    },
});

const releaseNotes = ref([]);
const loading = ref(true);
const loadError = ref(false);
const selectedReleaseNote = ref(null);

const { open, registerIframe, listen } = useIframe();

onMounted(() => {
    registerIframe({ iframe: "release-notes" });
    listen();
});

const closeModal = () => {
    open.value = false;
    selectedReleaseNote.value = null;
};

axios
    .get("/api/v1/release-notes")
    .then(({ data }) => {
        releaseNotes.value = data.data;
    })
    .catch(() => {
        loadError.value = true;
    })
    .finally(() => {
        loading.value = false;
    });
</script>
