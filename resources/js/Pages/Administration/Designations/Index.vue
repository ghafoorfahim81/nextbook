<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from './CreateEditModal.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    items: Object,
    departments: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const isDialogOpen = ref(false);
const editingItem = ref(null);

const columns = computed(() => ([
    { key: 'name', label: t('general.name'), sortable: true },
    { key: 'code', label: t('general.code'), sortable: true },
    {
        key: 'department_name',
        label: t('hr.department'),
        render: (row) => row.department_name ?? '-',
    },
    {
        key: 'grade_level',
        label: t('admin.designation.grade_level'),
        sortable: true,
        render: (row) => row.grade_level ?? '-',
    },
    {
        key: 'created_by.name',
        label: t('general.created_by'),
        render: (row) => row.created_by?.name ?? '-',
    },
    { key: 'actions', label: t('general.action') },
]));

const editItem = (item) => {
    editingItem.value = item;
    isDialogOpen.value = true;
};

const { deleteResource } = useDeleteResource();
const deleteItem = (id) => {
    deleteResource('designations.destroy', id, {
        title: t('general.delete', { name: t('general.resource.designation') }),
        description: t('general.delete_description', { name: t('general.resource.designation') }),
        successMessage: t('general.delete_success', { name: t('general.resource.designation') }),
    });
};
</script>

<template>
    <AppLayout :title="t('sidebar.hr.designation')">
        <CreateEditModal
            :isDialogOpen="isDialogOpen"
            :editingItem="editingItem"
            :departments="departments"
            @update:isDialogOpen="(value) => {
                isDialogOpen = value;
                if (!value) editingItem = null;
            }"
            @saved="() => { editingItem = null }"
        />
        <DataTable
            can="designations"
            :items="items"
            :columns="columns"
            :filters="filters"
            @edit="editItem"
            @delete="deleteItem"
            @add="isDialogOpen = true"
            :title="t('sidebar.hr.designation')"
            :url="`designations.index`"
            :showAddButton="true"
            :addTitle="t('general.resource.designation')"
            :addAction="'modal'"
        />
    </AppLayout>
</template>
