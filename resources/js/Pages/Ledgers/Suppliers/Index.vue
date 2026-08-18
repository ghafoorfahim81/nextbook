<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { computed } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3'
const props = defineProps({
    suppliers: Object,
    filters: Object,
    filterOptions: Object,
});

const { t } = useI18n();
const { deleteResource } = useDeleteResource();

const columns = computed(() => ([
        { key: 'name', label: t('general.name'), sortable: true },
        { key: 'code', label: t('admin.currency.code'), sortable: true },
        { key: 'contact_person', label: t('ledger.contact_person') },
        { key: 'phone_no', label: t('general.phone'), sortable: true },
        { key: 'email', label: t('general.email'), sortable: true },
        {
            key: 'is_active',
            label: t('general.status'),
            sortable: true,
            render: (row) => row.is_active ? t('general.active') : t('general.inactive'),
        },
        { key: 'actions', label: t('general.actions') },

    ]));
const editItem = (item) => {
    router.visit(route('suppliers.edit', item.id));
};

const showItem = (item) => {
    router.visit(route('suppliers.show', item));
};

const deleteItem = (id) => {
    deleteResource('suppliers.destroy', id, {
        title: t('general.delete', { name: t('ledger.supplier.supplier') }),
        description: t('general.delete_description', { name: t('ledger.supplier.supplier') }),
        successMessage: t('general.delete_success', { name: t('ledger.supplier.supplier') }),
    });
};

const filterFields = computed(() => ([
    { key: 'name', label: t('general.name'), type: 'text' },
    { key: 'code', label: t('admin.currency.code'), type: 'text' },
    {
        key: 'currency_id',
        label: t('admin.currency.currency'),
        type: 'select',
        options: (props.filterOptions?.currencies || []).map((c) => ({ id: c.id, name: c.code })),
    },
    {
        key: 'balance_type',
        label: t('report.filters.balance_type'),
        type: 'select',
        // The same buckets as the Party Balance Summary report, so a filter
        // learned in one place means the same thing in the other.
        options: [
            { id: 'debtor', name: t('report.balance_types.debtor') },
            { id: 'creditor', name: t('report.balance_types.creditor') },
        ],
    },
    {
        key: 'group_id',
        label: t('ledger.customer_group'),
        type: 'select',
        options: (props.filterOptions?.groups || []).map((g) => ({ id: g.id, name: g.localized_name })),
    },
    {
        key: 'country_id',
        label: t('ledger.country'),
        type: 'select',
        options: (props.filterOptions?.countries || []).map((c) => ({ id: c.id, name: c.localized_name })),
    },
    {
        key: 'province_id',
        label: t('ledger.province'),
        type: 'select',
        options: (props.filterOptions?.provinces || []).map((p) => ({ id: p.id, name: p.localized_name })),
    },
    {
        key: 'created_by',
        label: t('general.created_by'),
        type: 'select',
        options: (props.filterOptions?.users || []).map((u) => ({ id: u.id, name: u.name })),
    },
]));
</script>

<template>
    <AppLayout :title="t('ledger.supplier.suppliers')">
        <DataTable
            can="suppliers"
            :items="suppliers"
            :columns="columns"
            :filters="filters"
            :filterFields="filterFields"
            @delete="deleteItem"
            @edit="editItem"
            @show="showItem"
            :title="t('ledger.supplier.suppliers')"
            :url="`suppliers.index`"
            :hasShow="true"
            exportRoute="suppliers.list-export"
            :showAddButton="true"
            :addTitle="t('ledger.supplier.supplier')"
            :addAction="'redirect'"
            :addRoute="'suppliers.create'"
        />
    </AppLayout>
</template>
