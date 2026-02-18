<template>
    <div class="relative">
        <!-- the actual input -->
        <Input
            ref="inputRef"
            :id="id"
            :type="type"
            v-model="model"
            :step="step"
            :disabled="disabled"
            :autocomplete="autocomplete"
            :placeholder="placeholder"
            @click="handleClick"
            class="peer block w-full border-border bg-background px-3 py-3 text-sm shadow-sm
            placeholder:text-transparent focus:placeholder:text-muted-foreground
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
import { computed, ref } from 'vue'
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
    step: { type: String, default: 'any' },
    hint: String,
    placeholder: String,
    input: Function,
})

const emit = defineEmits(['update:modelValue'])
const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v),
})

const inputRef = ref(null)
function handleClick(e) {
    // Call any click listener provided via $emit
    // Select input value if enabled
    if (!props.disabled && inputRef.value) {
        // The actual input ref may be on the nexted Input, so dig if necessary
        // Vue 3 <script setup> exposes .$el on the component ref
        let el = inputRef.value.$el || inputRef.value
        // If the inner element is an input, select it
        if (el && typeof el.querySelector === 'function') {
            const realInput = el.querySelector('input') || el
            if (realInput && typeof realInput.select === 'function') {
                realInput.select()
            }
        } else if (el && typeof el.select === 'function') {
            el.select()
        }
    }
    // Emit click upwards as before
    // NOTE: this still allows the user of the component to attach their own handler
    // after select is called
    emit('click', e)
}
</script>

<style scoped>
/* Match focus style with NextSelect */
:deep(input:focus),
:deep(input:focus-visible) {
    border-color: rgb(99 102 241);
    box-shadow: 0 0 0 1px rgba(99, 102, 241, 0.25);
}

/* Hide number input spinners only inside NextInput */
:deep(input[type="number"]) {
    appearance: textfield;
    -moz-appearance: textfield;
}

:deep(input[type="number"])::-webkit-outer-spin-button,
:deep(input[type="number"])::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
</style>
