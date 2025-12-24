<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from './CreateEditModal.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    sizes: Object,
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
    { key: 'code', label: t('admin.currency.code'), sortable: true },
    { key: 'actions', label: t('general.action') },
]));

const { deleteResource } = useDeleteResource();
const deleteItem = (id) => {
    deleteResource('sizes.destroy', id, {
        title: t('general.delete', { name: t('admin.size.size') }),
        description: t('general.delete_description', { name: t('admin.size.size') }),
        successMessage: t('general.delete_success', { name: t('admin.size.size') }),
    });
};

const editItem = (item) => {
    editingItem.value = item;
    isDialogOpen.value = true;
};
</script>

<template>
    <AppLayout :title="t('admin.size.sizes')">
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
            :items="sizes"
            :columns="columns"
            @edit="editItem"
            @delete="deleteItem"
            @add="isDialogOpen = true"
            :title="t('admin.size.sizes')"
            :url="`sizes.index`"
            :showAddButton="true"
            :addTitle="t('admin.size.size')"
            :addAction="'modal'"
        />
    </AppLayout>
</template>
