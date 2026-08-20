<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useDeleteResource } from '@/composables/useDeleteResource';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const props = defineProps({
    employeeLoans: Object,
    filterOptions: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const { t } = useI18n();

const statusTone = (status) => ({
    draft: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    pending_approval: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    approved: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200',
    active: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-200',
    settled: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200',
    written_off: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-200',
    cancelled: 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
}[status] ?? 'bg-slate-100 text-slate-700');

const columns = computed(() => ([
    { key: 'number', label: t('general.number'), sortable: true },
    { key: 'employee_name', label: t('hr.employee') },
    { key: 'loan_type_label', label: t('hr.loan_type') },
    { key: 'issue_date', label: t('hr.issue_date'), sortable: true },
    { key: 'principal_amount', label: t('hr.principal') },
    { key: 'installment_amount', label: t('hr.installment') },
    { key: 'repaid_amount', label: t('hr.repaid') },
    { key: 'outstanding_amount', label: t('hr.outstanding') },
    {
        key: 'status_label',
        label: t('general.status'),
        html: true,
        render: (row) => `<span class="rounded-full px-2 py-0.5 text-xs font-medium ${statusTone(row.status)}">${row.status_label}</span>`,
    },
    { key: 'actions', label: t('general.action') },
]));

const filterFields = computed(() => ([
    { key: 'status', label: t('general.status'), type: 'select', options: props.filterOptions?.statuses || [] },
    { key: 'loan_type', label: t('hr.loan_type'), type: 'select', options: props.filterOptions?.loanTypes || [] },
]));

const { deleteResource } = useDeleteResource();

const deleteItem = (id) => {
    const item = props.employeeLoans?.data?.find((row) => row.id === id);

    if (item?.is_disbursed) {
        toast.error(t('hr.disbursed_loan_cannot_be_deleted'));
        return;
    }

    deleteResource('employee-loans.destroy', id, {
        title: t('general.delete', { name: t('hr.employee_loan') }),
        description: t('general.delete_description', { name: t('hr.employee_loan') }),
        successMessage: t('general.delete_success', { name: t('hr.employee_loan') }),
    });
};
</script>

<template>
    <AppLayout :title="t('hr.employee_loans')">
        <DataTable
            can="loans"
            :items="employeeLoans"
            :columns="columns"
            :filters="filters"
            :filterFields="filterFields"
            @view="(item) => router.get(route('employee-loans.show', item.id))"
            @edit="(item) => router.get(route('employee-loans.edit', item.id))"
            @delete="deleteItem"
            @add="router.get(route('employee-loans.create'))"
            :title="t('hr.employee_loans')"
            :url="`employee-loans.index`"
            :showAddButton="true"
            :addTitle="t('hr.employee_loan')"
        />
    </AppLayout>
</template>
