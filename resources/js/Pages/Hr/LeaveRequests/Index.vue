<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useDeleteResource } from '@/composables/useDeleteResource';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    leaveRequests: Object,
    filterOptions: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const { t } = useI18n();

const columns = computed(() => ([
    { key: 'number', label: t('general.number'), sortable: true },
    { key: 'employee_name', label: t('hr.employee') },
    { key: 'leave_type_name', label: t('hr.leave_type') },
    { key: 'from_date', label: t('hr.from_date'), sortable: true },
    { key: 'to_date', label: t('hr.to_date') },
    {
        key: 'days',
        label: t('hr.days'),
        sortable: true,
        render: (row) => (row.is_half_day ? `${row.days} (${row.half_day_period_label})` : row.days),
    },
    {
        key: 'is_paid',
        label: t('hr.is_paid'),
        render: (row) => (row.is_paid ? t('general.yes') : t('general.no')),
    },
    { key: 'status_label', label: t('general.status'), sortable: true },
    { key: 'actions', label: t('general.action') },
]));

const filterFields = computed(() => ([
    { key: 'leave_type_id', label: t('hr.leave_type'), type: 'select', options: (props.filterOptions?.leaveTypes || []).map((x) => ({ id: x.id, name: x.name })) },
    { key: 'status', label: t('general.status'), type: 'select', options: props.filterOptions?.statuses || [] },
    { key: 'from_date', label: t('hr.from_date'), type: 'daterange' },
]));

const showItem = (id) => router.visit(route('leave-requests.show', id));
const editItem = (item) => router.visit(route('leave-requests.edit', item.id));

const { deleteResource } = useDeleteResource();
const deleteItem = (id) => {
    deleteResource('leave-requests.destroy', id, {
        title: t('general.delete', { name: t('hr.leave_request') }),
        description: t('general.delete_description', { name: t('hr.leave_request') }),
        successMessage: t('general.delete_success', { name: t('hr.leave_request') }),
    });
};
</script>

<template>
    <AppLayout :title="t('hr.leave_requests')">
        <DataTable
            can="leave_applications"
            :items="leaveRequests"
            :columns="columns"
            :filters="filters"
            :filterFields="filterFields"
            :hasShow="true"
            @edit="editItem"
            @delete="deleteItem"
            @show="showItem"
            :title="t('hr.leave_requests')"
            :url="`leave-requests.index`"
            :showAddButton="true"
            :addTitle="t('hr.leave_request')"
            :addAction="'redirect'"
            :addRoute="'leave-requests.create'"
        />
    </AppLayout>
</template>
