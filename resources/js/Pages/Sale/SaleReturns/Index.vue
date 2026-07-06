<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDeleteResource } from '@/composables/useDeleteResource';
import { router } from '@inertiajs/vue3'
const { t } = useI18n();

const props = defineProps({
    saleReturns: Object,
    filters: Object,
    filterOptions: Object,
})

const editItem = (item) => {
    router.visit(route('sale-returns.edit', item.id));
}

const { deleteResource } = useDeleteResource()
const deleteItem = (id) => {
    deleteResource('sale-returns.destroy', id, {
        title: t('general.delete', { name: t('sale_return.sale_returns') }),
    })
}

const showItem = (id) => {
    router.visit(route('sale-returns.show', id));
}

const columns = computed(() => ([
    { key: 'number', label: t('general.number'), sortable: true },
    { key: 'sale_number', label: t('sale_return.linked_sale') },
    { key: 'customer_name', label: t('ledger.customer.customer') },
    { key: 'amount', label: t('general.amount'), sortable: true },
    { key: 'date', label: t('general.date'), sortable: true },
    { key: 'reason_label', label: t('sale_return.reason') },
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
        key: 'reason',
        label: t('sale_return.reason'),
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
    <AppLayout :title="t('sale_return.sale_returns')">
            <DataTable
            can="sale_returns"
            :items="saleReturns"
            :columns="columns"
            :filters="filters"
            :filterFields="filterFields"
            :title="t('sale_return.sale_returns')"
            :url="`sale-returns.index`"
            :showAddButton="true"
            :showEditButton="true"
            :showDeleteButton="true"
            :hasShow="true"
            @edit="editItem"
            @delete="deleteItem"
            @show="showItem"
            :addTitle="t('sale_return.sale_return')"
            :addAction="'redirect'"
            :addRoute="'sale-returns.create'"
            />
    </AppLayout>
</template>
