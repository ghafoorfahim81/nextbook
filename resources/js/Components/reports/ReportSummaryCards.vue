<script setup>
const props = defineProps({
  cards: { type: Array, default: () => [] },
})

function formatValue(card) {
  if (card.type === 'money') {
    return Number(card.value || 0).toLocaleString(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })
  }

  if (card.type === 'quantity') {
    return Number(card.value || 0).toLocaleString(undefined, {
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    })
  }

  if (card.type === 'integer') {
    return Number(card.value || 0).toLocaleString(undefined, {
      maximumFractionDigits: 0,
    })
  }

  return card.value ?? '-'
}
</script>

<template>
  <div v-if="cards.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    <div
      v-for="card in cards"
      :key="card.key"
      class="rounded-2xl border border-border bg-card px-4 py-4 text-left shadow-sm rtl:text-right"
    >
      <div class="text-sm text-muted-foreground">{{ card.label }}</div>
      <div class="mt-2 text-2xl font-semibold tracking-tight text-card-foreground">
        {{ formatValue(card) }}
      </div>
    </div>
  </div>
</template>
