<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDeleteResource } from '@/composables/useDeleteResource';
import ReceiptShowDialog from '@/Pages/Receipts/ShowDialog.vue'
const { t } = useI18n();

const props = defineProps({
    receipts: Object,
})

const { deleteResource } = useDeleteResource()
const showDialog = ref(false)
const selectedReceiptId = ref(null)
const editItem = (item) => {
    window.location.href = `/receipts/${item.id}/edit`;
}
const deleteItem = (id) => {
    deleteResource('receipts.destroy', id, {
        title: t('general.delete', { name: 'Receipt' }),
        name: 'Receipt',
    })
}
const showItem = (id) => {
    selectedReceiptId.value = id
    showDialog.value = true
}


const columns = ref([
    { key: 'number', label: t('general.number'), sortable: true },
    { key: 'ledger_name', label: 'Ledger' },
    { key: 'amount', label: t('general.amount'), sortable: true },
    { key: 'currency_code', label: t('admin.currency.currency') },
    { key: 'date', label: t('general.date'), sortable: true },
    { key: 'actions', label: t('general.actions') },
])
</script>

<template>
    <AppLayout title="Receipts">
        <DataTable
            :items="receipts"
            :columns="columns"
            :title="'Receipts'"
            :url="`receipts.index`"
            :showAddButton="true"
            :hasShow="true"
            @edit="editItem"
            @delete="deleteItem"
            @show="showItem"
            :addTitle="'Receipt'"
            :addAction="'redirect'"
            :addRoute="'receipts.create'"
        />
        <ReceiptShowDialog
            :open="showDialog"
            :receipt-id="selectedReceiptId"
            @update:open="showDialog = $event"
        />
    </AppLayout>
</template>
