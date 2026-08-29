<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { computed, ref } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from './CreateEditModal.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    items: Object,
    filters: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const isDialogOpen = ref(false);
const editingItem = ref(null);

const columns = computed(() => ([
    { key: 'name', label: t('general.name'), sortable: true },
    { key: 'remark', label: t('general.remark') },
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
    deleteResource('landed-cost-categories.destroy', id, {
        title: t('general.delete', { name: t('admin.landed_cost_category.landed_cost_category') }),
        description: t('general.delete_description', { name: t('admin.landed_cost_category.landed_cost_category') }),
        successMessage: t('general.delete_success', { name: t('admin.landed_cost_category.landed_cost_category') }),
    });
};
</script>

<template>
    <AppLayout :title="t('sidebar.administration.landed_cost_category')">
        <CreateEditModal
            :isDialogOpen="isDialogOpen"
            :editingItem="editingItem"
            @update:isDialogOpen="(value) => {
                isDialogOpen = value;
                if (!value) editingItem = null;
            }"
            @saved="() => { editingItem = null }"
        />
        <DataTable
            can="landed_cost_categories"
            :items="items"
            :columns="columns"
            :filters="filters"
            @edit="editItem"
            @delete="deleteItem"
            @add="isDialogOpen = true"
            :title="t('sidebar.administration.landed_cost_category')"
            :url="`landed-cost-categories.index`"
            :showAddButton="true"
            :addTitle="t('admin.landed_cost_category.landed_cost_category')"
            :addAction="'modal'"
        />
    </AppLayout>
</template>
