<template>
    <div>
        <div
            v-for="customField in customFields"
            :key="customField.id"
        >
            <contact-form-dynamic-row
                :fieldKey="customField.key"
                :field="customField.field"
                @destroyed="removeField(customField.id)"
            ></contact-form-dynamic-row>
        </div>
        <div class="px-4 py-6 sm:px-6 flex justify-center w-full">
            <button
                @click.prevent="addField"
                type="button"
                class="rounded-md bg-fp-surface px-2.5 py-1.5 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface"
            >
                Add Custom Field
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref } from "vue";
import ContactFormDynamicRow from "./ContactFormDynamicRow.vue";

const customFields = ref([]);

let nextFieldId = 1;

const addField = () => {
    const id = nextFieldId++;

    customFields.value.push({
        id,
        key: "custom_field_" + id,
        field: "",
    });
};

const removeField = (id) =>
    (customFields.value = customFields.value.filter((item) => item.id !== id));
</script>
