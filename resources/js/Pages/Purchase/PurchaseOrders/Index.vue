<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDeleteResource } from '@/composables/useDeleteResource';
import { router } from '@inertiajs/vue3'
const { t } = useI18n();

const props = defineProps({
    purchaseOrders: Object,
    filters: Object,
    filterOptions: Object,
})

const editItem = (item) => {
    router.visit(route('purchase-orders.edit', item.id));
}

const { deleteResource } = useDeleteResource()
const deleteItem = (id) => {
    deleteResource('purchase-orders.destroy', id, {
        title: t('general.delete', { name: t('purchase_order.purchase_orders') }),
    })
}

const showItem = (id) => {
    router.visit(route('purchase-orders.show', id));
}

const columns = computed(() => ([
    { key: 'number', label: t('general.number'), sortable: true },
    { key: 'supplier_name', label: t('ledger.supplier.supplier') },
    { key: 'date', label: t('general.date'), sortable: true },
    { key: 'delivery_date', label: t('purchase_order.delivery_date'), sortable: true },
    { key: 'amount', label: t('general.amount'), sortable: true },
    { key: 'status_label', label: t('general.status') },
    { key: 'actions', label: t('general.actions') },
]))

const filterFields = computed(() => ([
    {
        key: 'supplier_id',
        label: t('ledger.supplier.supplier'),
        type: 'select',
        options: (props.filterOptions?.suppliers || []).map((s) => ({ id: s.id, name: s.name })),
    },
    {
        key: 'status',
        label: t('general.status'),
        type: 'select',
        options: (props.filterOptions?.statuses || []).map((s) => ({ id: s.id, name: s.name })),
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
    <AppLayout :title="t('purchase_order.purchase_orders')">
        <DataTable
            can="purchase_orders"
            :items="purchaseOrders"
            :columns="columns"
            :filters="filters"
            :filterFields="filterFields"
            :title="t('purchase_order.purchase_orders')"
            :url="`purchase-orders.index`"
            :showAddButton="true"
            :showEditButton="true"
            :showDeleteButton="true"
            :hasShow="true"
            @edit="editItem"
            @delete="deleteItem"
            @show="showItem"
            :addTitle="t('purchase_order.purchase_order')"
            :addAction="'redirect'"
            :addRoute="'purchase-orders.create'"
        />
    </AppLayout>
</template>
