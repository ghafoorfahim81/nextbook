<template>
    <div class="relative">
        <Input
            :id="id"
            :model-value="displayValue"
            @update:model-value="onInput"
            type="tel"
            inputmode="tel"
            autocomplete="tel"
            :placeholder="placeholder || mask"
            class="peer block w-full border-border bg-background px-3 py-3 text-sm shadow-sm placeholder:text-muted-foreground"
        />
        <label
            :for="id"
            class="pointer-events-none absolute start-3 top-0 z-10 -translate-y-1/2 rounded bg-background px-1 text-xs text-muted-foreground"
        >
            {{ label }}<span v-if="isRequired" class="ms-0.5 text-red-500">*</span>
        </label>
        <p v-if="error" class="mt-1 text-xs text-red-500">{{ error }}</p>
        <!-- <p v-else class="mt-1 text-xs text-muted-foreground">{{ hint || `Mask: ${mask}` }}</p> -->
    </div>
</template>

<script setup>
import { computed } from 'vue';
import Input from '@/Components/ui/input/Input.vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, required: true },
    id: { type: String, default: () => `phone-${Math.random().toString(36).slice(2, 9)}` },
    error: String,
    hint: String,
    placeholder: String,
    mask: { type: String, default: '(###) ### - ####' },
    isRequired: Boolean,
});

const emit = defineEmits(['update:modelValue']);
const displayValue = computed(() => format(props.modelValue));

function onInput(value) {
    emit('update:modelValue', digits(value));
}

function format(value) {
    const valueDigits = digits(value);
    if (!valueDigits) return '';

    const parts = [];
    if (valueDigits.length) parts.push(`(${valueDigits.slice(0, 3)}`);
    if (valueDigits.length >= 3) parts[0] += ')';
    if (valueDigits.length > 3) parts.push(valueDigits.slice(3, 6));
    if (valueDigits.length > 6) parts.push(`- ${valueDigits.slice(6, 10)}`);

    return parts.join(' ');
}

function digits(value) {
    return String(value || '').replace(/\D/g, '').slice(0, 10);
}
</script>
