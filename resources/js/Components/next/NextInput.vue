<template>
    <div class="relative">
        <!-- Outlined floating label over a real border gap. The frame is a
             <fieldset> and the label's clearance is its <legend>, so nothing
             is painted over anything — see resources/css/next-field.css. -->
        <div class="next-field" :class="{ 'next-field--error': Boolean(error), 'next-field--disabled': disabled }">
            <Input
                ref="inputRef"
                :id="id"
                :type="type"
                :autofocus="autofocus"
                v-model="model"
                :step="step"
                :disabled="disabled"
                :readonly="readonly"
                :autocomplete="autocomplete"
                :placeholder="placeholder || ' '"
                @click="handleClick"
                class="next-field-control peer relative z-0 block w-full appearance-none rounded-lg text-sm
                placeholder:text-transparent focus:ring-0 focus-visible:ring-0 focus-visible:ring-offset-0 focus:placeholder:text-muted-foreground
                disabled:cursor-not-allowed disabled:opacity-50"
            />

            <fieldset aria-hidden="true" class="next-field-outline">
                <legend v-if="label"><span>{{ label }}<span v-if="isRequired">*</span></span></legend>
            </fieldset>

            <label v-if="label" :for="id" class="next-field-label text-sm">{{ label }}<span
                v-if="isRequired" class="next-field-required">*</span></label>
        </div>

        <!-- error text -->
        <p v-if="error" class="mt-1 text-xs text-red-500">{{ error }}</p>
        <p v-else-if="hint && showHints" class="mt-1 text-xs text-muted-foreground">{{ hint }}</p>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Input from '@/Components/ui/input/Input.vue'

// Inline hints are a per-user preference (appearance.show_field_hints), on
// unless the user turns them off. Errors are never suppressed.
const showHints = computed(() => {
    try {
        return usePage().props.user_preferences?.appearance?.show_field_hints !== false
    } catch (e) {
        return true
    }
})

const props = defineProps({
    modelValue: [String, Number],
    label: { type: String, required: true },
    id: { type: String, default: () => `fi-${Math.random().toString(36).slice(2, 9)}` },
    type: { type: String, default: 'text' },
    autofocus: { type: Boolean, default: false },
    disabled: Boolean,
    isRequired: Boolean,
    readonly: { type: Boolean, default: false },
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

defineExpose({
    focus: () => inputRef.value?.focus?.(),
})
</script>

<style scoped>
/* Everything about the outline, the notch and the label now lives in
   resources/css/next-field.css, shared with NextSelect and NextTextarea —
   three copies of the same measurements is what kept letting them drift. */

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
