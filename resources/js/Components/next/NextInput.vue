<template>
    <div class="flex flex-col space-y-1 min-w-[150px]">
        <label :for="inputId" class="mb-1 cursor-pointer text-nowrap">
            {{ label }}
            <span v-if="isRequired" class="text-red-600 ms-[2px]">*</span>
        </label>
        <Input
            :id="inputId"
            :type="type??'text'"
            v-model="model"
            :placeholder="placeholder"
            :disabled="disabled"
        />
        <span v-if="error" class="text-red-500 text-sm">{{ error }}</span>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import Input from '@/Components/ui/input/Input.vue';

const props = defineProps({
    label: String,
    type: { type: String, default: 'text' },
    modelValue: [String, Number],
    placeholder: String,
    error: String,
    disabled: Boolean,
    isRequired: Boolean,
});

const emit = defineEmits(['update:modelValue']);
const inputId = ref(`input-${Math.random().toString(36).substring(2, 9)}`);

// computed wrapper for v-model
const model = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val),
});
</script>
