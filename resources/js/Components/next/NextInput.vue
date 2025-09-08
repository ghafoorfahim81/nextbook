<template>
    <div class="relative">
        <!-- the actual input -->
        <Input
            :id="id"
            :type="type"
            v-model="model"
            :disabled="disabled"
            :autocomplete="autocomplete"
            placeholder=" "
        class="peer block w-full rounded-md border border-2 border-black border-input bg-background px-3 py-3 text-sm shadow-sm
        focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2
        disabled:cursor-not-allowed disabled:opacity-50"
        />

        <!-- floating label -->
        <label
            :for="id"
            class="pointer-events-none absolute left-3 top-1/2 z-10 -translate-y-1/2 rounded bg-background px-1
         text-muted-foreground transition-all duration-150
         peer-placeholder-shown:top-1/2 peer-placeholder-shown:text-sm
         peer-focus:top-0 peer-focus:-translate-y-1/2 peer-focus:text-xs peer-focus:text-foreground
         peer-[:not(:placeholder-shown)]:top-0
         peer-[:not(:placeholder-shown)]:-translate-y-1/2
         peer-[:not(:placeholder-shown)]:text-xs">
            {{ label }}
            <span v-if="isRequired" class="text-red-600 ms-[2px]">*</span>
        </label>


        <!-- error text -->
        <p v-if="error" class="mt-1 text-xs text-red-500">{{ error }}</p>
        <p v-else-if="hint" class="mt-1 text-xs text-muted-foreground">{{ hint }}</p>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import Input from '@/Components/ui/input/Input.vue'

const props = defineProps({
    modelValue: [String, Number],
    label: { type: String, required: true },
    id: { type: String, default: () => `fi-${Math.random().toString(36).slice(2, 9)}` },
    type: { type: String, default: 'text' },
    disabled: Boolean,
    isRequired: Boolean,
    autocomplete: { type: String, default: 'off' },
    error: String,
    hint: String,
})

const emit = defineEmits(['update:modelValue'])
const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v),
})
</script>
