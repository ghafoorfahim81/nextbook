<template>
    <div class="grid w-full gap-1.5">
        <label
            v-if="label"
            :for="textareaId"
            class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
        >
            {{ label }}
            <span v-if="isRequired" class="text-red-600 ms-[2px]">*</span>
        </label>
        <textarea
            :id="textareaId"
            :value="modelValue"
            @input="e => emit('update:modelValue', e.target.value)"
            :placeholder="placeholder"
            :rows="rows"
            :disabled="disabled"
            class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background
             placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring
             focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
        />
        <p v-if="error" class="text-sm text-red-500">{{ error }}</p>
    </div>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({
    label: String,
    modelValue: String,
    placeholder: String,
    rows: {
        type: Number,
        default: 3,
    },
    error: String,
    disabled: Boolean,
    isRequired: Boolean,
})

const emit = defineEmits(['update:modelValue'])

const textareaId = ref(`textarea-${Math.random().toString(36).substring(2, 9)}`)
</script>
