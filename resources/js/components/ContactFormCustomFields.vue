<template>
    <div>
        <div
            v-for="(customField, index) in customFields"
            :key="`custom_field_${index}`"
        >
            <contact-form-dynamic-row
                :fieldKey="customField.key"
                :field="customField.field"
                @destroyed="removeField(index)"
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

const addField = () => {
    customFields.value.push({
        key: "custom_field_" + (customFields.value.length + 1),
        field: "",
    });
};

const removeField = (index) =>
    (customFields.value = customFields.value.filter(
        (item, itemIndex) => itemIndex != index
    ));
</script>
