<template>
    <div class="relative">
        <textarea
            ref="textareaRef"
            :id="id"
            rows="4"
            :name="name"
            :placeholder="placeholder ?? t('general.enter', { text: label })"
            :value="modelValue"
            @input="e => emit('update:modelValue', e.target.value)"
            :readonly="readonly ?? false"
            class="peer block min-h-[96px] w-full rounded-md border border-border bg-background px-3 pb-2 pt-5 text-sm shadow-sm
            placeholder:text-transparent focus:placeholder:text-muted-foreground focus:outline-none
            disabled:cursor-not-allowed disabled:opacity-50"
        />

        <label
            :for="id"
            class="pointer-events-none absolute start-3 top-2 z-10 rounded bg-background px-1 text-xs
         text-muted-foreground transition-all duration-150
         peer-focus:top-1 peer-focus:text-xs peer-focus:text-foreground
         peer-focus:opacity-100
         peer-[:not(:placeholder-shown)]:top-1
         peer-[:not(:placeholder-shown)]:text-xs peer-[:not(:placeholder-shown)]:opacity-100">
            {{ label }}
        </label>
    </div>
</template>

<script setup>
import { nextTick, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n';
import { shouldAutoFocusElement } from '@/lib/autofocus'

const { t } = useI18n();

const props = defineProps({
    modelValue: { type: String, default: '' },
    name: String,
    label: String,
    id: { type: String, default: () => `ta-${Math.random().toString(36).slice(2, 9)}` },
    readonly: Boolean,
    placeholder: String,
});

const emit = defineEmits(['update:modelValue']);

const textareaRef = ref(null)

const focusTextarea = () => {
    if (shouldAutoFocusElement(textareaRef.value)) {
        textareaRef.value?.focus?.()
    }
}

onMounted(() => {
    nextTick(() => {
        focusTextarea()
        requestAnimationFrame(focusTextarea)
        setTimeout(focusTextarea, 50)
    })
})

defineExpose({
    focus: () => textareaRef.value?.focus?.(),
})
</script>

<style scoped>
/* Match focus style with NextInput */
:deep(textarea:focus),
:deep(textarea:focus-visible) {
    border-color: rgb(99 102 241);
    box-shadow: 0 0 0 1px rgba(99, 102, 241, 0.25);
}
</style>
