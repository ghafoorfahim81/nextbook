<script setup>
import { computed } from 'vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import ReportFilterField from '@/Components/reports/ReportFilterField.vue'

const props = defineProps({
  label: { type: String, required: true },
  modelValue: {
    type: [String, Number, Object, Boolean, Array],
    default: null,
  },
  options: { type: Array, default: () => [] },
  labelKey: { type: String, default: 'name' },
  valueKey: { type: String, default: 'id' },
  placeholder: { type: String, default: '' },
  clearable: { type: Boolean, default: true },
  emptyValue: {
    type: [String, Number, Object, Boolean, Array],
    default: '',
  },
  reduce: { type: Function, default: null },
})

const emit = defineEmits(['update:modelValue'])

const normalizedModel = computed(() => (props.modelValue === '' ? null : props.modelValue))

function resolveValue(option) {
  if (props.reduce) {
    return props.reduce(option)
  }

  return option?.[props.valueKey] ?? null
}

function handleUpdate(value) {
  emit('update:modelValue', value ?? props.emptyValue)
}
</script>

<template>
    <NextSelect
      :model-value="normalizedModel"
      :options="options"
      :label-key="labelKey"
      :value-key="valueKey"
      :reduce="resolveValue"
      :placeholder="placeholder || label"
      :clearable="clearable"
      :floating-text="''"
      @update:modelValue="handleUpdate"
    />
</template>
