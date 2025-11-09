<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDeleteResource } from '@/composables/useDeleteResource';
import PaymentShowDialog from '@/Pages/Payments/ShowDialog.vue'
const { t } = useI18n();

const props = defineProps({
    payments: Object,
})

const { deleteResource } = useDeleteResource()
const showDialog = ref(false)
const selectedPaymentId = ref(null)

const editItem = (item) => {
    window.location.href = `/payments/${item.id}/edit`;
}
const deleteItem = (id) => {
    deleteResource('payments.destroy', id, {
        title: t('general.delete', { name: 'Payment' }),
        name: 'Payment',
    })
}
const showItem = (id) => {
    selectedPaymentId.value = id
    showDialog.value = true
}

const columns = ref([
    { key: 'number', label: t('general.number'), sortable: true },
    { key: 'ledger_name', label: t('ledger.supplier.supplier') },
    { key: 'amount', label: t('general.amount'), sortable: true },
    { key: 'currency_code', label: t('admin.currency.currency') },
    { key: 'date', label: t('general.date'), sortable: true },
    { key: 'actions', label: t('general.actions') },
])
</script>

<template>
    <AppLayout :title="t('payment.payments')">
        <DataTable
            :items="payments"
            :columns="columns"
            :title="t('payment.payments')"
            :url="`payments.index`"
            :showAddButton="true"
            :hasShow="true"
            @edit="editItem"
            @delete="deleteItem"
            @show="showItem"
            :addTitle="t('payment.payment')"
            :addAction="'redirect'"
            :addRoute="'payments.create'"
        />
        <PaymentShowDialog
            :open="showDialog"
            :payment-id="selectedPaymentId"
            @update:open="showDialog = $event"
        />
    </AppLayout>
</template>


