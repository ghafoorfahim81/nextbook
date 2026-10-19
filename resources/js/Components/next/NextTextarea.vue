<template>
    <div class="relative">
        <!-- Same notched outline as NextInput and NextSelect; the shared rules
             live in resources/css/next-field.css. A textarea has no empty
             state worth floating out of — a label centred in four rows of box
             reads as body text — so it stays floated and shows its real
             placeholder underneath. -->
        <div class="next-field next-field--floated"
             :class="{ 'next-field--error': Boolean(error), 'next-field--disabled': disabled }">
            <textarea
                ref="textareaRef"
                :id="id"
                :rows="rows"
                :name="name"
                :placeholder="placeholder ?? t('general.enter', { text: label })"
                :value="modelValue"
                @input="e => emit('update:modelValue', e.target.value)"
                :readonly="readonly ?? false"
                :disabled="disabled"
                class="next-field-control peer block min-h-[96px] w-full rounded-lg pb-2 pt-6 text-sm
                placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50"
            />

            <fieldset aria-hidden="true" class="next-field-outline">
                <legend v-if="label"><span>{{ label }}<span v-if="isRequired">*</span></span></legend>
            </fieldset>

            <label v-if="label" :for="id" class="next-field-label text-sm">{{ label }}<span
                v-if="isRequired" class="next-field-required">*</span></label>
        </div>

        <p v-if="error" class="mt-1 text-xs text-red-500">{{ error }}</p>
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
    disabled: Boolean,
    isRequired: Boolean,
    error: String,
    rows: { type: [String, Number], default: 4 },
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
/* The label sits on the top border, so the first line of text has to start
   below it — the shared sheet only owns the horizontal padding. */
.next-field-control {
    padding-top: 1.5rem;
}
</style>
