<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import DataTable from '@/Components/DataTable.vue'
import ShowDialog from './ShowDialog.vue'
import { useDeleteResource } from '@/composables/useDeleteResource'
import { router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  drawings: Object,
  filters: Object,
  filterOptions: Object,
})

const { t } = useI18n()

const showDialogOpen = ref(false)
const selectedDrawing = ref(null)

const columns = computed(() => [
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

const viewItem = async (id) => {
  try {
    const response = await fetch(route('drawings.show', id))
    const data = await response.json()
    selectedDrawing.value = data?.data || null
    showDialogOpen.value = true
  } catch (error) {
    console.error('Error fetching drawing:', error)
  }
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
    <ShowDialog
      :open="showDialogOpen"
      :drawing="selectedDrawing"
      @update:open="showDialogOpen = $event"
    />

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
      :showAddButton="true"
      :addTitle="t('sidebar.owners.drawing')"
      :addAction="'redirect'"
      :addRoute="'drawings.create'"
    />
  </AppLayout>
</template>
