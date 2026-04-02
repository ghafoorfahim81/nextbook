<script setup>
import { computed, ref, watchEffect } from 'vue';

defineOptions({
    inheritAttrs: false,
});

const emit = defineEmits(['update:checked', 'change']);

const props = defineProps({
    checked: {
        type: [Array, Boolean],
        default: false,
    },
    value: {
        type: [String, Number],
        default: null,
    },
    indeterminate: {
        type: Boolean,
        default: false,
    },
});

const input = ref(null);

const isChecked = computed(() => {
    if (Array.isArray(props.checked)) {
        return props.checked.includes(props.value);
    }

    return Boolean(props.checked);
});

watchEffect(() => {
    if (input.value) {
        input.value.indeterminate = props.indeterminate;
    }
});

const handleChange = (event) => {
    const checked = event.target.checked;

    if (Array.isArray(props.checked)) {
        const nextValues = checked
            ? Array.from(new Set([...props.checked, props.value]))
            : props.checked.filter((item) => item !== props.value);

        emit('update:checked', nextValues);
        emit('change', event);
        return;
    }

    emit('update:checked', checked);
    emit('change', event);
};
</script>


<template>
    <input
        ref="input"
        type="checkbox"
        :checked="isChecked"
        :value="value"
        v-bind="$attrs"
        @change="handleChange"
        class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800"
    >
</template>
