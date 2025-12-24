<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import DataTable from '@/Components/DataTable.vue'
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDeleteResource } from '@/composables/useDeleteResource'
import ShowDialog from '@/Pages/AccountTransfers/ShowDialog.vue'
import { router } from '@inertiajs/vue3'
const { t } = useI18n()

const props = defineProps({
  transfers: Object,
})

const { deleteResource } = useDeleteResource()
const showDialog = ref(false)
const selectedId = ref(null)

const editItem = (item) => {
  router.visit(route('account-transfers.edit', item.id));
}
const deleteItem = (id) => {
  deleteResource('account-transfers.destroy', id, {
    title: t('general.delete', { name: t('general.account_transfer') }),
    name: t('general.account_transfer'),
  })
}
const showItem = (id) => {
  selectedId.value = id
  showDialog.value = true
}

const columns = computed(() => ([
  { key: 'number', label: t('general.number'), sortable: true },
  { key: 'from_account_name', label: t('general.from_account') },
  { key: 'to_account_name', label: t('general.to_account') },
  { key: 'amount', label: t('general.amount'), sortable: true },
  { key: 'currency_code', label: t('admin.currency.currency') },
  { key: 'date', label: t('general.date'), sortable: true },
  { key: 'actions', label: t('general.actions') },
]))
</script>

<template>
  <AppLayout :title="t('general.account_transfers')">
    <DataTable
      :items="transfers"
      :columns="columns"
      :title="t('general.account_transfers')"
      :url="`account-transfers.index`"
      :showAddButton="true"
      :hasShow="true"
      @edit="editItem"
      @delete="deleteItem"
      @show="showItem"
      :addTitle="t('general.account_transfer')"
      :addAction="'redirect'"
      :addRoute="'account-transfers.create'"
    />
    <ShowDialog
      :open="showDialog"
      :transfer-id="selectedId"
      @update:open="showDialog = $event"
    />
  </AppLayout>

</template>

