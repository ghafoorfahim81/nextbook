<template>
    <div class="relative">
        <!-- the actual input -->
        <Input
            :id="id"
            :type="type"
            v-model="model"
            :disabled="disabled"
            :autocomplete="autocomplete"
            :placeholder="placeholder"
            @input="input"
        class="peer block w-full rounded-md border border-2 border-black border-input bg-background px-3 py-3 text-sm shadow-sm
        placeholder:text-transparent focus:placeholder:text-muted-foreground
        focus:border-violet-500 focus:outline-none
        disabled:cursor-not-allowed disabled:opacity-50"
        />

        <!-- floating label -->
        <label
            :for="id"
            class="pointer-events-none absolute start-3 top-1/2 z-10 -translate-y-1/2 rounded bg-background px-1
         text-muted-foreground transition-all duration-150
         peer-placeholder-shown:top-1/2 peer-placeholder-shown:text-sm
         peer-focus:top-0 peer-focus:-translate-y-1/2 peer-focus:text-xs peer-focus:text-foreground
         peer-focus:opacity-100
         peer-[:not(:placeholder-shown)]:top-0
         peer-[:not(:placeholder-shown)]:-translate-y-1/2
         peer-[:not(:placeholder-shown)]:text-xs peer-[:not(:placeholder-shown)]:opacity-100">
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
    placeholder: String,
    input: Function,
})

const emit = defineEmits(['update:modelValue'])
const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v),
})
</script>

<style scoped>
/* Hide number input spinners only inside NextInput */
:deep(input[type="number"]) { appearance: textfield; -moz-appearance: textfield; }
:deep(input[type="number"])::-webkit-outer-spin-button,
:deep(input[type="number"])::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
</style>
