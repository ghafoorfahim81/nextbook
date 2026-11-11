<script setup>
import { computed, ref, useSlots } from 'vue'
import { useI18n } from 'vue-i18n'

import { Download, ArrowUpDown, Plus } from 'lucide-vue-next'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select'
import NextDate from '@/Components/next/NextDatePicker.vue'
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
  addLabel: { type: String, default: '' },
  addRoute: { type: String, default: '' },
  dateFromLabel: { type: String, default: '' },
  dateToLabel: { type: String, default: '' },
})

const { t, locale } = useI18n()
const emit = defineEmits(['row-click'])

const slots = useSlots()
const dateFrom = ref('')
const dateTo = ref('')
const filteredRows = computed(() => (props.rows || []).filter((row) => {
  const date = row.date
  return (!dateFrom.value || date >= dateFrom.value)
    && (!dateTo.value || date <= dateTo.value)
}))

const {
  search,
  sortDirection,
  sortKey,
  perPage,
  paginatedRows,
  pagination,
  setPage,
} = useClientTable(filteredRows, {
  defaultSortKey: props.defaultSortKey,
  defaultSortDirection: props.defaultSortDirection,
  perPage: props.perPage,
})

const isRTL = computed(() => ['fa', 'ps', 'pa'].includes(locale.value))

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

const visitAddRoute = () => {
  if (props.addRoute) {
    window.location.href = props.addRoute
  }
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

      <div class="flex gap-2">
        <!-- Same look as the Export and New buttons on the module lists. -->
        <Button
          v-if="exportUrl"
          variant="outline"
          size="sm"
          class="h-9 gap-1 border-green-800 text-green-600 hover:bg-green-700 hover:text-white"
          @click="exportTable"
        >
          <Download class="h-4 w-4" />
          {{ exportButtonLabel }}
        </Button>
        <Button
          v-if="addRoute"
          variant="outline"
          class="h-9 border-primary text-primary hover:bg-primary hover:text-white"
          @click="visitAddRoute"
        >
          <Plus class="h-4 w-4" />
          {{ addLabel || t('general.add_new') }}
        </Button>
      </div>
    </div>

    <div class="grid gap-3 md:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)_auto_auto_auto_auto]">
      <Input
        v-model="search"
        :placeholder="searchLabel"
        :class="isRTL ? 'pl-8 w-full pr-10 border-1 text-primary rounded-md border-primary focus:border-none focus:ring-2 focus:ring-ring' :
                        'pl-8 w-full pr-20 text-primary rounded-md border-primary focus:border-none focus:ring-1 focus:ring-ring'"
      />

      <!-- The app's own select, not a native one: the browser draws a native
           option list in its system colours (grey on the dark theme) and
           keeps its arrow on the reading-start side in a right-to-left page. -->
      <Select v-model="sortKey">
        <SelectTrigger class="h-10 w-full border-input">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem
            v-for="option in sortOptions"
            :key="option.key"
            :value="option.key"
            class="data-[state=checked]:bg-primary data-[state=checked]:text-white data-[highlighted]:bg-purple-500 data-[highlighted]:text-white"
          >
            {{ option.label }}
          </SelectItem>
        </SelectContent>
      </Select>

      <NextDate v-model="dateFrom" :placeholder="dateFromLabel || t('general.from')" />
      <NextDate v-model="dateTo" :placeholder="dateToLabel || t('general.to')" />

      <Button variant="outline" class="gap-2" @click="sortDirection = sortDirection === 'asc' ? 'desc' : 'asc'">
        <ArrowUpDown class="h-4 w-4" />
        {{ sortDirection === 'asc' ? 'Asc' : 'Desc' }}
      </Button>

      <Select v-model="perPage">
        <SelectTrigger class="h-10 w-[84px] border-input">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem
            v-for="size in [10, 25, 50, 100]"
            :key="size"
            :value="size"
            class="data-[state=checked]:bg-primary data-[state=checked]:text-white data-[highlighted]:bg-purple-500 data-[highlighted]:text-white"
          >
            {{ size }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>

    <ReportDataTable
      :columns="columns"
      :rows="paginatedRows"
      :pagination="pagination"
      :empty-message="emptyMessage"
      :row-number-label="rowNumberLabel"
      :row-clickable="true"
      @page-change="setPage"
      @row-click="emit('row-click', $event)"
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
