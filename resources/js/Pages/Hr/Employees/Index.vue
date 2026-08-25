<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useDeleteResource } from '@/composables/useDeleteResource';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    employees: Object,
    filterOptions: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const { t } = useI18n();

const columns = computed(() => ([
    { key: 'code', label: t('general.code'), sortable: true },
    { key: 'full_name', label: t('general.name'), sortable: true },
    { key: 'father_name', label: t('hr.father_name'), render: (row) => row.father_name ?? '-' },
    { key: 'department_name', label: t('hr.department'), render: (row) => row.department_name ?? '-' },
    { key: 'designation_name', label: t('hr.designation'), render: (row) => row.designation_name ?? '-' },
    { key: 'employment_type_label', label: t('hr.employment_type'), sortable: true },
    { key: 'employment_status_label', label: t('general.status'), sortable: true },
    { key: 'joining_date', label: t('hr.joining_date'), sortable: true },
    { key: 'phone_number', label: t('hr.phone_number'), render: (row) => row.phone_number ?? '-' },
    { key: 'actions', label: t('general.action') },
]));

const filterFields = computed(() => ([
    {
        key: 'department_id',
        label: t('hr.department'),
        type: 'select',
        options: (props.filterOptions?.departments || []).map((d) => ({ id: d.id, name: d.name })),
    },
    {
        key: 'designation_id',
        label: t('hr.designation'),
        type: 'select',
        options: (props.filterOptions?.designations || []).map((d) => ({ id: d.id, name: d.name })),
    },
    {
        key: 'employment_type',
        label: t('hr.employment_type'),
        type: 'select',
        options: props.filterOptions?.employmentTypes || [],
    },
    {
        key: 'employment_status',
        label: t('hr.employment_status'),
        type: 'select',
        options: props.filterOptions?.employmentStatuses || [],
    },
    { key: 'joining_date', label: t('hr.joining_date'), type: 'daterange' },
]));

const showItem = (id) => router.visit(route('employees.show', id));
const editItem = (item) => router.visit(route('employees.edit', item.id));

const { deleteResource } = useDeleteResource();
const deleteItem = (id) => {
    deleteResource('employees.destroy', id, {
        title: t('general.delete', { name: t('hr.employee') }),
        description: t('general.delete_description', { name: t('hr.employee') }),
        successMessage: t('general.delete_success', { name: t('hr.employee') }),
    });
};
</script>

<template>
    <AppLayout :title="t('hr.employees')">
        <DataTable
            can="employees"
            :items="employees"
            :columns="columns"
            :filters="filters"
            :filterFields="filterFields"
            :title="t('hr.employees')"
            :url="`employees.index`"
            :showAddButton="true"
            :hasShow="true"
            @edit="editItem"
            @delete="deleteItem"
            @show="showItem"
            :addTitle="t('hr.employee')"
            :addAction="'redirect'"
            :addRoute="'employees.create'"
            exportRoute="employees.list-export"
        />
    </AppLayout>
</template>
