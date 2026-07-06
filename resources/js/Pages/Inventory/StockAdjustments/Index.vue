<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import DataTable from '@/Components/DataTable.vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDeleteResource } from '@/composables/useDeleteResource'
import { router } from '@inertiajs/vue3'
import { useToast } from '@/Components/ui/toast/use-toast'

const { t } = useI18n()
const { toast } = useToast()

const props = defineProps({
  adjustments: Object,
  filters: Object,
  filterOptions: Object,
})

const editItem = (item) => {
  if (item.status !== 'draft') {
    toast({
      title: t('general.error'),
      description: t('adjustment.only_draft_can_be_edited'),
      variant: 'error',
      class: 'bg-red-600 text-white',
    })
    return
  }
  router.visit(route('stock-adjustments.edit', item.id))
}

const { deleteResource } = useDeleteResource()
const deleteItem = (id) => {
  const adjustment = props.adjustments.data.find(a => a.id === id)
  if (adjustment && adjustment.status !== 'draft') {
    toast({
      title: t('general.error'),
      description: t('adjustment.only_draft_can_be_deleted'),
      variant: 'error',
      class: 'bg-red-600 text-white',
    })
    return
  }
  deleteResource('stock-adjustments.destroy', id, {
    title: t('general.delete', { name: t('adjustment.stock_adjustment') }),
  })
}

const showItem = (id) => {
  router.visit(route('stock-adjustments.show', id))
}

const columns = computed(() => ([
  { key: 'reference', label: t('adjustment.reference'), sortable: true },
  { key: 'date', label: t('general.date'), sortable: true },
  { key: 'type_label', label: t('adjustment.type') },
  { key: 'reason_label', label: t('adjustment.reason') },
  { key: 'warehouse.name', label: t('adjustment.warehouse') },
  { key: 'status_label', label: t('general.status') },
  {
    key: 'total_cost',
    label: t('adjustment.total_cost'),
    render: (row) => Number(row.total_cost || 0).toFixed(2),
  },
  {
    key: 'created_by.name',
    label: t('general.created_by'),
    render: (row) => row.created_by?.name ?? '-',
  },
  { key: 'actions', label: t('general.actions') },
]))

const filterFields = computed(() => ([
  {
    key: 'warehouse_id',
    label: t('adjustment.warehouse'),
    type: 'select',
    options: (props.filterOptions?.warehouses || []).map((w) => ({ id: w.id, name: w.name })),
  },
  {
    key: 'type',
    label: t('adjustment.type'),
    type: 'select',
    options: props.filterOptions?.types || [],
  },
  {
    key: 'reason',
    label: t('adjustment.reason'),
    type: 'select',
    options: (props.filterOptions?.reasons || []).map((r) => ({ id: r.id, name: r.name })),
  },
  {
    key: 'status',
    label: t('general.status'),
    type: 'select',
    options: props.filterOptions?.statuses || [],
  },
  {
    key: 'items.item_id',
    label: t('item.item'),
    type: 'select',
    options: (props.filterOptions?.items || []).map((i) => ({ id: i.id, name: i.name })),
  },
  { key: 'date', label: t('general.date'), type: 'daterange' },
  {
    key: 'created_by',
    label: t('general.created_by'),
    type: 'select',
    options: (props.filterOptions?.users || []).map((u) => ({ id: u.id, name: u.name })),
  },
]))
</script>

<template>
  <AppLayout :title="t('adjustment.stock_adjustments')">
    <DataTable
      can="stock_adjustment"
      :items="adjustments"
      :columns="columns"
      :filters="filters"
      :filterFields="filterFields"
      :title="t('adjustment.stock_adjustments')"
      :url="`stock-adjustments.index`"
      exportRoute="stock-adjustments.export"
      :showAddButton="true"
      :hasShow="true"
      @edit="editItem"
      @delete="deleteItem"
      @show="showItem"
      :addTitle="t('adjustment.stock_adjustment')"
      :addAction="'redirect'"
      :addRoute="'stock-adjustments.create'"
    />
  </AppLayout>
</template>
