<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDeleteResource } from '@/composables/useDeleteResource';
import { router } from '@inertiajs/vue3'
const { t } = useI18n();

const props = defineProps({
    saleQuotations: Object,
    filters: Object,
    filterOptions: Object,
})

const editItem = (item) => {
    router.visit(route('sale-quotations.edit', item.id));
}

const { deleteResource } = useDeleteResource()
const deleteItem = (id) => {
    deleteResource('sale-quotations.destroy', id, {
        title: t('general.delete', { name: t('sale_quotation.sale_quotations') }),
    })
}

const showItem = (id) => {
    router.visit(route('sale-quotations.show', id));
}

const columns = computed(() => ([
    { key: 'number', label: t('general.number'), sortable: true },
    { key: 'customer_name', label: t('ledger.customer.customer') },
    { key: 'date', label: t('general.date'), sortable: true },
    { key: 'valid_until', label: t('sale_quotation.valid_until'), sortable: true },
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
    <AppLayout :title="t('sale_quotation.sale_quotations')">
        <DataTable
            can="sale_quotations"
            :items="saleQuotations"
            :columns="columns"
            :filters="filters"
            :filterFields="filterFields"
            :title="t('sale_quotation.sale_quotations')"
            :url="`sale-quotations.index`"
            :showAddButton="true"
            :showEditButton="true"
            :showDeleteButton="true"
            :hasShow="true"
            @edit="editItem"
            @delete="deleteItem"
            @show="showItem"
            :addTitle="t('sale_quotation.sale_quotation')"
            :addAction="'redirect'"
            :addRoute="'sale-quotations.create'"
        />
    </AppLayout>
</template>
