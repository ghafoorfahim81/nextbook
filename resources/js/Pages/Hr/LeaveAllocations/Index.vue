<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from './CreateEditModal.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    allocations: Object,
    balances: { type: Object, default: () => ({}) },
    filterOptions: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const isDialogOpen = ref(false);
const editingItem = ref(null);

/**
 * Available days come from the batched balance map rather than the allocation
 * row, because the balance is derived from approved requests — the allocation
 * only records what was granted.
 */
const availableFor = (row) => {
    const forEmployee = props.balances?.[row.employee_id] || [];
    const match = forEmployee.find((b) => b.leave_type_id === row.leave_type_id);

    return match ? match.available : null;
};

const takenFor = (row) => {
    const forEmployee = props.balances?.[row.employee_id] || [];
    const match = forEmployee.find((b) => b.leave_type_id === row.leave_type_id);

    return match ? match.taken : null;
};

const columns = computed(() => ([
    { key: 'employee_name', label: t('hr.employee') },
    { key: 'leave_type_name', label: t('hr.leave_type') },
    { key: 'period_start', label: t('hr.period_start') },
    { key: 'period_end', label: t('hr.period_end') },
    { key: 'entitled_days', label: t('hr.entitled_days') },
    { key: 'carried_forward_days', label: t('hr.carried_forward_days') },
    { key: 'adjustment_days', label: t('hr.adjustment_days') },
    { key: 'taken', label: t('hr.taken_days'), render: (row) => takenFor(row) ?? '—' },
    { key: 'available', label: t('hr.available_days'), render: (row) => availableFor(row) ?? '—' },
    { key: 'actions', label: t('general.action') },
]));

const filterFields = computed(() => ([
    { key: 'leave_type_id', label: t('hr.leave_type'), type: 'select', options: props.filterOptions?.leaveTypes || [] },
    { key: 'period_start', label: t('hr.period_start'), type: 'daterange' },
]));

const editItem = (item) => {
    editingItem.value = item;
    isDialogOpen.value = true;
};

const { deleteResource } = useDeleteResource();
const deleteItem = (id) => {
    deleteResource('leave-allocations.destroy', id, {
        title: t('general.delete', { name: t('hr.leave_allocation') }),
        description: t('general.delete_description', { name: t('hr.leave_allocation') }),
        successMessage: t('general.delete_success', { name: t('hr.leave_allocation') }),
    });
};
</script>

<template>
    <AppLayout :title="t('hr.leave_allocations')">
        <CreateEditModal
            :isDialogOpen="isDialogOpen"
            :editingItem="editingItem"
            :filterOptions="filterOptions"
            @update:isDialogOpen="(v) => { isDialogOpen = v; if (!v) editingItem = null; }"
            @saved="() => { editingItem = null }"
        />
        <DataTable
            can="leaves"
            :items="allocations"
            :columns="columns"
            :filters="filters"
            :filterFields="filterFields"
            :hasShow="true"
            @edit="editItem"
            @delete="deleteItem"
            @show="(id) => router.get(route('leave-allocations.show', id))"
            @add="isDialogOpen = true"
            :title="t('hr.leave_allocations')"
            :url="`leave-allocations.index`"
            :showAddButton="true"
            :addTitle="t('hr.leave_allocation')"
            :addAction="'modal'"
        />
    </AppLayout>
</template>
