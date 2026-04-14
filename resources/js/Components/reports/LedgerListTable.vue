<script setup>
import { computed, useSlots } from 'vue'
import { useI18n } from 'vue-i18n'
import { Download, ArrowUpDown } from 'lucide-vue-next'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import ReportDataTable from '@/Components/reports/ReportDataTable.vue'
import { useClientTable } from '@/composables/useClientTable'

const props = defineProps({
  columns: { type: Array, required: true },
  defaultSortDirection: { type: String, default: 'desc' },
  defaultSortKey: { type: String, default: '' },
  emptyMessage: { type: String, required: true },
  exportLabel: { type: String, default: '' },
  exportUrl: { type: String, default: '' },
  perPage: { type: Number, default: 10 },
  rowNumberLabel: { type: String, default: '#' },
  rows: { type: Array, default: () => [] },
  searchPlaceholder: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  title: { type: String, default: '' },
})

const { t } = useI18n()
const slots = useSlots()

const {
  search,
  sortDirection,
  sortKey,
  perPage,
  paginatedRows,
  pagination,
  setPage,
} = useClientTable(computed(() => props.rows), {
  defaultSortKey: props.defaultSortKey,
  defaultSortDirection: props.defaultSortDirection,
  perPage: props.perPage,
})

const sortOptions = computed(() => (props.columns || [])
  .filter((column) => column.key && column.key !== 'actions')
  .map((column) => ({
    key: column.key,
    label: column.label,
  })))

const searchLabel = computed(() => props.searchPlaceholder || t('general.search'))
const exportButtonLabel = computed(() => props.exportLabel || t('report.export_excel'))
const customCellColumns = computed(() => (props.columns || []).filter((column) => slots[`cell-${column.key}`]))

const exportTable = () => {
  if (!props.exportUrl) {
    return
  }

  window.location.href = props.exportUrl
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div class="space-y-1">
        <h3 v-if="title" class="text-sm font-semibold uppercase tracking-[0.18em] text-muted-foreground">
          {{ title }}
        </h3>
        <p v-if="subtitle" class="text-sm text-muted-foreground">
          {{ subtitle }}
        </p>
      </div>

      <Button v-if="exportUrl" class="gap-2" @click="exportTable">
        <Download class="h-4 w-4" />
        {{ exportButtonLabel }}
      </Button>
    </div>

    <div class="grid gap-3 md:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)_auto_auto]">
      <Input
        v-model="search"
        :placeholder="searchLabel"
        class="bg-background"
      />

      <select
        v-model="sortKey"
        class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm outline-none"
      >
        <option v-for="option in sortOptions" :key="option.key" :value="option.key">
          {{ option.label }}
        </option>
      </select>

      <Button variant="outline" class="gap-2" @click="sortDirection = sortDirection === 'asc' ? 'desc' : 'asc'">
        <ArrowUpDown class="h-4 w-4" />
        {{ sortDirection === 'asc' ? 'Asc' : 'Desc' }}
      </Button>

      <select
        v-model="perPage"
        class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm outline-none"
      >
        <option :value="10">10</option>
        <option :value="25">25</option>
        <option :value="50">50</option>
        <option :value="100">100</option>
      </select>
    </div>

    <ReportDataTable
      :columns="columns"
      :rows="paginatedRows"
      :pagination="pagination"
      :empty-message="emptyMessage"
      :row-number-label="rowNumberLabel"
      @page-change="setPage"
    >
      <template
        v-for="column in customCellColumns"
        :key="column.key"
        #[`cell-${column.key}`]="slotProps"
      >
        <slot
          :name="`cell-${column.key}`"
          v-bind="slotProps"
        />
      </template>
    </ReportDataTable>
  </div>
</template>
