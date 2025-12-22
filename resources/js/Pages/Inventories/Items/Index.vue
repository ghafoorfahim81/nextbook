<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import DataTable from '@/Components/DataTable.vue'
import ItemInventoryModal from '@/Components/ItemInventoryModal.vue'
import { ref, computed } from 'vue'
import { useDeleteResource } from '@/composables/useDeleteResource.js'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const props = defineProps({
  items: Object,
})

const columns = computed(() => ([
  { key: 'name', label: t('general.name') },
  { key: 'code', label: t('admin.currency.code') },
  { key: 'category', label: t('admin.category.category') },
  { key: 'measure', label: t('admin.unit_measure.unit_measure') },
  { key: 'brand_name', label: t('admin.brand.brand') },
  { key: 'cost', label: t('item.cost') },
  { key: 'on_hand', label: t('general.on_hand') },
  { key: 'mrp_rate', label: t('item.mrp_rate') },
  { key: 'actions', label: t('general.actions') },
]))

const { deleteResource } = useDeleteResource()
const showInventory = ref(false)
const selectedItem = ref(null)

const editItem = (item) => {
  window.location.href = `/items/${item.id}/edit`
}

const deleteItem = (id) => {
  deleteResource('items.destroy', id, {
    title: t('general.delete', { name: t('item.item') }),
    description: t('general.delete_description', { name: t('item.item') }),
    successMessage: t('general.delete_success', { name: t('item.item') }),
  })
}

const openInventory = (id) => {
  const item = props.items?.data?.find((row) => row.id === id)
  if (!item) return
  selectedItem.value = item
  showInventory.value = true
}
</script>

<template>
  <AppLayout :title="t('item.items')">
    <DataTable
      :items="items"
      :columns="columns"
      @delete="deleteItem"
      @edit="editItem"
      @show="openInventory"
      :title="t('item.items')"
      :url="`items.index`"
      :hasShow="true"
      :showAddButton="true"
      :addTitle="t('item.item')"
      :addAction="'redirect'"
      :addRoute="'items.create'"
    />

    <ItemInventoryModal
      v-if="selectedItem"
      v-model="showInventory"
      :item="selectedItem"
    />
  </AppLayout>
</template>
