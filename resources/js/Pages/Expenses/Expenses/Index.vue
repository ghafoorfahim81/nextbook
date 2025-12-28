<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import ShowDialog from './ShowDialog.vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    expenses: Object,
});

const { t } = useI18n();
const showDialogOpen = ref(false);
const selectedExpense = ref(null);

const columns = computed(() => [
    { key: 'date', label: t('general.date'), sortable: true },
    {
        key: 'category.name',
        label: t('expense.category'),
        render: (row) => row.category?.name,
    },
    {
        key: 'expense_account.name',
        label: t('expense.expense_account'),
        render: (row) => row.expense_account?.name,
    },
    {
        key: 'bank_account.name',
        label: t('expense.bank_account'),
        render: (row) => row.bank_account?.name,
    },
    {
        key: 'total',
        label: t('general.total'),
        render: (row) => `${row.currency?.symbol || ''} ${Number(row.total || 0).toLocaleString()}`,
    },
    { key: 'remarks', label: t('general.remarks') },
    { key: 'actions', label: t('general.action') },
]);

const viewItem = async (item) => {
    console.log('item', item);
    try {
        const response = await fetch(route('expenses.show', item));
        const data = await response.json();
        selectedExpense.value = data.data;
        showDialogOpen.value = true;
    } catch (error) {
        console.error('Error fetching expense:', error);
    }
};

const editItem = (item) => {
    router.visit(route('expenses.edit', item.id));
};

const { deleteResource } = useDeleteResource();

const deleteItem = (id) => {
    deleteResource('expenses.destroy', id, {
        title: t('general.delete', { name: t('expense.expense') }),
        description: t('general.delete_description', { name: t('expense.expense') }),
        successMessage: t('general.delete_success', { name: t('expense.expense') }),
    });
};

const addExpense = () => {
    router.visit(route('expenses.create'));
};
</script>

<template>
    <AppLayout :title="t('expense.expenses')">
        <ShowDialog
            :open="showDialogOpen"
            :expense="selectedExpense"
            @update:open="showDialogOpen = $event"
        />
        <DataTable
            can="expenses"
            :items="expenses"
            :columns="columns"
            @show="viewItem"
            @edit="editItem"
            @delete="deleteItem"
            @add="addExpense"
            :hasShow="true"
            :title="t('expense.expenses')"
            :url="`expenses.index`"
            :showAddButton="true"
            :addTitle="t('expense.expense')"
            :addAction="'redirect'"
            :addRoute="'expenses.create'"
        />

    </AppLayout>
</template>

