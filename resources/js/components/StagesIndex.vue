<template>
    <div class="overflow-x-auto pb-4">
        <p
            v-if="moveError"
            role="alert"
            class="mb-4 rounded-lg bg-fp-danger/10 px-4 py-3 text-sm text-fp-danger ring-1 ring-fp-danger/20"
        >
            {{ moveError }}
        </p>
        <div class="flex gap-4">
            <section
                v-for="stage in stagesList"
                :key="stage.id"
                class="flex w-72 shrink-0 flex-col rounded-xl bg-fp-surface sm:w-80"
            >
                <header class="flex items-center gap-2 px-4 pt-4 pb-3">
                    <span
                        class="size-2.5 shrink-0 rounded-full"
                        :style="{ backgroundColor: stage.color }"
                    ></span>
                    <h3 class="text-sm font-semibold text-fp-hi">
                        {{ stage.name }}
                    </h3>
                    <span
                        class="ml-auto rounded-full bg-fp-surface px-2 py-0.5 text-xs font-medium text-fp-low ring-1 ring-inset ring-white/10"
                    >
                        {{ stage.posts.length }}
                    </span>
                </header>
                <draggable
                    :list="stage.posts"
                    group="post"
                    tag="ul"
                    class="flex min-h-32 flex-1 flex-col gap-2 px-3 pb-3"
                    :class="[
                        !stage.posts.length
                            ? 'rounded-lg border border-dashed border-white/10'
                            : '',
                    ]"
                    @start="rememberStages"
                    @change="(e) => updatePost(stage.id, e)"
                    item-key="id"
                >
                    <template #item="{ element: post }">
                        <li
                            class="cursor-grab rounded-lg bg-fp-surface p-3 ring-1 ring-white/10 transition hover:ring-white/10 active:cursor-grabbing"
                        >
                            <h4 class="text-sm font-semibold text-fp-hi">
                                <a
                                    :href="`/posts/${post.id}`"
                                    class="hover:text-fp-accent"
                                    >{{ post.title }}</a
                                >
                            </h4>
                            <p class="mt-1 line-clamp-2 text-sm text-fp-mid">
                                {{ post.content }}
                            </p>
                            <div
                                class="mt-3 flex items-center justify-between text-xs text-fp-low"
                            >
                                <span>{{ post.created_at }}</span>
                                <span
                                    class="inline-flex items-center gap-1 font-medium text-fp-low"
                                >
                                    <svg
                                        class="size-3.5"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                        aria-hidden="true"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M10 3a.75.75 0 0 1 .55.24l5.25 5.5a.75.75 0 1 1-1.1 1.02L10.75 6.3V16a.75.75 0 0 1-1.5 0V6.3L5.3 9.76a.75.75 0 0 1-1.1-1.02l5.25-5.5A.75.75 0 0 1 10 3Z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                    {{ post.votes_count ?? 0 }}
                                </span>
                            </div>
                        </li>
                    </template>
                </draggable>
            </section>
        </div>
    </div>
</template>

<script setup>
import draggable from "vuedraggable";
import { ref, toRefs } from "vue";
import { moveErrorMessage, snapshotStages } from "../composables/stageMove.js";

const props = defineProps({
    stages: {
        type: Array,
        required: true,
    },
});

const { stages } = toRefs(props);
const stagesList = ref(stages.value);
const drag = ref(false);
const loading = ref(false);
const moveError = ref(null);
let stagesBeforeDrag = null;

const rememberStages = () => {
    stagesBeforeDrag = snapshotStages(stagesList.value);
};

const parseEvent = (stageId, event) => {
    if (event.hasOwnProperty("removed")) {
        return;
    }

    if (event.hasOwnProperty("added")) {
        return {
            stage_id: stageId,
            position: event.added.newIndex,
            postId: event.added.element.id,
        };
    }

    if (event.hasOwnProperty("moved")) {
        return {
            stage_id: stageId,
            position: event.moved.newIndex,
            postId: event.moved.element.id,
        };
    }
};

const updatePost = (stageId, event) => {
    const eventData = parseEvent(stageId, event);

    if (!eventData) {
        return;
    }
    loading.value = true;
    moveError.value = null;
    const previousStages = stagesBeforeDrag;

    axios
        .patch(`/posts/${eventData.postId}/stage`, {
            position: eventData.position,
            stage_id: eventData.stage_id,
        })
        .then(({ data }) => {
            stagesList.value = stagesList.value.map((stage) => {
                const newStage = data.data.find(
                    (dataStage) => dataStage.id == stage.id
                );
                if (newStage) {
                    return newStage;
                }
                return stage;
            });
        })
        .catch((error) => {
            if (previousStages) {
                stagesList.value = previousStages;
            }
            moveError.value = moveErrorMessage(error);
        })
        .finally(() => (loading.value = false));
    drag.value = false;
};
</script>
