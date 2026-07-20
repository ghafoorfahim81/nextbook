<template>
    <NextSelect
        multiple
        :model-value="modelValue"
        @update:model-value="val => emit('update:modelValue', val ?? [])"
        :options="translatedOptions"
        label-key="name"
        value-key="id"
        :reduce="o => o.id"
        :id="id"
        :floating-text="floatingText"
        :placeholder="placeholder || t('general.select')"
        :error="error"
        :disabled="disabled"
        :clearable="clearable"
        :show-arrow="showArrow"
    >
        <template #option="{ name, hex }">
            <span class="flex items-center gap-2">
                <span class="h-3.5 w-3.5 shrink-0 rounded-full border border-muted-foreground/40" :style="{ backgroundColor: hex }" />
                <span>{{ name }}</span>
            </span>
        </template>
        <template #selected-option="{ name, hex }">
            <span class="flex items-center gap-1.5">
                <span class="h-3 w-3 shrink-0 rounded-full border border-muted-foreground/40" :style="{ backgroundColor: hex }" />
                <span>{{ name }}</span>
            </span>
        </template>
    </NextSelect>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import NextSelect from '@/Components/next/NextSelect.vue'
import { COLOR_OPTIONS } from '@/constants/colors'

const { t } = useI18n()

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    options: { type: Array, default: () => COLOR_OPTIONS },
    id: { type: String, default: () => 'color-sel-' + Math.random().toString(36).slice(2) },
    floatingText: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    error: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    clearable: { type: Boolean, default: true },
    showArrow: { type: Boolean, default: true },
})

const emit = defineEmits(['update:modelValue'])

const translatedOptions = computed(() =>
    props.options.map(o => ({
        id: o.value,
        name: t(`colors.${o.value}`),
        hex: o.hex,
    }))
)
</script>
