<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import DataTable from '@/Components/DataTable.vue'
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDeleteResource } from '@/composables/useDeleteResource'
import ShowDialog from '@/Pages/ItemTransfer/ItemTransfers/ShowDialog.vue'
import { router } from '@inertiajs/vue3'
import { useToast } from '@/Components/ui/toast/use-toast'
const { t } = useI18n()

const { toast } = useToast()

const props = defineProps({
  transfers: Object,
})

const showDialog = ref(false)
const selectedId = ref(null)

const editItem = (item) => {
 if(item.status === 'completed' || item.status === 'cancelled') {
  toast({
    title: t('general.error'),
    description: t('item_transfer.cannot_edit_completed_or_cancelled_transfer'),
    variant: 'error',
    class: 'bg-red-600 text-white',
  })
  return
 }
  router.visit(route('item-transfers.edit', item.id))
}

const { deleteResource } = useDeleteResource()
const deleteItem = (id) => {
    const transfer = props.transfers.data.find(t => (t.id==id));
    if(transfer.status === 'completed') {
        toast({
        title: t('general.error'),
        description: t('item_transfer.cannot_edit_completed_or_cancelled_transfer'),
        variant: 'error',
        class: 'bg-red-600 text-white',
        })
        return
    }
  deleteResource('item-transfers.destroy', id, {
    title: t('general.delete', { name: t('item_transfer.item_transfer') }),
  })
}

const showItem = (id) => {
  selectedId.value = id
  showDialog.value = true
}

const columns = computed(() => ([
  { key: 'date', label: t('general.date'), sortable: true },
  { key: 'from_store.name', label: t('item_transfer.from_store') },
  { key: 'to_store.name', label: t('item_transfer.to_store') },
  { key: 'status_label', label: t('general.status') },
  { key: 'transfer_cost', label: t('item_transfer.transfer_cost'), sortable: true },
  { key: 'actions', label: t('general.actions') },
]))
</script>

<template>
  <AppLayout :title="t('item_transfer.item_transfers')">
    <DataTable
      can="item_transfers"
      :items="transfers"
      :columns="columns"
      :title="t('item_transfer.item_transfers')"
      :url="`item-transfers.index`"
      :showAddButton="true"
      :hasShow="true"
      @edit="editItem"
      @delete="deleteItem"
      @show="showItem"
      :addTitle="t('item_transfer.item_transfer')"
      :addAction="'redirect'"
      :addRoute="'item-transfers.create'"
    />

    <ShowDialog
      :open="showDialog"
      :transfer-id="selectedId"
      @update:open="showDialog = $event"
    />
  </AppLayout>
</template>

