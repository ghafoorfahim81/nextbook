<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import PurchaseShowDialog from '@/Components/PurchaseShowDialog.vue';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDeleteResource } from '@/composables/useDeleteResource';
import { router } from '@inertiajs/vue3'
const { t } = useI18n();

const props = defineProps({
    purchases: Object,
    filters: Object,
    filterOptions: Object,
})

const showDialog = ref(false);
const selectedPurchaseId = ref(null);

const editItem = (item) => {
    router.visit(route('purchases.edit', item.id));
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

const filterFields = computed(() => ([
    {
        key: 'supplier_id',
        label: t('ledger.supplier.supplier'),
        type: 'select',
        options: (props.filterOptions?.suppliers || []).map((s) => ({ id: s.id, name: s.name })),
    },
    {
        key: 'transaction.currency_id',
        label: t('admin.currency.currency'),
        type: 'select',
        options: (props.filterOptions?.currencies || []).map((c) => ({ id: c.id, name: c.code })),
    },
    {
        key: 'type',
        label: t('general.type'),
        type: 'select',
        options: (props.filterOptions?.types || []).map((o) => ({ id: o.id, name: o.name })),
    },
    {
        key: 'store_id',
        label: t('admin.store.store'),
        type: 'select',
        options: (props.filterOptions?.stores || []).map((s) => ({ id: s.id, name: s.name })),
    },
    { key: 'date', label: t('general.date'), type: 'daterange' },
    {
        key: 'created_by',
        label: t('general.created_by'),
        type: 'select',
        options: (props.filterOptions?.users || []).map((u) => ({ id: u.id, name: u.name })),
    },
]))
</script>

<template>
    <AppLayout :title="t('purchase.purchase')">
        <DataTable
         can="purchases"
         :items="purchases"
         :columns="columns"
         :filters="filters"
         :filterFields="filterFields"
         :title="t('purchase.purchases')"
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
