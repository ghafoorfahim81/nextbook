<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import DataTable from '@/Components/DataTable.vue'
import { useDeleteResource } from '@/composables/useDeleteResource'
import { router } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  drawings: Object,
  filters: Object,
  filterOptions: Object,
})

const { t } = useI18n()

const columns = computed(() => [
  { key: 'number', label: t('general.number'), sortable: true },
  { key: 'date', label: t('general.date'), sortable: true },
  {
    key: 'owner.name',
    label: t('owner.owner'),
    render: (row) => row.owner?.name || '-',
  },
  {
    key: 'bank_account.name',
    label: t('general.bank_account'),
    render: (row) => row.bank_account?.name || '-',
  },
  {
    key: 'drawing_account.name',
    label: t('owner.drawing_account'),
    render: (row) => row.drawing_account?.name || '-',
  },
  {
    key: 'amount',
    label: t('general.amount'),
    render: (row) => `${row.currency?.symbol || ''} ${Number(row.amount || 0).toLocaleString()}`,
  },
  { key: 'narration', label: t('general.remarks') },
  { key: 'status', label: t('general.status') },
  { key: 'actions', label: t('general.action') },
])

const { deleteResource } = useDeleteResource()

const deleteItem = (id) => {
  deleteResource('drawings.destroy', id, {
    title: t('general.delete', { name: t('sidebar.owners.drawing') }),
    description: t('general.delete_description', { name: t('sidebar.owners.drawing') }),
    successMessage: t('general.delete_success', { name: t('sidebar.owners.drawing') }),
  })
}

const editItem = (item) => {
  router.visit(route('drawings.edit', item.id))
}

const viewItem = (id) => {
  router.visit(route('drawings.show', id))
}

const filterFields = computed(() => ([
  {
    key: 'owner_id',
    label: t('owner.owner'),
    type: 'select',
    options: (props.filterOptions?.owners || []).map((owner) => ({ id: owner.id, name: owner.name })),
  },
  {
    key: 'branch_id',
    label: t('general.branch'),
    type: 'select',
    options: (props.filterOptions?.branches?.data ?? props.filterOptions?.branches ?? []).map((branch) => ({ id: branch.id, name: branch.name })),
  },
  {
    key: 'bank_account_id',
    label: t('general.bank_account'),
    type: 'select',
    options: (props.filterOptions?.bankAccounts || []).map((a) => ({ id: a.id, name: a.name })),
  },
  {
    key: 'drawing_account_id',
    label: t('owner.drawing_account'),
    type: 'select',
    options: (props.filterOptions?.drawingAccounts || []).map((a) => ({ id: a.id, name: a.name })),
  },
  {
    key: 'transaction.currency_id',
    label: t('admin.currency.currency'),
    type: 'select',
    options: (props.filterOptions?.currencies || []).map((c) => ({ id: c.id, name: c.code })),
  },
  { key: 'date', label: t('general.date'), type: 'daterange' },
  {
    key: 'created_by',
    label: t('general.created_by'),
    type: 'select',
    options: (props.filterOptions?.users || []).map((user) => ({ id: user.id, name: user.name })),
  },
]))
</script>

<template>
  <AppLayout :title="t('resources.drawings')">
    <DataTable
      can="drawings"
      :items="drawings"
      :columns="columns"
      :filters="filters"
      :filterFields="filterFields"
      :hasShow="true"
      @show="viewItem"
      @edit="editItem"
      @delete="deleteItem"
      :title="t('resources.drawings')"
      :url="'drawings.index'"
      exportRoute="drawings.export"
      :showAddButton="true"
      :addTitle="t('sidebar.owners.drawing')"
      :addAction="'redirect'"
      :addRoute="'drawings.create'"
    />
  </AppLayout>
</template>
