<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDeleteResource } from '@/composables/useDeleteResource';
import { router } from '@inertiajs/vue3'
const { t } = useI18n();

const props = defineProps({
    purchaseReturns: Object,
    filters: Object,
    filterOptions: Object,
})

const editItem = (item) => {
    router.visit(route('purchase-returns.edit', item.id));
}

const { deleteResource } = useDeleteResource()
const deleteItem = (id) => {
    deleteResource('purchase-returns.destroy', id, {
        title: t('general.delete', { name: t('purchase_return.purchase_returns') }),
    })
}

const showItem = (id) => {
    router.visit(route('purchase-returns.show', id));
}

const columns = computed(() => ([
    { key: 'number', label: t('general.number'), sortable: true },
    { key: 'purchase_number', label: t('purchase_return.linked_purchase') },
    { key: 'supplier_name', label: t('ledger.supplier.supplier') },
    { key: 'amount', label: t('general.amount'), sortable: true },
    { key: 'date', label: t('general.date'), sortable: true },
    { key: 'reason_label', label: t('purchase_return.reason') },
    { key: 'status', label: t('general.status') },
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
        key: 'reason',
        label: t('purchase_return.reason'),
        type: 'select',
        options: (props.filterOptions?.reasons || []).map((r) => ({ id: r.id, name: r.name })),
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
    <AppLayout :title="t('purchase_return.purchase_returns')">
            <DataTable
            can="purchase_returns"
            :items="purchaseReturns"
            :columns="columns"
            :filters="filters"
            :filterFields="filterFields"
            :title="t('purchase_return.purchase_returns')"
            :url="`purchase-returns.index`"
            :showAddButton="true"
            :showEditButton="true"
            :showDeleteButton="true"
            :hasShow="true"
            @edit="editItem"
            @delete="deleteItem"
            @show="showItem"
            :addTitle="t('purchase_return.purchase_return')"
            :addAction="'redirect'"
            :addRoute="'purchase-returns.create'"
            />
    </AppLayout>
</template>
