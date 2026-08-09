<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import CreateEditModal from './CreateEditModal.vue';
import { computed, ref } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';

defineProps({ paymentTerms: Object });
const isDialogOpen = ref(false);
const editingItem = ref(null);
const { deleteResource } = useDeleteResource();
const columns = computed(() => [
    { key: 'name', label: 'Name', sortable: true },
    { key: 'days', label: 'Days', sortable: true },
    { key: 'type', label: 'Type', sortable: true },
    { key: 'actions', label: 'Actions' },
]);
const editItem = (item) => { editingItem.value = item; isDialogOpen.value = true; };
const deleteItem = (id) => deleteResource('payment-terms.destroy', id);
</script>

<template>
    <AppLayout title="Payment Terms">
        <CreateEditModal :is-dialog-open="isDialogOpen" :editing-item="editingItem" @update:is-dialog-open="isDialogOpen = $event" @saved="editingItem = null" />
        <DataTable can="payment_terms" :items="paymentTerms" :columns="columns" title="Payment Terms" url="payment-terms.index" :show-add-button="true" add-title="Payment Term" add-action="modal" @add="isDialogOpen = true" @edit="editItem" @delete="deleteItem" />
    </AppLayout>
</template>
