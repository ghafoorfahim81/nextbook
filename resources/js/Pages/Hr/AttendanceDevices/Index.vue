<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from './CreateEditModal.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    devices: Object,
    filterOptions: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const isDialogOpen = ref(false);
const editingItem = ref(null);

const columns = computed(() => ([
    { key: 'name', label: t('general.name') },
    { key: 'code', label: t('general.code') },
    { key: 'device_type_label', label: t('hr.device') },
    { key: 'location', label: t('admin.brand.address'), render: (row) => row.location ?? '—' },
    { key: 'mapping_count', label: t('hr.employee_count'), render: (row) => row.mapping_count ?? 0 },
    { key: 'last_sync_at', label: t('general.updated_at'), render: (row) => row.last_sync_at ?? '—' },
    { key: 'actions', label: t('general.action') },
]));

const filterFields = computed(() => ([
    { key: 'device_type', label: t('hr.device'), type: 'select', options: props.filterOptions?.deviceTypes || [] },
]));

const editItem = (item) => {
    editingItem.value = item;
    isDialogOpen.value = true;
};

const { deleteResource } = useDeleteResource();
const deleteItem = (id) => {
    deleteResource('attendance-devices.destroy', id, {
        title: t('general.delete', { name: t('hr.device') }),
        description: t('general.delete_description', { name: t('hr.device') }),
        successMessage: t('general.delete_success', { name: t('hr.device') }),
    });
};
</script>

<template>
    <AppLayout :title="t('hr.devices')">
        <CreateEditModal
            :isDialogOpen="isDialogOpen"
            :editingItem="editingItem"
            :filterOptions="filterOptions"
            @update:isDialogOpen="(v) => { isDialogOpen = v; if (!v) editingItem = null; }"
            @saved="() => { editingItem = null }"
        />
        <DataTable
            can="attendance_devices"
            :items="devices"
            :columns="columns"
            :filters="filters"
            :filterFields="filterFields"
            @edit="editItem"
            @delete="deleteItem"
            @add="isDialogOpen = true"
            :title="t('hr.devices')"
            :url="`attendance-devices.index`"
            :showAddButton="true"
            :addTitle="t('hr.device')"
            :addAction="'modal'"
        />
    </AppLayout>
</template>
