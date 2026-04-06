<script setup>
import NextDatePicker from '@/Components/next/NextDatePicker.vue'
import ReportFilterField from '@/Components/reports/ReportFilterField.vue'

defineProps({
  label: { type: String, required: true },
  modelValue: { type: [String, Number, Date], default: null },
})

const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <ReportFilterField :label="label">
    <NextDatePicker
      :model-value="modelValue"
      @update:modelValue="emit('update:modelValue', $event)"
    />
  </ReportFilterField>
</template>
