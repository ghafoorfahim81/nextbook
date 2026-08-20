<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useDeleteResource } from '@/composables/useDeleteResource';
import { useI18n } from 'vue-i18n';

defineProps({
    salaryPayments: Object,
    filterOptions: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const { t } = useI18n();

const columns = computed(() => ([
    { key: 'number', label: t('general.number'), sortable: true },
    { key: 'date', label: t('general.date'), sortable: true },
    { key: 'employee_name', label: t('hr.employee') },
    { key: 'employee_code', label: t('general.code') },
    { key: 'currency_code', label: t('general.currency') },
    { key: 'amount', label: t('general.amount') },
    { key: 'payment_mode_label', label: t('hr.payment_mode') },
    { key: 'bank_account_name', label: t('hr.paid_from') },
    { key: 'actions', label: t('general.action') },
]));

const { deleteResource } = useDeleteResource();

const deleteItem = (id) => {
    deleteResource('salary-payments.destroy', id, {
        title: t('hr.void_payment'),
        // Says what actually happens: the voucher is withdrawn and the payslip
        // goes back on the unpaid list.
        description: t('hr.void_payment_description'),
        successMessage: t('general.delete_success', { name: t('hr.salary_payment') }),
    });
};
</script>

<template>
    <AppLayout :title="t('hr.salary_payments')">
        <DataTable
            can="salary_payments"
            :items="salaryPayments"
            :columns="columns"
            :filters="filters"
            @view="(item) => router.get(route('salary-payments.show', item.id))"
            @delete="deleteItem"
            @add="router.get(route('salary-payments.create'))"
            :title="t('hr.salary_payments')"
            :url="`salary-payments.index`"
            :showAddButton="true"
            :addTitle="t('hr.salary_payment')"
        />
    </AppLayout>
</template>
