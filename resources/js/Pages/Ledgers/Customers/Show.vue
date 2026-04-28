<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import LedgerListTable from '@/Components/reports/LedgerListTable.vue';

const props = defineProps({
    customer: { type: Object, required: true },
    sales: { type: Object, required: false },
    receipts: { type: Object, required: false },
    payments: { type: Object, required: false },
});

const { t } = useI18n();

const customerData = computed(() => props.customer?.data ?? props.customer ?? {});
const statement = computed(() => customerData.value.statement ?? {});
const openings = computed(() => customerData.value.openings ?? []);

const salesRows = computed(() => props.sales?.data ?? props.sales ?? []);
const receiptRows = computed(() => props.receipts?.data ?? props.receipts ?? []);
const paymentRows = computed(() => props.payments?.data ?? props.payments ?? []);

const activeMainTab = ref('general');
const activeTxnTab = ref('sales');

const formatAmount = (value) => {
    if (value === null || value === undefined) return '-';
    return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const exportUrl = (list) => route('customers.export', {
    customer: customerData.value.id,
    list,
});

const customerSalesTableRows = computed(() => salesRows.value.map((row) => ({
    id: row.id,
    number: row.number || row.reference_id || row.id,
    date: row.date,
    type: row.type || '-',
    amount: row.amount,
    status: row.payment_status_label || row.payment_status || '-',
    description: row.description || '-',
})));

const customerReceiptTableRows = computed(() => receiptRows.value.map((row) => ({
    id: row.id,
    number: row.number || row.reference_id || row.id,
    date: row.date,
    amount: row.amount,
    currency: row.currency_code || row.transaction?.currency?.code || row.transaction?.currency?.name || '',
    rate: row.rate || 0,
    payment_mode: row.payment_mode_label || row.payment_mode || '-',
    description: row.narration || row.description || '-',
})));

const customerPaymentTableRows = computed(() => paymentRows.value.map((row) => ({
    id: row.id,
    number: row.number || row.reference_id || row.id,
    date: row.date,
    amount: row.amount,
    currency: row.currency_code || row.transaction?.currency?.code || row.transaction?.currency?.name || '',
    rate: row.rate || 0,
    payment_mode: row.payment_mode_label || row.payment_mode || '-',
    description: row.narration || row.description || '-',
})));

const customerSalesColumns = computed(() => [
    { key: 'number', label: t('general.number') },
    { key: 'date', label: t('general.date') },
    { key: 'type', label: t('general.type') },
    { key: 'amount', label: t('general.amount'), type: 'money', align: 'right' },
    { key: 'status', label: t('general.status') },
    { key: 'description', label: t('general.description') },
]);

const customerMovementColumns = computed(() => [
    { key: 'number', label: t('general.number') },
    { key: 'date', label: t('general.date') },
    { key: 'amount', label: t('general.amount'), type: 'money', align: 'right' },
    { key: 'currency', label: t('admin.currency.currency') },
    { key: 'rate', label: t('general.rate'), type: 'money', align: 'right' },
    { key: 'payment_mode', label: t('general.payment_method') },
    { key: 'description', label: t('general.description') },
]);
</script>

<template>
    <AppLayout :title="`${t('ledger.customer.customer')} - ${customerData.name || ''}`">
        <div class="space-y-4">
            <!-- Top tabs: General / Opening -->
            <div class="border-b border-border flex gap-4">
                <button
                    type="button"
                    class="px-4 py-2 -mb-px border-b-2 transition-colors"
                    :class="activeMainTab === 'general'
                        ? 'border-primary text-primary font-semibold'
                        : 'border-transparent text-muted-foreground hover:text-foreground'"
                    @click="activeMainTab = 'general'"
                >
                    {{ t('general.general') }}
                </button>
                <button
                    type="button"
                    class="px-4 py-2 -mb-px border-b-2 transition-colors"
                    :class="activeMainTab === 'opening'
                        ? 'border-primary text-primary font-semibold'
                        : 'border-transparent text-muted-foreground hover:text-foreground'"
                    @click="activeMainTab = 'opening'"
                >
                    {{ t('item.opening') }}
                </button>
            </div>

            <!-- GENERAL TAB -->
            <div v-if="activeMainTab === 'general'" class="space-y-4">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <!-- Left: avatar + statement -->
                    <div class="bg-card text-card-foreground rounded-xl shadow-sm border border-border p-4 flex flex-col items-center gap-4">
                        <div class="w-20 h-20 rounded-full bg-gradient-to-tr from-blue-500 to-indigo-500 flex items-center justify-center text-white text-2xl font-bold">
                            {{ (customerData.name || '').charAt(0).toUpperCase() }}
                        </div>
                        <div class="text-center">
                            <div class="text-lg font-semibold text-primary">
                                {{ customerData.name }}
                            </div>
                            <div class="text-xs text-muted-foreground mt-1">
                                {{ customerData.code }}
                            </div>
                            <div class="mt-2 text-xs text-muted-foreground/70">
                                {{ t('ledger.customer.customer') }}
                            </div>
                        </div>

                        <!-- Statement summary -->
                        <div class="w-full grid grid-cols-3 gap-2 mt-4">
                            <div class="border border-border rounded-lg px-3 py-2 text-center bg-background">
                                <div class="text-xs text-muted-foreground">{{ t('general.credit') }}</div>
                                <div class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                                    {{  (statement.total_credit) }}
                                </div>
                            </div> 1486315
                            <div class="border border-border rounded-lg px-3 py-2 text-center bg-background">
                                <div class="text-xs text-muted-foreground">{{ t('general.debit') }}</div>
                                <div class="text-sm font-semibold text-blue-600 dark:text-blue-400">
                                    {{  (statement.total_debit) }}
                                </div>
                            </div>
                            <div class="border border-border rounded-lg px-3 py-2 text-center bg-background">
                                <div class="text-xs text-muted-foreground">{{ t('general.balance') }}</div>
                                <div class="text-sm font-semibold"
                                    :class="statement.balance_nature === 'cr'
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : 'text-blue-600 dark:text-blue-400'">
                                    {{  (statement.balance) }} 
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: basic info -->
                    <div class="lg:col-span-2 bg-card text-card-foreground rounded-xl shadow-sm border border-border p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="text-xs text-muted-foreground">{{ t('general.name') }}</div>
                                <div class="font-medium text-foreground">{{ customerData.name }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">{{ t('ledger.contact_person') }}</div>
                                <div class="font-medium text-foreground">{{ customerData.contact_person }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">{{ t('general.phone') }}</div>
                                <div class="font-medium text-foreground">{{ customerData.phone_no }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">{{ t('general.email') }}</div>
                                <div class="font-medium text-foreground">{{ customerData.email }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">{{ t('admin.currency.currency') }}</div>
                                <div class="font-medium text-foreground">{{ customerData.currency?.name || '' }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">{{ t('general.branch') }}</div>
                                <div class="font-medium text-foreground">{{ customerData.branch?.name || '' }}</div>
                            </div>
                            <div class="md:col-span-2">
                                <div class="text-xs text-muted-foreground">{{ t('general.address') }}</div>
                                <div class="font-medium text-foreground">{{ customerData.address }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sales / Receipts / Payments tables -->
                <div class="bg-card text-card-foreground rounded-xl shadow-sm border border-border p-4">
                    <div class="flex flex-wrap items-center gap-3 mb-4">
                        <button
                            type="button"
                            class="px-3 py-1.5 text-sm rounded-full transition-colors"
                            :class="activeTxnTab === 'sales'
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                            @click="activeTxnTab = 'sales'"
                        >
                            {{ t('sale.sales') }}
                        </button>
                        <button
                            type="button"
                            class="px-3 py-1.5 text-sm rounded-full transition-colors"
                            :class="activeTxnTab === 'receipts'
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                            @click="activeTxnTab = 'receipts'"
                        >
                            {{ t('receipt.receipts') }}
                        </button>
                        <button
                            type="button"
                            class="px-3 py-1.5 text-sm rounded-full transition-colors"
                            :class="activeTxnTab === 'payments'
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                            @click="activeTxnTab = 'payments'"
                        >
                            {{ t('payment.payments') }}
                        </button>
                    </div>

                    <LedgerListTable
                        v-if="activeTxnTab === 'sales'"
                        :title="t('sale.sales')"
                        :rows="customerSalesTableRows"
                        :columns="customerSalesColumns"
                        :empty-message="t('general.no_data_found')"
                        :export-url="exportUrl('sales')"
                        :export-label="t('report.export_excel')"
                        :row-number-label="t('report.columns.no')"
                        default-sort-key="date"
                        default-sort-direction="desc"
                    />
                    <LedgerListTable
                        v-else-if="activeTxnTab === 'receipts'"
                        :title="t('receipt.receipts')"
                        :rows="customerReceiptTableRows"
                        :columns="customerMovementColumns"
                        :empty-message="t('general.no_data_found')"
                        :export-url="exportUrl('receipts')"
                        :export-label="t('report.export_excel')"
                        :row-number-label="t('report.columns.no')"
                        default-sort-key="date"
                        default-sort-direction="desc"
                    />
                    <LedgerListTable
                        v-else
                        :title="t('payment.payments')"
                        :rows="customerPaymentTableRows"
                        :columns="customerMovementColumns"
                        :empty-message="t('general.no_data_found')"
                        :export-url="exportUrl('payments')"
                        :export-label="t('report.export_excel')"
                        :row-number-label="t('report.columns.no')"
                        default-sort-key="date"
                        default-sort-direction="desc"
                    />
                </div>
            </div>

            <!-- OPENING TAB -->
            <div v-else class="bg-card text-card-foreground rounded-xl shadow-sm border border-border p-4">
                <div class="text-sm font-semibold mb-3 text-foreground">
                    {{ t('item.opening') }}
                </div>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-left rtl:text-right text-muted-foreground">
                            <th class="py-2 pr-4">{{ t('admin.currency.currency') }}</th>
                            <th class="py-2 pr-4">{{ t('general.amount') }}</th>
                            <th class="py-2 pr-4">{{ t('general.rate') }}</th>
                            <th class="py-2 pr-4">{{ t('general.type') }}</th>
                            <th class="py-2 pr-4">{{ t('general.date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!openings.length">
                            <td colspan="5" class="py-4 text-center text-muted-foreground">
                                {{ t('general.no_data_found') }}
                            </td>
                        </tr>
                        <tr
                            v-for="opening in openings"
                            :key="opening.id"
                            class="border-b border-border last:border-b-0 text-foreground"
                        >
                            <td class="py-2 pr-4">{{ opening.currency?.name || '' }}</td>
                            <td class="py-2 pr-4">{{ formatAmount(opening.amount) }}</td>
                            <td class="py-2 pr-4">{{ opening.rate }}</td>
                            <td class="py-2 pr-4 capitalize">{{ opening.type }}</td>
                            <td class="py-2 pr-4">{{ opening.date }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
