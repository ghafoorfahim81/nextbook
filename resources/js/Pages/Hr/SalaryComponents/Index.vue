<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from './CreateEditModal.vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const props = defineProps({
    salaryComponents: Object,
    filterOptions: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const isDialogOpen = ref(false);
const editingItem = ref(null);

const columns = computed(() => ([
    { key: 'sequence', label: t('hr.sequence'), sortable: true },
    { key: 'name', label: t('general.name'), sortable: true },
    { key: 'code', label: t('general.code') },
    { key: 'component_type_label', label: t('hr.component_type') },
    { key: 'calculation_type_label', label: t('hr.calculation_type') },
    {
        key: 'value',
        label: t('hr.value'),
        // One column for two mutually exclusive fields: a component is either
        // a fixed amount or a percentage, never both.
        render: (row) => {
            if (row.percentage !== null && row.percentage !== undefined) return `${row.percentage}%`;
            if (row.amount !== null && row.amount !== undefined) return row.amount;
            return '—';
        },
    },
    { key: 'is_taxable', label: t('hr.is_taxable'), render: (row) => (row.is_taxable ? t('general.yes') : t('general.no')) },
    { key: 'is_active', label: t('general.status'), render: (row) => (row.is_active ? t('general.active') : t('general.inactive')) },
    { key: 'actions', label: t('general.action') },
]));

const filterFields = computed(() => ([
    { key: 'component_type', label: t('hr.component_type'), type: 'select', options: props.filterOptions?.componentTypes || [] },
    { key: 'calculation_type', label: t('hr.calculation_type'), type: 'select', options: props.filterOptions?.calculationTypes || [] },
]));

const editItem = (item) => {
    editingItem.value = item;
    isDialogOpen.value = true;
};

const { deleteResource } = useDeleteResource();

const deleteItem = (id) => {
    const item = props.salaryComponents?.data?.find((row) => row.id === id);

    // Refused server-side too; caught here so the user gets an explanation
    // instead of a confirm dialog that then fails.
    if (item?.is_system) {
        toast.error(t('hr.system_component_cannot_be_deleted'));
        return;
    }

    deleteResource('salary-components.destroy', id, {
        title: t('general.delete', { name: t('hr.salary_component') }),
        description: t('general.delete_description', { name: t('hr.salary_component') }),
        successMessage: t('general.delete_success', { name: t('hr.salary_component') }),
    });
};
</script>

<template>
    <AppLayout :title="t('hr.salary_components')">
        <CreateEditModal
            :isDialogOpen="isDialogOpen"
            :editingItem="editingItem"
            :filterOptions="filterOptions"
            @update:isDialogOpen="(v) => { isDialogOpen = v; if (!v) editingItem = null; }"
            @saved="() => { editingItem = null }"
        />
        <DataTable
            can="salary_components"
            :items="salaryComponents"
            :columns="columns"
            :filters="filters"
            :filterFields="filterFields"
            @edit="editItem"
            @delete="deleteItem"
            @add="isDialogOpen = true"
            :title="t('hr.salary_components')"
            :url="`salary-components.index`"
            :showAddButton="true"
            :addTitle="t('hr.salary_component')"
            :addAction="'modal'"
        />
    </AppLayout>
</template>
