<template>
    <WidgetDialog :open="open" @close="closeModal">
        <template #title>
            {{ submitted ? "Thank you for your testimonial!" : "Share Your Experience" }}
        </template>

        <form v-if="!submitted" class="flex flex-col gap-y-5" @submit.prevent="submitTestimonial">
            <p class="text-sm text-slate-600">
                We'd love to hear about your experience. Your testimonial helps others learn about our service.
            </p>

            <div class="flex flex-col gap-y-1">
                <label for="description" class="text-sm font-medium text-slate-700">
                    Your Experience <span class="text-red-600" aria-hidden="true">*</span>
                </label>
                <textarea
                    id="description"
                    v-model="form.description"
                    rows="4"
                    autofocus
                    required
                    class="w-full rounded-md border-slate-300 text-sm focus:border-orange-500 focus:ring-orange-500"
                    placeholder="Tell us about your experience..."
                ></textarea>
            </div>

            <div v-if="ratingEnabled" class="flex flex-col gap-y-1">
                <label for="rating" class="text-sm font-medium text-slate-700">
                    Rating <span class="text-red-600" aria-hidden="true">*</span>
                </label>
                <select
                    id="rating"
                    v-model="form.rating"
                    required
                    class="w-full rounded-md border-slate-300 text-sm focus:border-orange-500 focus:ring-orange-500"
                >
                    <option value="">Select rating</option>
                    <option v-for="i in 5" :key="i" :value="i">{{ i }} {{ i === 1 ? 'star' : 'stars' }}</option>
                </select>
            </div>

            <div v-if="errors.length > 0" role="alert" class="rounded-md border border-red-600/20 bg-red-50 px-4 py-3 text-sm text-red-800">
                <p class="font-medium">Please fix the following errors:</p>
                <ul class="mt-1 list-disc pl-5">
                    <li v-for="error in errors" :key="error">{{ error }}</li>
                </ul>
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="rounded-md px-3 py-2 text-sm font-medium text-slate-900 ring-1 ring-slate-900/15 ring-inset hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
                    @click="closeModal"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    :disabled="submitting"
                    class="rounded-md bg-orange-600 px-3 py-2 text-sm font-medium text-white hover:bg-orange-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orange-600 disabled:opacity-50"
                >
                    {{ submitting ? "Submitting..." : "Submit Testimonial" }}
                </button>
            </div>
        </form>

        <div v-else class="flex flex-col gap-y-5">
            <p class="flex items-start gap-x-3 text-sm text-slate-600">
                <CheckCircleIcon class="size-5 shrink-0 text-green-600" aria-hidden="true" />
                Your testimonial has been submitted and will be reviewed before being published.
            </p>

            <button
                type="button"
                class="rounded-md bg-orange-600 px-3 py-2 text-sm font-medium text-white hover:bg-orange-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orange-600 sm:self-end"
                @click="closeModal"
            >
                Close
            </button>
        </div>
    </WidgetDialog>
</template>

<script setup>
import { onMounted, ref, reactive } from "vue";
import { CheckCircleIcon } from "@heroicons/vue/20/solid";
import { useIframe } from "../composables/iframe.js";
import WidgetDialog from "./WidgetDialog.vue";

const props = defineProps({
    contactToken: {
        type: String,
        default: null
    }
});

const ratingEnabled = ref(false);
const submitted = ref(false);
const submitting = ref(false);
const errors = ref([]);

const form = reactive({
    description: '',
    rating: ''
});

const { open, registerIframe, listen } = useIframe();

onMounted(() => {
    registerIframe({ iframe: "testimonials" });
    listen();

    axios.get("/api/v1/testimonials/settings").then(({ data }) => {
        ratingEnabled.value = data.rating_enabled;
    });
});

const closeModal = () => {
    open.value = false;

    setTimeout(() => {
        submitted.value = false;
        errors.value = [];
        Object.keys(form).forEach(key => form[key] = '');
    }, 300);
}

const submitTestimonial = async () => {
    submitting.value = true;
    errors.value = [];

    try {
        const payload = {
            description: form.description
        };

        if (props.contactToken) {
            payload.contact_token = props.contactToken;
        }

        if (ratingEnabled.value) {
            payload.rating = form.rating;
        }

        await axios.post("/api/v1/testimonials", payload);

        submitted.value = true;
    } catch (error) {
        if (error.response && error.response.status === 422) {
            errors.value = Object.values(error.response.data.errors).flat();
        } else {
            errors.value = ['An unexpected error occurred. Please try again.'];
        }
    } finally {
        submitting.value = false;
    }
}
</script>
