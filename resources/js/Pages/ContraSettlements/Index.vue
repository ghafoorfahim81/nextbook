<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import DataTable from '@/Components/DataTable.vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { router } from '@inertiajs/vue3'
import { useDeleteResource } from '@/composables/useDeleteResource'

const { t } = useI18n()

const props = defineProps({
  contraSettlements: Object,
  filters: Object,
  filterOptions: Object,
})

const { deleteResource } = useDeleteResource()

const showItem = (id) => {
  router.visit(route('contra-settlements.show', id))
}

const deleteItem = (id) => {
  deleteResource('contra-settlements.destroy', id, {
    title: t('general.delete', { name: t('contra.contra_settlement') }),
    name: t('contra.contra_settlement'),
  })
}

const columns = computed(() => ([
  { key: 'number', label: t('general.number'), sortable: true },
  { key: 'customer_ledger_name', label: t('contra.customer_account'), sortable: false },
  { key: 'supplier_ledger_name', label: t('contra.supplier_account'), sortable: false },
  { key: 'amount', label: t('contra.offset_amount'), sortable: true },
  { key: 'currency_code', label: t('admin.currency.currency'), sortable: false },
  { key: 'status_label', label: t('general.status') },
  { key: 'date', label: t('general.date'), sortable: true },
  {
    key: 'created_by.name',
    label: t('general.created_by'),
    render: (row) => row.created_by?.name ?? '-',
  },
  { key: 'actions', label: t('general.actions') },
]))

const filterFields = computed(() => ([
  {
    key: 'customer_ledger_id',
    label: t('contra.customer_account'),
    type: 'select',
    options: (props.filterOptions?.customers || []).map((c) => ({ id: c.id, name: c.name })),
  },
  {
    key: 'supplier_ledger_id',
    label: t('contra.supplier_account'),
    type: 'select',
    options: (props.filterOptions?.suppliers || []).map((s) => ({ id: s.id, name: s.name })),
  },
  {
    key: 'currency_id',
    label: t('admin.currency.currency'),
    type: 'select',
    options: (props.filterOptions?.currencies || []).map((c) => ({ id: c.id, name: c.code })),
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
  <AppLayout :title="t('contra.contra_settlements')">
    <DataTable
      can="contra_settlements"
      :items="contraSettlements"
      :columns="columns"
      :filters="filters"
      :filterFields="filterFields"
      :title="t('contra.contra_settlements')"
      url="contra-settlements.index"
      :showAddButton="true"
      :hasShow="true"
      :hasEdit="false"
      @show="showItem"
      @delete="deleteItem"
      :addTitle="t('contra.contra_settlement')"
      addAction="redirect"
      addRoute="contra-settlements.create"
    />
  </AppLayout>
</template>
