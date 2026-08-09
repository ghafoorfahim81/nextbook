<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import CreateEditModal from './CreateEditModal.vue';
import { computed, ref } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';

defineProps({ customerGroups: Object });
const isDialogOpen = ref(false);
const editingItem = ref(null);
const { deleteResource } = useDeleteResource();
const columns = computed(() => [
    { key: 'name_en', label: 'Name (English)', sortable: true },
    { key: 'name_fa', label: 'نام فارسی', sortable: true },
    { key: 'description', label: 'Description' },
    { key: 'actions', label: 'Actions' },
]);
const editItem = (item) => { editingItem.value = item; isDialogOpen.value = true; };
const deleteItem = (id) => deleteResource('customer-groups.destroy', id);
</script>

<template>
    <AppLayout title="Customer Groups">
        <CreateEditModal :is-dialog-open="isDialogOpen" :editing-item="editingItem" @update:is-dialog-open="isDialogOpen = $event" @saved="editingItem = null" />
        <DataTable can="customer_groups" :items="customerGroups" :columns="columns" title="Customer Groups" url="customer-groups.index" :show-add-button="true" add-title="Customer Group" add-action="modal" @add="isDialogOpen = true" @edit="editItem" @delete="deleteItem" />
    </AppLayout>
</template>
