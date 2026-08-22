<script setup>
import { computed } from 'vue'
import { useCountUp } from '@/composables/useCountUp'
import { formatCompact, formatNumber } from './format'

// Renders a figure that counts up to its real value on load. Text only — the
// caller owns the typography, so this drops straight into a heading or a cell.
const props = defineProps({
  value: { type: [Number, String], default: 0 },
  type: { type: String, default: 'money' },
  compact: { type: Boolean, default: false },
})

const counted = useCountUp(() => Number(props.value || 0))

const display = computed(() => (props.compact
  ? formatCompact(counted.value, props.type)
  : formatNumber(counted.value, props.type)))
</script>

<template>{{ display }}</template>
