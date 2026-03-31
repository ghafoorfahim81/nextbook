<script setup>
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'
import { Badge } from '@/Components/ui/badge'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
  rows: { type: Array, default: () => [] },
  rowType: { type: String, default: 'transaction' },
})

const { t } = useI18n()

function amountValue(row) {
  return Number(row.amount || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
}

function quantityValue(row) {
  return Number(row.quantity || 0).toLocaleString(undefined, {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  })
}
</script>

<template>
  <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
    <div class="relative border-b border-border px-5 py-4">
      <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary via-primary/80 to-primary/45" />
      <div class="text-base font-semibold text-card-foreground">{{ title }}</div>
      <div v-if="description" class="text-sm text-muted-foreground">{{ description }}</div>
    </div>

    <div class="overflow-x-auto p-2">
      <Table>
        <TableHeader>
          <TableRow class="border-border">
            <TableHead class="text-muted-foreground">{{ rowType === 'stock' ? t('dashboard.table.item') : t('dashboard.table.reference') }}</TableHead>
            <TableHead class="text-muted-foreground">{{ rowType === 'stock' ? t('dashboard.table.location') : t('dashboard.table.party') }}</TableHead>
            <TableHead class="text-muted-foreground">{{ rowType === 'stock' ? t('dashboard.table.movement') : t('dashboard.table.status') }}</TableHead>
            <TableHead class="text-muted-foreground">{{ t('dashboard.table.date') }}</TableHead>
            <TableHead class="text-right text-muted-foreground">{{ rowType === 'stock' ? t('dashboard.table.quantity') : t('dashboard.table.amount') }}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-if="!rows.length" class="border-border">
            <TableCell colspan="5" class="py-8 text-center text-sm text-muted-foreground">
              {{ t('dashboard.table.no_records') }}
            </TableCell>
          </TableRow>
          <TableRow v-for="row in rows" :key="row.id" class="border-border">
            <TableCell class="font-medium text-card-foreground">
              {{ rowType === 'stock' ? row.item_name : `#${row.number}` }}
            </TableCell>
            <TableCell class="text-muted-foreground">
              {{ rowType === 'stock' ? row.warehouse_name : row.party_name }}
            </TableCell>
            <TableCell>
              <Badge variant="outline" class="capitalize border-border text-foreground">{{ rowType === 'stock' ? row.movement_type : row.status }}</Badge>
            </TableCell>
            <TableCell class="text-muted-foreground">{{ row.date }}</TableCell>
            <TableCell class="text-right font-medium text-card-foreground">
              {{ rowType === 'stock' ? quantityValue(row) : amountValue(row) }}
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>
  </div>
</template>
