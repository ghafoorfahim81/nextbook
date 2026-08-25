<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from './CreateEditModal.vue';
import { useI18n } from 'vue-i18n';

defineProps({
    shifts: Object,
    filters: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const isDialogOpen = ref(false);
const editingItem = ref(null);

const columns = computed(() => ([
    { key: 'name', label: t('general.name'), sortable: true },
    { key: 'code', label: t('general.code'), sortable: true },
    {
        key: 'hours',
        label: t('hr.start_time'),
        render: (row) => `${row.start_time} – ${row.end_time}${row.crosses_midnight ? ' ⁺¹' : ''}`,
    },
    { key: 'full_day_hours', label: t('hr.full_day_hours') },
    {
        key: 'working_days',
        label: t('hr.working_days'),
        // Rendered as short weekday names rather than raw ISO numbers, which
        // are meaningless at a glance.
        render: (row) => (row.working_days || []).map((d) => t(`hr.weekday.${d}`)).join(', '),
    },
    {
        key: 'is_default',
        label: t('hr.is_default'),
        render: (row) => (row.is_default ? t('general.yes') : t('general.no')),
    },
    { key: 'employee_count', label: t('hr.employee_count'), render: (row) => row.employee_count ?? 0 },
    { key: 'actions', label: t('general.action') },
]));

const editItem = (item) => {
    editingItem.value = item;
    isDialogOpen.value = true;
};

const { deleteResource } = useDeleteResource();
const deleteItem = (id) => {
    deleteResource('shifts.destroy', id, {
        title: t('general.delete', { name: t('hr.shift') }),
        description: t('general.delete_description', { name: t('hr.shift') }),
        successMessage: t('general.delete_success', { name: t('hr.shift') }),
    });
};
</script>

<template>
    <AppLayout :title="t('hr.shifts')">
        <CreateEditModal
            :isDialogOpen="isDialogOpen"
            :editingItem="editingItem"
            @update:isDialogOpen="(v) => { isDialogOpen = v; if (!v) editingItem = null; }"
            @saved="() => { editingItem = null }"
        />
        <DataTable
            can="shifts"
            :items="shifts"
            :columns="columns"
            :filters="filters"
            @edit="editItem"
            @delete="deleteItem"
            @add="isDialogOpen = true"
            :title="t('hr.shifts')"
            :url="`shifts.index`"
            :showAddButton="true"
            :addTitle="t('hr.shift')"
            :addAction="'modal'"
        />
    </AppLayout>
</template>
