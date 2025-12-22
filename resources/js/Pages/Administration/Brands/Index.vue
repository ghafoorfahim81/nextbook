<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from './CreateEditModal.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    brands: Object,
    branches: {
        type: Array,
        required: true,
    },
});

const isDialogOpen = ref(false);
const editingItem = ref(null);
const { t } = useI18n();

const columns = computed(() => ([
    { key: 'name', label: t('general.name'), sortable: true },
    { key: 'description', label: t('admin.shared.remark') },
    { key: 'website', label: t('admin.brand.website') },
    { key: 'logo', label: t('admin.shared.logo') },
    { key: 'actions', label: t('general.action') },
]));

const { deleteResource } = useDeleteResource();
const deleteItem = (id) => {
    deleteResource('brands.destroy', id, {
        title: t('general.delete', { name: t('admin.brand.brand') }),
        description: t('general.delete_description', { name: t('admin.brand.brand') }),
        successMessage: t('general.delete_success', { name: t('admin.brand.brand') }),
    });
};

const editItem = (item) => {
    editingItem.value = item;
    isDialogOpen.value = true;
};
</script>

<template>
    <AppLayout :title="t('admin.brand.brands')">
        <CreateEditModal
            :isDialogOpen="isDialogOpen"
            :editingItem="editingItem"
            :branches="branches"
            @update:isDialogOpen="(value) => {
                isDialogOpen = value;
                if (!value) editingItem = null;
            }"
            @saved="() => { editingItem = null }"
        />
        <DataTable
            :items="brands"
            :columns="columns"
            @edit="editItem"
            @delete="deleteItem"
            @add="isDialogOpen = true"
            :title="t('admin.brand.brands')"
            :url="`brands.index`"
            :showAddButton="true"
            :addTitle="t('admin.brand.brand')"
            :addAction="'modal'"
        />
    </AppLayout>
</template>
