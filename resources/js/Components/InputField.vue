<!-- InputField.vue -->
<template>
    <div :class="['flex flex-col space-y-1 min-w-[150px]', type === 'file' && 'hover:cursor-pointer']">
        <label :for="id" class="mb-1 cursor-pointer text-nowrap">
            {{ label }}
        </label>
        <input
            :id="id"
            :type="type"
            :min="type === 'number' ? min : undefined"
            :value="modelValue"
            :placeholder="placeholder"
            :class="['rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50', type === 'file' && 'hover:cursor-pointer']"
            :disabled="disabled"
            @input="$emit('update:modelValue', $event.target.value)"
            ref="inputRef"
        />
        <div v-if="error" :class="['text-red-500', errorExtraSmallText ? 'text-xs' : 'text-sm']">
            {{ error }}
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
    id: {
        type: String,
        required: true
    },
    label: {
        type: String,
        required: true
    },
    type: {
        type: String,
        default: 'text'
    },
    min: {
        type: Number,
        default: 1
    },
    modelValue: {
        type: [String, Number],
        default: ''
    },
    placeholder: {
        type: String,
        default: 'Enter text'
    },
    error: {
        type: String,
        default: ''
    },
    disabled: {
        type: Boolean,
        default: false
    },
    errorExtraSmallText: {
        type: Boolean,
        default: false
    }
});

const inputRef = ref(null);

defineEmits(['update:modelValue']);
</script>
