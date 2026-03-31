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
  <Card class="group relative overflow-hidden border-border bg-gradient-to-b from-card to-card/95 shadow-sm transition-all duration-200 hover:shadow-md hover:translate-y-[-5px] hover:bg-primary/10">
    <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary via-primary/80 to-primary/45" />
    <div class="absolute inset-y-0 end-0 w-20 bg-[radial-gradient(circle_at_center,rgba(168,85,247,0.08),transparent_70%)] opacity-0 transition-opacity duration-200 " />
    <CardHeader class="space-y-2 pb-2">
      <CardTitle class="text-sm font-medium text-muted-foreground">
        {{ label }}
      </CardTitle>
    </CardHeader>
    <CardContent class="space-y-2">
      <div class="text-2xl font-semibold tracking-tight text-card-foreground">
        {{ formattedValue }}
      </div>
      <p v-if="help" class="max-w-[26ch] text-xs leading-5 text-muted-foreground">
        {{ help }}
      </p>
    </CardContent>
  </Card>
</template>
