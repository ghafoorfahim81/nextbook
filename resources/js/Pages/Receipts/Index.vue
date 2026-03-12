<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDeleteResource } from '@/composables/useDeleteResource';
import ReceiptShowDialog from '@/Pages/Receipts/ShowDialog.vue'
import { router } from '@inertiajs/vue3'
const { t } = useI18n();

const props = defineProps({
    receipts: Object,
    filters: Object,
    filterOptions: Object,
})

const { deleteResource } = useDeleteResource()
const showDialog = ref(false)
const selectedReceiptId = ref(null)
const editItem = (item) => {
    router.visit(route('receipts.edit', item.id));
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


const columns = computed(() => ([
    { key: 'number', label: t('general.number'), sortable: true },
    { key: 'ledger_name', label: 'Ledger' },
    { key: 'amount', label: t('general.amount'), sortable: true },
    { key: 'currency_code', label: t('admin.currency.currency') },
    { key: 'date', label: t('general.date'), sortable: true },
    {
        key: 'created_by.name',
        label: t('general.created_by'),
        render: (row) => row.created_by?.name ?? '-',
    },
    {
        key: 'updated_by.name',
        label: t('general.updated_by'),
        render: (row) => row.updated_by?.name ?? '-',
    },
    { key: 'actions', label: t('general.actions') },
]))

const filterFields = computed(() => ([
    {
        key: 'ledger_id',
        label: t('ledger.customer.customer'),
        type: 'select',
        options: (props.filterOptions?.customers || []).map((c) => ({ id: c.id, name: c.name })),
    },
    {
        key: 'transaction.currency_id',
        label: t('admin.currency.currency'),
        type: 'select',
        options: (props.filterOptions?.currencies || []).map((c) => ({ id: c.id, name: c.code })),
    },
    {
        key: 'transaction.lines.account_id',
        label: t('receipt.bank_account'),
        type: 'select',
        options: (props.filterOptions?.bankAccounts || []).map((a) => ({ id: a.id, name: a.name })),
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
    <AppLayout :title="t('receipt.receipts')">
        <DataTable
            can="receipts"
            :items="receipts"
            :columns="columns"
            :filters="filters"
            :filterFields="filterFields"
            :title="t('receipt.receipts')"
            :url="`receipts.index`"
            :showAddButton="true"
            :hasShow="true"
            @edit="editItem"
            @delete="deleteItem"
            @show="showItem"
            :addTitle="t('receipt.receipt')"
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
