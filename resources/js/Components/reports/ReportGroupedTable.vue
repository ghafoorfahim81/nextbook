<script setup>
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'

const props = defineProps({
  sections: { type: Array, default: () => [] },
  columns: { type: Array, default: () => [] },
  emptyMessage: { type: String, default: 'No rows found.' },
})

function formatValue(column, row) {
  const value = row?.[column.key]

  if (value === null || value === undefined || value === '') {
    return '-'
  }

  if (column.type === 'money') {
    return Number(value).toLocaleString(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })
  }

  if (column.type === 'quantity') {
    return Number(value).toLocaleString(undefined, {
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    })
  }

  if (column.type === 'integer') {
    return Number(value).toLocaleString(undefined, { maximumFractionDigits: 0 })
  }

  if (column.type === 'balance') {
    const amount = Number(value || 0)

    if (amount === 0) {
      return '0.00'
    }

    return `${Math.abs(amount).toLocaleString(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })} ${amount >= 0 ? 'Dr' : 'Cr'}`
  }

  return value
}
</script>

<template>
  <div v-if="!sections.length" class="rounded-2xl border border-border bg-card p-8 text-center text-sm text-muted-foreground shadow-sm">
    {{ emptyMessage }}
  </div>

  <div v-else class="space-y-6">
    <section
      v-for="section in sections"
      :key="section.key"
      class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
    >
      <div class="border-b border-border px-5 py-4">
        <h3 class="text-base font-semibold text-card-foreground">{{ section.label }}</h3>
      </div>

      <div class="overflow-x-auto">
        <Table>
          <TableHeader>
            <TableRow class="border-border">
              <TableHead
                v-for="column in columns"
                :key="column.key"
                class="text-muted-foreground rtl:text-right"
                :class="column.align === 'right' ? 'text-right' : ''"
              >
                {{ column.label }}
              </TableHead>
            </TableRow>
          </TableHeader>

          <TableBody>
            <TableRow v-if="!section.rows?.length" class="border-border">
              <TableCell :colspan="columns.length" class="py-8 text-center text-sm text-muted-foreground">
                {{ emptyMessage }}
              </TableCell>
            </TableRow>

            <TableRow
              v-for="(row, index) in section.rows || []"
              :key="`${section.key}-${row.account_name || index}`"
              class="border-border"
            >
              <TableCell
                v-for="column in columns"
                :key="column.key"
                class="text-card-foreground"
                :class="column.align === 'right' ? 'text-right' : ''"
              >
                {{ formatValue(column, row) }}
              </TableCell>
            </TableRow>

            <TableRow v-if="section.totals" class="border-border bg-muted/40 font-semibold">
              <TableCell
                v-for="column in columns"
                :key="`total-${column.key}`"
                class="text-card-foreground"
                :class="column.align === 'right' ? 'text-right' : ''"
              >
                <template v-if="column.key === 'account_name'">
                  {{ section.totals.account_name }}
                </template>
                <template v-else>
                  {{ formatValue(column, section.totals) }}
                </template>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </section>
  </div>
</template>
