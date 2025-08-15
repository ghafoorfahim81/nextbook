<template>
    <div class="relative w-full dark:bg-slate-50 dark:text-slate-500">
        <div class="relative">
            <v-select
                :id="id"
                :options="options"
                :label="labelKey"
                :reduce="reduceInternal"
                :modelValue="modelValue"
                @update:modelValue="val => emit('update:modelValue', val)"
                append-to-body
                :close-on-select="true"
                :clearable="false"
                class="col-span-3 border border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                v-bind="$attrs"
            />
            <!-- Floating label sits *under* the dropdown and ignores pointer events -->
            <FloatingLabel
                :id="id"
                :label="floatingText"
                class="pointer-events-none z-10"
            />
        </div>

        <span v-if="error" class="mt-1 block text-red-500 text-sm">
      {{ error }}
    </span>
    </div>
</template>

<script setup>
import FloatingLabel from "@/Components/next/FloatingLabel.vue";

const props = defineProps({
    modelValue: [String, Number, Object, Array, null],
    options: { type: Array, default: () => [] },
    labelKey: { type: String, default: "name" },
    valueKey: { type: String, default: "id" },
    reduceFn: { type: Function, default: null },
    id: { type: String, default: () => "sel-" + Math.random().toString(36).slice(2) },
    floatingText: { type: String, default: "" },
    error: { type: String, default: "" },
});

const emit = defineEmits(["update:modelValue"]);

const reduceInternal = (opt) => {
    if (props.reduceFn) return props.reduceFn(opt);
    if (opt !== null && typeof opt === "object") return opt?.[props.valueKey];
    return opt;
};
</script>

<!-- Styles specific to vue-select dropdown -->
<style scoped>
<style scoped>
    /* Keep the menu above surrounding UI */
:deep(.vs__dropdown-menu) { z-index: 2000; }

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
