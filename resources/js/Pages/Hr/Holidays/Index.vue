<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from './CreateEditModal.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    holidays: Object,
    filterOptions: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const isDialogOpen = ref(false);
const editingItem = ref(null);

const columns = computed(() => ([
    { key: 'name', label: t('general.name') },
    { key: 'date', label: t('hr.date'), sortable: true },
    { key: 'end_date', label: t('hr.end_date'), render: (row) => row.end_date ?? '—' },
    { key: 'day_count', label: t('hr.day_count') },
    { key: 'holiday_type_label', label: t('hr.holiday_type') },
    { key: 'is_paid', label: t('hr.is_paid'), render: (row) => (row.is_paid ? t('general.yes') : t('general.no')) },
    { key: 'actions', label: t('general.action') },
]));

const filterFields = computed(() => ([
    { key: 'holiday_type', label: t('hr.holiday_type'), type: 'select', options: props.filterOptions?.holidayTypes || [] },
    { key: 'date', label: t('hr.date'), type: 'daterange' },
]));

const editItem = (item) => {
    editingItem.value = item;
    isDialogOpen.value = true;
};

const { deleteResource } = useDeleteResource();
const deleteItem = (id) => {
    deleteResource('holidays.destroy', id, {
        title: t('general.delete', { name: t('hr.holiday') }),
        description: t('general.delete_description', { name: t('hr.holiday') }),
        successMessage: t('general.delete_success', { name: t('hr.holiday') }),
    });
};
</script>

<template>
    <AppLayout :title="t('hr.holidays')">
        <CreateEditModal
            :isDialogOpen="isDialogOpen"
            :editingItem="editingItem"
            :filterOptions="filterOptions"
            @update:isDialogOpen="(v) => { isDialogOpen = v; if (!v) editingItem = null; }"
            @saved="() => { editingItem = null }"
        />
        <DataTable
            can="holidays"
            :items="holidays"
            :columns="columns"
            :filters="filters"
            :filterFields="filterFields"
            @edit="editItem"
            @delete="deleteItem"
            @add="isDialogOpen = true"
            :title="t('hr.holidays')"
            :url="`holidays.index`"
            :showAddButton="true"
            :addTitle="t('hr.holiday')"
            :addAction="'modal'"
        />
    </AppLayout>
</template>
