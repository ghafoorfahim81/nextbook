<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from './CreateEditModal.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    categories: Object,
});

const isDialogOpen = ref(false);
const editingCategory = ref(null);
const { t } = useI18n();

const columns = computed(() => [
    { key: 'name', label: t('general.name'), sortable: true },
    { key: 'remarks', label: t('general.remarks') },
    { 
        key: 'is_active', 
        label: t('general.status'),
        render: (row) => row.is_active ? t('general.active') : t('general.inactive'),
    },
    { key: 'actions', label: t('general.action') },
]);

const editItem = (item) => {
    editingCategory.value = item;
    isDialogOpen.value = true;
};

const { deleteResource } = useDeleteResource();

const deleteItem = (id) => {
    deleteResource('expense-categories.destroy', id, {
        title: t('general.delete', { name: t('expense.category') }),
        description: t('general.delete_description', { name: t('expense.category') }),
        successMessage: t('general.delete_success', { name: t('expense.category') }),
    });
};
</script>

<template>
    <AppLayout :title="t('expense.categories')">
        <CreateEditModal
            :isDialogOpen="isDialogOpen"
            :editingItem="editingCategory"
            @update:isDialogOpen="(value) => {
                isDialogOpen = value;
                if (!value) editingCategory = null;
            }"
            @saved="() => { editingCategory = null }"
        />
        <DataTable
            :items="categories"
            :columns="columns"
            @edit="editItem"
            @delete="deleteItem"
            @add="isDialogOpen = true"
            :title="t('expense.categories')"
            :url="`expense-categories.index`"
            :showAddButton="true"
            :addTitle="t('expense.category')"
            :addAction="'modal'"
        />
    </AppLayout>
</template>

