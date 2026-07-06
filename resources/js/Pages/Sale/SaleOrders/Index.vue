<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDeleteResource } from '@/composables/useDeleteResource';
import { router } from '@inertiajs/vue3'
const { t } = useI18n();

const props = defineProps({
    saleOrders: Object,
    filters: Object,
    filterOptions: Object,
})

const editItem = (item) => {
    router.visit(route('sale-orders.edit', item.id));
}

const { deleteResource } = useDeleteResource()
const deleteItem = (id) => {
    deleteResource('sale-orders.destroy', id, {
        title: t('general.delete', { name: t('sale_order.sale_orders') }),
    })
}

const showItem = (id) => {
    router.visit(route('sale-orders.show', id));
}

const columns = computed(() => ([
    { key: 'number', label: t('general.number'), sortable: true },
    { key: 'customer_name', label: t('ledger.customer.customer') },
    { key: 'date', label: t('general.date'), sortable: true },
    { key: 'delivery_date', label: t('sale_order.delivery_date'), sortable: true },
    { key: 'amount', label: t('general.amount'), sortable: true },
    { key: 'status_label', label: t('general.status') },
    { key: 'actions', label: t('general.actions') },
]))

const filterFields = computed(() => ([
    {
        key: 'customer_id',
        label: t('ledger.customer.customer'),
        type: 'select',
        options: (props.filterOptions?.customers || []).map((c) => ({ id: c.id, name: c.name })),
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
    <AppLayout :title="t('sale_order.sale_orders')">
        <DataTable
            can="sale_orders"
            :items="saleOrders"
            :columns="columns"
            :filters="filters"
            :filterFields="filterFields"
            :title="t('sale_order.sale_orders')"
            :url="`sale-orders.index`"
            :showAddButton="true"
            :showEditButton="true"
            :showDeleteButton="true"
            :hasShow="true"
            @edit="editItem"
            @delete="deleteItem"
            @show="showItem"
            :addTitle="t('sale_order.sale_order')"
            :addAction="'redirect'"
            :addRoute="'sale-orders.create'"
        />
    </AppLayout>
</template>
