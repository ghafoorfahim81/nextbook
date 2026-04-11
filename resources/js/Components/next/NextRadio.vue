<template>
    <div class="flex items-center gap-2">
        <input
            ref="radioRef"
            :id="id"
            type="radio"
            :value="value"
            :name="name"
            :disabled="disabled"
            :checked="isChecked"
            @change="handleChange"
            class="h-4 w-4 text-primary focus:ring-primary border-1 bg-background"
        />
        <label
            :for="id"
            class="text-sm font-medium text-foreground cursor-pointer"
            :class="{ 'opacity-50': disabled }"
        >
            {{ label }}
        </label> 
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'
import { shouldAutoFocusElement } from '@/lib/autofocus'

const props = defineProps({
    modelValue: [String, Number, Object],
    value: [String, Number, Object],
    label: { type: String, required: true },
    id: { type: String, default: () => `radio-${Math.random().toString(36).slice(2, 9)}` },
    name: { type: String, required: true },
    error: String,
    disabled: Boolean,
})

const emit = defineEmits(['update:modelValue'])
const radioRef = ref(null)

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

const focusRadio = () => {
    if (shouldAutoFocusElement(radioRef.value)) {
        radioRef.value?.focus?.()
    }
}

onMounted(() => {
    nextTick(() => {
        focusRadio()
        requestAnimationFrame(focusRadio)
        setTimeout(focusRadio, 50)
    })
})

defineExpose({
    focus: () => radioRef.value?.focus?.(),
})
</script>
