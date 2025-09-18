<template>
    <div class="relative w-full dark:bg-slate-50 dark:text-slate-500">
        <div class="relative">
            <v-select
                :id="id"
                :options="searchableOptions"
                :label="labelKey"
                :reduce="reduceInternal"
                :modelValue="modelValue"
                @update:modelValue="val => emit('update:modelValue', val)"
                @search="handleSearch"
                :filterable="false"
                :loading="isLoading"
                :close-on-select="true"
                class="col-span-3 border border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                v-bind="$attrs"
            />
            <!-- Floating label sits *under* the dropdown and ignores pointer events -->
            <FloatingLabel
                :id="id"
                :label="floatingText"
                class="pointer-events-none z-0"
            />
        </div>

        <span v-if="error" class="mt-1 block text-red-500 text-sm">
      {{ error }}
    </span>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import FloatingLabel from "@/Components/next/FloatingLabel.vue";
import { useSearchResources } from '@/composables/useSearchResources.js';

const props = defineProps({
    modelValue: [String, Number, Object, Array, null],
    options: { type: Array, default: () => [] },
    labelKey: { type: String, default: "name" },
    valueKey: { type: String, default: "id" },
    reduce: { type: Function, default: null },
    id: { type: String, default: () => "sel-" + Math.random().toString(36).slice(2) },
    floatingText: { type: String, default: "" },
    error: { type: String, default: "" },
    // Search-related props
    searchable: { type: Boolean, default: false },
    resourceType: { type: String, default: null },
    searchFields: { type: Array, default: () => ['name'] },
    searchOptions: { type: Object, default: () => ({}) },
});
// ... existing imports and props ...

const emit = defineEmits(["update:modelValue"]);

// Add this: Cache for all fetched options (Map for quick lookup by valueKey)
const cachedOptions = ref(new Map());

// Search functionality
const { searchResources, isLoading } = useSearchResources();
const searchableOptions = ref([...props.options]);
const currentSearchTerm = ref('');

// Updated ensure function to also check cachedOptions
const ensureSelectedOptionInOptions = () => {
    if (!props.modelValue) return;

    const reduceFn = props.reduce || ((opt) => opt ? opt[props.valueKey] : null);
    const selectedValue = Array.isArray(props.modelValue) ? props.modelValue : props.modelValue;

    // Find if the selected option is already in searchableOptions
    const selectedOptionExists = searchableOptions.value.some(option => reduceFn(option) === selectedValue);

    if (!selectedOptionExists) {
        // First, try props.options
        let selectedOption = props.options.find(option => reduceFn(option) === selectedValue);

        // If not found, try cachedOptions
        if (!selectedOption) {
            selectedOption = cachedOptions.value.get(selectedValue);
        }

        if (selectedOption) {
            // Add to the front to prioritize
            searchableOptions.value = [selectedOption, ...searchableOptions.value.filter(opt => reduceFn(opt) !== selectedValue)];
        }
    }
};

// Existing watches remain the same
watch(() => props.options, (newOptions) => {
    if (!props.searchable) {
        searchableOptions.value = [...newOptions];
    } else {
        ensureSelectedOptionInOptions();
    }
}, { immediate: true });

watch(() => props.modelValue, () => {
    if (props.searchable) {
        ensureSelectedOptionInOptions();
    }
}, { immediate: true });

// Define reduceInternal function
const reduceInternal = (option) => {
    if (props.reduce) {
        return props.reduce(option);
    }
    return option ? option[props.valueKey] : null;
};

const handleSearch = async (searchTerm) => {
    currentSearchTerm.value = searchTerm;

    if (!props.searchable || !props.resourceType) {
        return;
    }

    try {
        const results = await searchResources(
            searchTerm,
            props.options,
            props.resourceType,
            {
                labelKey: props.labelKey,
                valueKey: props.valueKey,
                searchFields: props.searchFields,
                ...props.searchOptions
            }
        );

        // Add new results to cachedOptions (avoids duplicates)
        results.forEach(opt => {
            const key = props.reduce ? props.reduce(opt) : opt[props.valueKey];
            if (!cachedOptions.value.has(key)) {
                cachedOptions.value.set(key, opt);
            }
        });

        searchableOptions.value = results;
    } catch (error) {
        console.error('Search error:', error);
        searchableOptions.value = [...props.options];
    } finally {
        // Ensure selected is included after updating options
        ensureSelectedOptionInOptions();
    }
};

// Optional: If you need to handle pre-set modelValue (e.g., editing forms) where the option isn't in initial props.options or cached yet,
// you could add an async fetch here in ensureSelectedOptionInOptions if (!selectedOption) { ... fetch single by ID ... }.
// But that requires backend support (e.g., modify searchRemote to handle { id: props.modelValue } in additionalParams).
// For now, this fixes the post-search selection issue.
</script>

<!-- Styles specific to vue-select dropdown -->
<style scoped>
    /* Keep the menu above surrounding UI - higher z-index for modals */
:deep(.vs__dropdown-menu) {
    z-index: 9999 !important;
    position: absolute !important;
}

/* Ensure the dropdown toggle is clickable */
:deep(.vs__dropdown-toggle) {
    cursor: pointer !important;
}

/* Ensure dropdown options are clickable */
:deep(.vs__dropdown-option) {
    cursor: pointer !important;
}

/* Ensure the entire dropdown container is interactive */
:deep(.vs__dropdown) {
    position: relative !important;
    z-index: 1 !important;
}

/* Fix any potential overflow issues in modals */
:deep(.vs__dropdown-menu) {
    max-height: 200px !important;
    overflow-y: auto !important;
}

/* --- Compact control height (match Tailwind inputs) --- */
/* If your inputs are h-10 (2.5rem), keep min-height but remove extra inner spacing.
   If you want slightly shorter (h-9 ~ 2.25rem), use the alternative block below. */

:deep(.vs__dropdown-toggle) {
    min-height: 2.5rem;           /* match h-10; change to 2.25rem for h-9 */
    padding-top: 0;                /* vue-select adds vertical padding via children; zero here */
    padding-bottom: 0;
}

/* Remove extra vertical margins inside the control */
:deep(.vs__selected),
:deep(.vs__search) {
    margin-top: 0;
    margin-bottom: 0;
    padding-top: 0.375rem;         /* = py-1.5 */
    padding-bottom: 0.375rem;
    line-height: 1.25rem;          /* text leading to avoid clipping */
    font-size: 0.875rem;           /* text-sm */
}

/* Tighten the right side (indicator & clear) so it doesn't add height */
:deep(.vs__actions) {
    padding-top: 0.25rem;          /* py-1 */
    padding-bottom: 0.25rem;
}

/* Ensure the control border looks like your inputs */
:deep(.vs__dropdown-toggle) {
    border-color: rgb(209 213 219);   /* Tailwind gray-300 */
    border-radius: 0.375rem;          /* rounded-md */
    box-shadow: 0 0 0 0 rgba(0,0,0,0);
}

/* Optional hover/focus parity with Tailwind inputs */
:deep(.vs--open .vs__dropdown-toggle),
:deep(.vs__dropdown-toggle:focus-within) {
    border-color: rgb(99 102 241);    /* indigo-500 */
    box-shadow: 0 0 0 1px rgba(99,102,241,.25);
}

</style>
