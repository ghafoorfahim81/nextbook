<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Button } from '@/Components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'

const props = defineProps({
  columns: { type: Array, required: true },
  rows: { type: Array, default: () => [] },
  pagination: { type: Object, required: true },
  emptyMessage: { type: String, required: true },
  rowNumberLabel: { type: String, default: '#' },
  showRowNumber: { type: Boolean, default: true },
})

const emit = defineEmits(['page-change'])
const { t } = useI18n()

const displayColumns = computed(() => {
  const columns = props.columns || []

  if (!props.showRowNumber) {
    return columns
  }

  return [
    {
      key: '__row_number',
      label: props.rowNumberLabel,
      align: 'right',
      type: 'integer',
    },
    ...columns,
  ]
})

const pages = computed(() => {
  const current = Number(props.pagination.current_page || 1)
  const last = Number(props.pagination.last_page || 1)
  const start = Math.max(1, current - 2)
  const end = Math.min(last, start + 4)
  const first = Math.max(1, end - 4)

  return Array.from({ length: end - first + 1 }, (_, index) => first + index)
})

function getRowNumber(index) {
  const from = Number(props.pagination.from || 0)
  return from > 0 ? from + index : index + 1
}

function formatValue(column, row) {
  const value = row[column.key]

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
    return `${Math.abs(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${amount >= 0 ? 'Dr' : 'Cr'}`
  }

  return value
}
</script>

<template>
  <div class="rounded-2xl border border-border bg-card shadow-sm">
    <div class="overflow-x-auto p-3">
      <Table>
        <TableHeader>
          <TableRow class="border-border">
            <TableHead
              v-for="column in displayColumns"
              :key="column.key"
              class="text-muted-foreground rtl:text-right"
              :class="column.align === 'right' ? 'text-right' : ''"
            >
              {{ column.label }}
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-if="!rows.length" class="border-border">
            <TableCell :colspan="displayColumns.length" class="py-8 text-center text-sm text-muted-foreground">
              {{ emptyMessage }}
            </TableCell>
          </TableRow>
          <TableRow v-for="(row, index) in rows" :key="row.id || row.reference_id || index" class="border-border">
            <TableCell
              v-for="column in displayColumns"
              :key="column.key"
              class="text-card-foreground"
              :class="column.align === 'right' ? 'text-right' : ''"
            >
              <template v-if="column.key === '__row_number'">
                {{ getRowNumber(index) }}
              </template>
              <slot
                v-else
                :name="`cell-${column.key}`"
                :row="row"
                :value="formatValue(column, row)"
                :column="column"
                :row-index="index"
              >
                {{ formatValue(column, row) }}
              </slot>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <div class="flex flex-col gap-3 border-t border-border px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
      <p class="text-sm text-muted-foreground">
        {{ t('report.table.showing', {
          from: pagination.from || 0,
          to: pagination.to || 0,
          total: pagination.total || 0,
        }) }}
      </p>

      <div class="flex flex-wrap items-center gap-2">
        <Button
          variant="outline"
          size="sm"
          :disabled="pagination.current_page <= 1"
          @click="emit('page-change', pagination.current_page - 1)"
        >
          {{ t('report.table.previous') }}
        </Button>

        <Button
          v-for="page in pages"
          :key="page"
          :variant="page === pagination.current_page ? 'default' : 'outline'"
          size="sm"
          @click="emit('page-change', page)"
        >
          {{ page }}
        </Button>

        <Button
          variant="outline"
          size="sm"
          :disabled="pagination.current_page >= pagination.last_page"
          @click="emit('page-change', pagination.current_page + 1)"
        >
          {{ t('report.table.next') }}
        </Button>
      </div>
    </div>
  </div>
</template>
