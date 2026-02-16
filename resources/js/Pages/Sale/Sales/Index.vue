<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import SaleShowDialog from '@/Components/SaleShowDialog.vue';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDeleteResource } from '@/composables/useDeleteResource';
import { router } from '@inertiajs/vue3'
const { t } = useI18n();

const props = defineProps({
    sales: Object,
    filters: Object,
    filterOptions: Object,
})

const showDialog = ref(false);
const selectedSaleId = ref(null);

const editItem = (item) => {
    router.visit(route('sales.edit', item.id));
}

const { deleteResource } = useDeleteResource()
const deleteItem = (id) => {
    deleteResource('sales.destroy', id, {
        title: t('general.delete', { name: t('sale.sales') }),
    })
}

const showItem = (id) => {
    selectedSaleId.value = id;
    showDialog.value = true;
}

const printItem = (id) => {
    window.open(route('sales.print', id), '_blank');
}

const columns = computed(() => ([
    { key: 'number', label: t('general.number'), sortable: true },
    { key: 'customer_name', label: t('ledger.customer.customer') },
    { key: 'amount', label: t('general.amount'), sortable: true },
    { key: 'date', label: t('general.date'), sortable: true },
    { key: 'type', label: t('general.type'), sortable: true },
    { key: 'status', label: t('general.status') },
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
        key: 'transaction.currency_id',
        label: t('admin.currency.currency'),
        type: 'select',
        options: (props.filterOptions?.currencies || []).map((c) => ({ id: c.id, name: c.code })),
    },
    {
        key: 'type',
        label: t('general.type'),
        type: 'select',
        options: (props.filterOptions?.types || []).map((o) => ({ id: o.id, name: o.name })),
    },
    {
        key: 'store_id',
        label: t('admin.store.store'),
        type: 'select',
        options: (props.filterOptions?.stores || []).map((s) => ({ id: s.id, name: s.name })),
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
    <AppLayout :title="t('sale.sales')">
            <DataTable
            can="sales"
            :items="sales"
            :columns="columns"
            :filters="filters"
            :filterFields="filterFields"
            :title="t('sale.sales')"
            :url="`sales.index`"
            :showAddButton="true"
            :showEditButton="true"
            :showDeleteButton="true"
            :hasShow="true"
            :hasPrint="true"
            @edit="editItem"
            @delete="deleteItem"
            @show="showItem"
            @print="printItem"
            :addTitle="t('sale.sale')"
            :addAction="'redirect'"
            :addRoute="'sales.create'"
            />

        <SaleShowDialog
            v-model="showDialog"
            :sale-id="selectedSaleId"
        />
    </AppLayout>
</template>
