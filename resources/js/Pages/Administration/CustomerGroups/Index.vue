<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import CreateEditModal from './CreateEditModal.vue';
import { computed, ref } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps({ customerGroups: Object });
const isDialogOpen = ref(false);
const editingItem = ref(null);
const { deleteResource } = useDeleteResource();
const columns = computed(() => [
    { key: 'name_en', label: t('admin.customer_group.name_en'), sortable: true },
    { key: 'local_name', label: t('admin.customer_group.local_name'), sortable: true },
    { key: 'description', label: t('general.description') },
    { key: 'actions', label: t('general.actions') },
]);
const editItem = (item) => { editingItem.value = item; isDialogOpen.value = true; };
const deleteItem = (id) => {
    deleteResource('customer-groups.destroy', id, {
        title: t('general.delete', { name: t('admin.customer_group.customer_group') }),
        description: t('general.delete_description', { name: t('admin.customer_group.customer_group') }),
        successMessage: t('general.delete_success', { name: t('admin.customer_group.customer_group') }),
    });
};
</script>

<template>
    <AppLayout :title="t('admin.customer_group.customer_groups')">
        <CreateEditModal :is-dialog-open="isDialogOpen" :editing-item="editingItem" @update:is-dialog-open="isDialogOpen = $event" @saved="editingItem = null" />
        <DataTable
            can="customer_groups"
            :items="customerGroups"
            :columns="columns"
            :title="t('admin.customer_group.customer_groups')"
            url="customer-groups.index"
            :show-add-button="true"
            :add-title="t('admin.customer_group.customer_group')"
            add-action="modal"
            @add="isDialogOpen = true"
            @edit="editItem"
            @delete="deleteItem"
        />
    </AppLayout>
</template>
