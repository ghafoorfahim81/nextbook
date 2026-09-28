<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import CreateEditModal from './CreateEditModal.vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDeleteResource } from '@/composables/useDeleteResource';

defineProps({ paymentTerms: Object });
const { t } = useI18n();
const isDialogOpen = ref(false);
const editingItem = ref(null);
const { deleteResource } = useDeleteResource();
const columns = computed(() => [
    { key: 'name', label: t('admin.payment_term.name'), sortable: true },
    { key: 'days', label: t('admin.payment_term.days'), sortable: true },
    { key: 'type', label: t('admin.payment_term.type'), sortable: true },
    { key: 'actions', label: t('general.action') },
]);
const editItem = (item) => { editingItem.value = item; isDialogOpen.value = true; };
const deleteItem = (id) => deleteResource('payment-terms.destroy', id, {
    name: t('admin.payment_term.payment_term'),
});
</script>

<template>
    <AppLayout :title="t('admin.payment_term.payment_terms')">
        <CreateEditModal :is-dialog-open="isDialogOpen" :editing-item="editingItem" @update:is-dialog-open="isDialogOpen = $event" @saved="editingItem = null" />
        <DataTable can="payment_terms" :items="paymentTerms" :columns="columns" :title="t('admin.payment_term.payment_terms')" url="payment-terms.index" :show-add-button="true" :add-title="t('admin.payment_term.payment_term')" add-action="modal" @add="isDialogOpen = true" @edit="editItem" @delete="deleteItem" />
    </AppLayout>
</template>
