<script setup>
const props = defineProps({
  sections: { type: Array, default: () => [] },
  emptyMessage: { type: String, default: 'No rows found.' },
})

function formatBalance(value) {
  return Number(value || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
}
</script>

<template>
  <div class="grid gap-4 xl:grid-cols-3">
    <div
      v-for="section in sections"
      :key="section.key"
      class="rounded-2xl border border-border bg-card shadow-sm"
    >
      <div class="border-b border-border px-5 py-4">
        <h3 class="text-base font-semibold text-card-foreground">{{ section.label }}</h3>
      </div>

      <div class="space-y-3 p-5">
        <div
          v-if="!section.rows?.length"
          class="rounded-xl border border-dashed border-border px-4 py-6 text-center text-sm text-muted-foreground"
        >
          {{ emptyMessage }}
        </div>

        <div
          v-for="row in section.rows || []"
          :key="`${section.key}-${row.account_name}`"
          class="flex items-center justify-between rounded-xl border border-border bg-background px-4 py-3"
        >
          <div class="font-medium text-card-foreground">{{ row.account_name }}</div>
          <div class="text-right font-semibold text-card-foreground">{{ formatBalance(row.balance) }}</div>
        </div>
      </div>
    </div>
  </div>
</template>
