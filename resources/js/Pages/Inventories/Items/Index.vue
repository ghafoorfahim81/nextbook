<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import DataTable from '@/Components/DataTable.vue'
import ItemInventoryModal from '@/Components/ItemInventoryModal.vue'
import { ref, computed } from 'vue'
import { useDeleteResource } from '@/composables/useDeleteResource.js'
import { useI18n } from 'vue-i18n'
import { router } from '@inertiajs/vue3'
const { t } = useI18n()

const props = defineProps({
  items: Object,
  filters: Object,
  filterOptions: Object,
})

const columns = computed(() => ([
  { key: 'name', label: t('general.name') },
  { key: 'code', label: t('admin.currency.code') },
  { key: 'category', label: t('admin.category.category') },
  { key: 'measure', label: t('admin.unit_measure.unit_measure') },
  { key: 'brand_name', label: t('admin.brand.brand') },
  { key: 'cost', label: t('item.cost') },
  { key: 'on_hand', label: t('general.on_hand') },
  { key: 'sale_price', label: t('item.sale_price') },
  { key: 'actions', label: t('general.actions') },
]))

const { deleteResource } = useDeleteResource()
const showInventory = ref(false)
const selectedItem = ref(null)

const editItem = (item) => {
  router.visit(route('items.edit', item.id));
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

const filterFields = computed(() => ([
  { key: 'code', label: t('admin.currency.code'), type: 'text' },
  {
    key: 'item_type',
    label: t('item.item_type'),
    type: 'select',
    options: (props.filterOptions?.itemTypes || []).map((o) => ({ id: o.id, name: o.name })),
  },
  {
    key: 'unit_measure_id',
    label: t('admin.unit_measure.unit_measure'),
    type: 'select',
    options: (props.filterOptions?.unitMeasures || []).map((o) => ({ id: o.id, name: o.name })),
  },
  {
    key: 'category_id',
    label: t('admin.category.category'),
    type: 'select',
    options: (props.filterOptions?.categories || []).map((o) => ({ id: o.id, name: o.name })),
  },
  {
    key: 'size_id',
    label: t('admin.size.size'),
    type: 'select',
    options: (props.filterOptions?.sizes || []).map((o) => ({ id: o.id, name: o.name })),
  },
  {
    key: 'brand_id',
    label: t('admin.brand.brand'),
    type: 'select',
    options: (props.filterOptions?.brands || []).map((o) => ({ id: o.id, name: o.name })),
  },
  { key: 'purchase_price', label: t('item.purchase_price'), type: 'numberrange' },
  { key: 'sale_price', label: t('item.sale_price'), type: 'numberrange' },
  {
    key: 'created_by',
    label: t('general.created_by'),
    type: 'select',
    options: (props.filterOptions?.users || []).map((u) => ({ id: u.id, name: u.name })),
  },
]))
</script>

<template>
  <AppLayout :title="t('item.items')">
    <DataTable
      can="items"
      :items="items"
      :columns="columns"
      :filters="filters"
      :filterFields="filterFields"
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
