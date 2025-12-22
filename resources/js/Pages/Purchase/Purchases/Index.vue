<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import PurchaseShowDialog from '@/Components/PurchaseShowDialog.vue';
import { ref, computed } from 'vue';
import { Button } from '@/Components/ui/button';
import { useI18n } from 'vue-i18n';
import { useDeleteResource } from '@/composables/useDeleteResource';
const { t } = useI18n();

const showFilter = () => {
    showFilter.value = true;
}

const props = defineProps({
    purchases: Object,
})

const showDialog = ref(false);
const selectedPurchaseId = ref(null);

const editItem = (item) => {
    window.location.href = `/purchases/${item.id}/edit`;
}

const { deleteResource } = useDeleteResource()
const deleteItem = (id) => {
    deleteResource('purchases.destroy', id, {
        title: t('general.delete', { name: t('purchase.purchase') }),
    })
}

const showItem = (id) => {
    selectedPurchaseId.value = id;
    showDialog.value = true;
}

const columns = computed(() => ([
    { key: 'number', label: t('general.number'), sortable: true },
    { key: 'supplier_name', label: t('ledger.supplier.supplier') },
    { key: 'amount', label: t('general.amount'), sortable: true },
    { key: 'date', label: t('general.date'), sortable: true },
    { key: 'type', label: t('general.type'), sortable: true },
    { key: 'status', label: t('general.status') },
    { key: 'actions', label: t('general.actions') },
]))
</script>

<template>
    <AppLayout :title="t('purchase.purchase')">
        <DataTable :items="purchases" :columns="columns"
         :title="t('purchase.purchase')"
         :url="`purchases.index`"
         :showAddButton="true"
         :showEditButton="true"
         :showDeleteButton="true"
         :hasShow="true"
         @edit="editItem"
         @delete="deleteItem"
         @show="showItem"
         :addTitle="t('purchase.purchase')"
         :addAction="'redirect'"
         :addRoute="'purchases.create'"
         />

        <!-- Purchase Show Dialog -->
        <PurchaseShowDialog
            :open="showDialog"
            :purchase-id="selectedPurchaseId"
            @update:open="showDialog = $event"
        />
    </AppLayout>
</template>
