<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed, h } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from './CreateEditModal.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    leaveTypes: Object,
    filterOptions: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const isDialogOpen = ref(false);
const editingItem = ref(null);

const columns = computed(() => ([
    { key: 'name', label: t('general.name'), sortable: true },
    { key: 'code', label: t('general.code') },
    { key: 'accrual_method_label', label: t('hr.accrual_method') },
    { key: 'days_per_year', label: t('hr.days_per_year'), render: (row) => row.days_per_year ?? '—' },
    { key: 'max_carry_forward_days', label: t('hr.max_carry_forward_days'), render: (row) => row.max_carry_forward_days ?? '—' },
    { key: 'is_paid', label: t('hr.is_paid'), render: (row) => (row.is_paid ? t('general.yes') : t('general.no')) },
    {
        key: 'applicable_gender_label',
        label: t('hr.applicable_gender'),
        render: (row) => row.applicable_gender_label ?? '—',
    },
    { key: 'actions', label: t('general.action') },
]));

const filterFields = computed(() => ([
    { key: 'accrual_method', label: t('hr.accrual_method'), type: 'select', options: props.filterOptions?.accrualMethods || [] },
]));

const editItem = (item) => {
    editingItem.value = item;
    isDialogOpen.value = true;
};

const { deleteResource } = useDeleteResource();
const deleteItem = (id) => {
    deleteResource('leave-types.destroy', id, {
        title: t('general.delete', { name: t('hr.leave_type') }),
        description: t('general.delete_description', { name: t('hr.leave_type') }),
        successMessage: t('general.delete_success', { name: t('hr.leave_type') }),
    });
};
</script>

<template>
    <AppLayout :title="t('hr.leave_types')">
        <CreateEditModal
            :isDialogOpen="isDialogOpen"
            :editingItem="editingItem"
            :filterOptions="filterOptions"
            @update:isDialogOpen="(v) => { isDialogOpen = v; if (!v) editingItem = null; }"
            @saved="() => { editingItem = null }"
        />
        <DataTable
            can="leave_types"
            :items="leaveTypes"
            :columns="columns"
            :filters="filters"
            :filterFields="filterFields"
            @edit="editItem"
            @delete="deleteItem"
            @add="isDialogOpen = true"
            :title="t('hr.leave_types')"
            :url="`leave-types.index`"
            :showAddButton="true"
            :addTitle="t('hr.leave_type')"
            :addAction="'modal'"
        />
    </AppLayout>
</template>
