<script setup>
import { computed } from 'vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'

const props = defineProps({
  label: { type: String, required: true },
  value: { type: [Number, String], required: true },
  help: { type: String, default: '' },
  type: { type: String, default: 'money' },
})

const formattedValue = computed(() => {
  const numeric = Number(props.value || 0)

  if (props.type === 'count') {
    return numeric.toLocaleString(undefined, { maximumFractionDigits: 0 })
  }

  if (props.type === 'quantity') {
    return numeric.toLocaleString(undefined, {
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    })
  }

  return numeric.toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
})
</script>

<template>
  <Card class="border-border bg-card shadow-sm">
    <CardHeader class="space-y-2 pb-3">
      <CardTitle class="text-sm font-medium text-muted-foreground">
        {{ label }}
      </CardTitle>
    </CardHeader>
    <CardContent class="space-y-1">
      <div class="text-2xl font-semibold tracking-tight text-card-foreground">
        {{ formattedValue }}
      </div>
      <p v-if="help" class="text-xs text-muted-foreground">
        {{ help }}
      </p>
    </CardContent>
  </Card>
</template>
