<template>
    <div class="flex items-center space-x-2">
        <input
            :id="id"
            type="radio"
            :value="value"
            :name="name"
            :disabled="disabled"
            :checked="isChecked"
            @change="handleChange"
            class="h-4 w-4 text-primary focus:ring-primary border-gray-300"
        />
        <label
            :for="id"
            class="text-sm font-medium text-gray-700 cursor-pointer"
            :class="{ 'opacity-50': disabled }"
        >
            {{ label }}
        </label>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    modelValue: [String, Number, Object],
    value: [String, Number, Object],
    label: { type: String, required: true },
    id: { type: String, default: () => `radio-${Math.random().toString(36).slice(2, 9)}` },
    name: { type: String, required: true },
    disabled: Boolean,
})

const emit = defineEmits(['update:modelValue'])

const isChecked = computed(() => {
    if (typeof props.modelValue === 'object' && typeof props.value === 'object') {
        return JSON.stringify(props.modelValue) === JSON.stringify(props.value)
    }
    return props.modelValue === props.value
})

const handleChange = (event) => {
    if (event.target.checked) {
        emit('update:modelValue', props.value)
    }
}
</script>
