<template>
    <div class="relative z-[100] w-full dark:bg-slate-50 dark:text-slate-500">
        <div class="relative">
            <v-select
                :id="id"
                :options="options"
                :label="labelKey"
                :reduce="reduceInternal"
                :modelValue="modelValue"
                @update:modelValue="val => emit('update:modelValue', val)"
                class="col-span-3 border border-gray-300 rounded-md"
                v-bind="$attrs"
            />
            <!-- Use your FloatingLabel component as-is -->
            <FloatingLabel
                :id="id"
                :label="floatingText"
            />
        </div>

        <span v-if="error" class="text-red-500 text-sm">
      {{ error }}
    </span>
    </div>
</template>

<script setup>
import FloatingLabel from "@/Components/next/FloatingLabel.vue";

/**
 * Props mirror your snippet but are reusable:
 * - v-model support via modelValue / update:modelValue
 * - options + labelKey + valueKey or custom reduceFn
 * - floatingText => text shown in FloatingLabel
 * - error => validation message
 */
const props = defineProps({
    modelValue: [String, Number, Object, Array, null],
    options: { type: Array, default: () => [] },
    labelKey: { type: String, default: 'name' },
    valueKey: { type: String, default: 'id' },
    reduceFn: { type: Function, default: null },
    id: { type: String, default: () => 'sel-' + Math.random().toString(36).slice(2) },
    floatingText: { type: String, default: 'Label' },
    error: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const reduceInternal = (opt) => {
    if (props.reduceFn) return props.reduceFn(opt);
    if (opt !== null && typeof opt === 'object') return opt?.[props.valueKey];
    return opt;
};
</script>
