<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from './CreateEditModal.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    documents: Object,
    filterOptions: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const isDialogOpen = ref(false);
const editingItem = ref(null);

const columns = computed(() => ([
    { key: 'employee_name', label: t('hr.employee'), render: (row) => row.employee_name ?? '-' },
    { key: 'document_type_label', label: t('hr.document_type') },
    { key: 'document_number', label: t('hr.document_number'), render: (row) => row.document_number ?? '-' },
    { key: 'issue_date', label: t('hr.issue_date'), sortable: true, render: (row) => row.issue_date ?? '-' },
    {
        key: 'expiry_date',
        label: t('hr.expiry_date'),
        sortable: true,
        render: (row) => {
            if (!row.expiry_date) return '-';
            if (row.is_expired) return `${row.expiry_date} (${t('hr.expired')})`;
            const d = row.days_until_expiry;
            if (d !== null && d !== undefined && d <= 30) return `${row.expiry_date} (${t('hr.expires_in_days', { days: d })})`;
            return row.expiry_date;
        },
    },
    { key: 'is_verified', label: t('hr.is_verified'), render: (row) => (row.is_verified ? t('general.yes') : t('general.no')) },
    { key: 'actions', label: t('general.action') },
]));

const filterFields = computed(() => ([
    { key: 'document_type', label: t('hr.document_type'), type: 'select', options: props.filterOptions?.documentTypes || [] },
    { key: 'expiry_date', label: t('hr.expiry_date'), type: 'daterange' },
]));

const editItem = (item) => {
    editingItem.value = item;
    isDialogOpen.value = true;
};

const showItem = (id) => router.visit(route('employee-documents.show', id));

const { deleteResource } = useDeleteResource();
const deleteItem = (id) => {
    deleteResource('employee-documents.destroy', id, {
        title: t('general.delete', { name: t('hr.document') }),
        description: t('general.delete_description', { name: t('hr.document') }),
        successMessage: t('general.delete_success', { name: t('hr.document') }),
    });
};
</script>

<template>
    <AppLayout :title="t('hr.documents')">
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
            can="employee_documents"
            :items="documents"
            :columns="columns"
            :filters="filters"
            :filterFields="filterFields"
            :hasShow="true"
            @edit="editItem"
            @delete="deleteItem"
            @show="showItem"
            @add="isDialogOpen = true"
            :title="t('hr.documents')"
            :url="`employee-documents.index`"
            :showAddButton="true"
            :addTitle="t('hr.document')"
            :addAction="'modal'"
        />
    </AppLayout>
</template>
