<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import SupplierShowDialog from '@/Components/SupplierShowDialog.vue';
import { ref, computed } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    suppliers: Object,
});

const { t } = useI18n();
const { deleteResource } = useDeleteResource();

const columns = computed(() => ([
    { key: 'name', label: t('general.name') },
    { key: 'code', label: t('admin.currency.code') },
    { key: 'contact_person', label: t('ledger.contact_person') },
    { key: 'phone_no', label: t('general.phone') },
    { key: 'email', label: t('general.email') },
    { key: 'actions', label: t('general.actions') },
]));

const editItem = (item) => {
    window.location.href = `/suppliers/${item.id}/edit`;
};

const showDialog = ref(false);
const selectedSupplierId = ref(null);

const showItem = (item) => {
    selectedSupplierId.value = item;
    showDialog.value = true;
};

const deleteItem = (id) => {
    deleteResource('suppliers.destroy', id, {
        title: t('general.delete', { name: t('ledger.supplier.supplier') }),
        description: t('general.delete_description', { name: t('ledger.supplier.supplier') }),
        successMessage: t('general.delete_success', { name: t('ledger.supplier.supplier') }),
    });
};
</script>

<template>
    <AppLayout :title="t('ledger.supplier.suppliers')">
        <DataTable
            :items="suppliers"
            :columns="columns"
            @delete="deleteItem"
            @edit="editItem"
            @show="showItem"
            :title="t('ledger.supplier.suppliers')"
            :url="`suppliers.index`"
            :hasShow="true"
            :showAddButton="true"
            :addTitle="t('ledger.supplier.supplier')"
            :addAction="'redirect'"
            :addRoute="'suppliers.create'"
        />

        <SupplierShowDialog
            v-model:open="showDialog"
            :supplier-id="selectedSupplierId"
        />
    </AppLayout>
</template>
