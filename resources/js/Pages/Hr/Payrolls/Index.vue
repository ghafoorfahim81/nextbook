<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useDeleteResource } from '@/composables/useDeleteResource';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const props = defineProps({
    payrolls: Object,
    filterOptions: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const { t } = useI18n();

const statusTone = (status) => ({
    draft: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    calculated: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-200',
    pending_approval: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    approved: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200',
    posted: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200',
    paid: 'bg-emerald-200 text-emerald-900 dark:bg-emerald-900 dark:text-emerald-100',
    cancelled: 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    reversed: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-200',
}[status] ?? 'bg-slate-100 text-slate-700');

const columns = computed(() => ([
    { key: 'number', label: t('general.number'), sortable: true },
    { key: 'period_label', label: t('hr.period'), render: (row) => row.period_label || row.period_end },
    { key: 'period_start', label: t('hr.period_start') },
    { key: 'period_end', label: t('hr.period_end'), sortable: true },
    { key: 'employee_count', label: t('hr.employees') },
    { key: 'total_gross', label: t('hr.gross') },
    { key: 'total_tax', label: t('hr.tax') },
    { key: 'total_net', label: t('hr.net_payable') },
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
    { key: 'department_id', label: t('hr.department'), type: 'select', options: props.filterOptions?.departments || [] },
]));

const { deleteResource } = useDeleteResource();

const deleteItem = (id) => {
    const item = props.payrolls?.data?.find((row) => row.id === id);

    // Refused server-side too. Caught here so the user is told to reverse it
    // rather than being shown a confirm dialog that then fails.
    if (item?.is_posted) {
        toast.error(t('hr.posted_payroll_must_be_reversed'));
        return;
    }

    deleteResource('payrolls.destroy', id, {
        title: t('general.delete', { name: t('hr.payroll') }),
        description: t('general.delete_description', { name: t('hr.payroll') }),
        successMessage: t('general.delete_success', { name: t('hr.payroll') }),
    });
};
</script>

<template>
    <AppLayout :title="t('hr.payrolls')">
        <DataTable
            can="payrolls"
            :items="payrolls"
            :columns="columns"
            :filters="filters"
            :filterFields="filterFields"
            @view="(item) => router.get(route('payrolls.show', item.id))"
            @edit="(item) => router.get(route('payrolls.edit', item.id))"
            @delete="deleteItem"
            @add="router.get(route('payrolls.create'))"
            :title="t('hr.payrolls')"
            :url="`payrolls.index`"
            :showAddButton="true"
            :addTitle="t('hr.payroll')"
        />
    </AppLayout>
</template>
