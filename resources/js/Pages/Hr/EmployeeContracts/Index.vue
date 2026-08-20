<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from './CreateEditModal.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    contracts: Object,
    filterOptions: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const isDialogOpen = ref(false);
const editingItem = ref(null);

const columns = computed(() => ([
    { key: 'contract_number', label: t('hr.contract_number'), sortable: true },
    { key: 'employee_name', label: t('hr.employee'), render: (row) => row.employee_name ?? '-' },
    { key: 'contract_type_label', label: t('hr.contract_type') },
    { key: 'start_date', label: t('hr.start_date'), sortable: true },
    {
        key: 'end_date',
        label: t('hr.end_date'),
        sortable: true,
        // A contract inside its last month is the whole reason this screen
        // exists, so it is called out rather than left as a bare date.
        render: (row) => {
            if (!row.end_date) return '-';
            const d = row.days_until_expiry;
            if (d !== null && d !== undefined && d < 0) return `${row.end_date} (${t('hr.expired')})`;
            if (d !== null && d !== undefined && d <= 30) return `${row.end_date} (${t('hr.expires_in_days', { days: d })})`;
            return row.end_date;
        },
    },
    { key: 'status_label', label: t('general.status') },
    { key: 'actions', label: t('general.action') },
]));

const filterFields = computed(() => ([
    { key: 'contract_type', label: t('hr.contract_type'), type: 'select', options: props.filterOptions?.contractTypes || [] },
    { key: 'status', label: t('general.status'), type: 'select', options: props.filterOptions?.statuses || [] },
    { key: 'end_date', label: t('hr.end_date'), type: 'daterange' },
]));

const editItem = (item) => {
    editingItem.value = item;
    isDialogOpen.value = true;
};

const { deleteResource } = useDeleteResource();
const deleteItem = (id) => {
    deleteResource('employee-contracts.destroy', id, {
        title: t('general.delete', { name: t('hr.contract') }),
        description: t('general.delete_description', { name: t('hr.contract') }),
        successMessage: t('general.delete_success', { name: t('hr.contract') }),
    });
};
</script>

<template>
    <AppLayout :title="t('hr.contracts')">
        <CreateEditModal
            :isDialogOpen="isDialogOpen"
            :editingItem="editingItem"
            :filterOptions="filterOptions"
            @update:isDialogOpen="(value) => {
                isDialogOpen = value;
                if (!value) editingItem = null;
            }"
            @saved="() => { editingItem = null }"
        />
        <DataTable
            can="employee_contracts"
            :items="contracts"
            :columns="columns"
            :filters="filters"
            :filterFields="filterFields"
            @edit="editItem"
            @delete="deleteItem"
            @add="isDialogOpen = true"
            :title="t('hr.contracts')"
            :url="`employee-contracts.index`"
            :showAddButton="true"
            :addTitle="t('hr.contract')"
            :addAction="'modal'"
        />
    </AppLayout>
</template>
