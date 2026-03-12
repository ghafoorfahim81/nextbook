<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from '@/Pages/Administration/Warehouses/CreateEditModal.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    warehouses: Object,
});

const isDialogOpen = ref(false);
const editingWarehouse = ref(null);
const { t } = useI18n();

const columns = computed(() => ([
    { key: 'name', label: t('general.name'), sortable: true },
    { key: 'address', label: t('admin.shared.address') },
    {
        key: 'is_active',
        label: t('general.status'),
        sortable: true,
        render: (row) => row.is_active ? t('general.active') : t('general.inactive'),
    },
    {
        key: 'created_by.name',
        label: t('general.created_by'),
        render: (row) => row.created_by?.name ?? '-',
    },
    {
        key: 'updated_by.name',
        label: t('general.updated_by'),
        render: (row) => row.updated_by?.name ?? '-',
    },
    { key: 'actions', label: t('general.action') },
]));

const editItem = (item) => {
    editingWarehouse.value = item;
    isDialogOpen.value = true;
};

const { deleteResource } = useDeleteResource();
const deleteItem = (id) => {
    deleteResource('warehouses.destroy', id, {
        title: t('general.delete', { name: t('admin.warehouse.warehouse') }),
        description: t('general.delete_description', { name: t('admin.warehouse.warehouse') }),
        successMessage: t('general.delete_success', { name: t('admin.warehouse.warehouse') }),
    });
};
</script>

<template>
    <AppLayout :title="t('admin.warehouse.warehouses')">
        <CreateEditModal
            :isDialogOpen="isDialogOpen"
            :editingItem="editingWarehouse"
            @update:isDialogOpen="(value) => {
                isDialogOpen = value;
                if (!value) editingWarehouse = null;
            }"
            @saved="() => { editingWarehouse = null }"
        />

        <DataTable
            can="warehouses"
            :items="warehouses"
            :columns="columns"
            @edit="editItem"
            @delete="deleteItem"
            @add="isDialogOpen = true"
            :title="t('admin.warehouse.warehouses')"
            :url="`warehouses.index`"
            :showAddButton="true"
            :addTitle="t('admin.warehouse.warehouse')"
            :addAction="'modal'"
        />
    </AppLayout>
</template>

