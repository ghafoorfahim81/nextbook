<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { router, usePage } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import LedgerListTable from '@/Components/reports/LedgerListTable.vue';
import TimeSeriesChart from '@/Components/charts/TimeSeriesChart.vue';
import { ArrowLeft, SquarePen } from 'lucide-vue-next';
import { useAuth } from '@/composables/useAuth';

const { t } = useI18n();
const { can } = useAuth();
const page = usePage();

const props = defineProps({
    account: { type: Object, required: true },
    transactions: { type: [Array, Object], required: false, default: () => [] },
    /** Native totals per currency, from AccountController::currencyBalances(). */
    currencyBalances: { type: Array, required: false, default: () => [] },
    /** The whole account in its own currency, from AccountController::convertedBalance(). */
    convertedBalance: { type: Object, required: false, default: null },
    opening: { type: Object, required: false, default: null },
    balanceNatureFormat: { type: String, default: null },
});

const accountData = computed(() => props.account?.data ?? props.account ?? {});
const transactionList = computed(() => props.transactions?.data ?? props.transactions ?? []);
const openings = computed(() => props.opening ? [props.opening?.data ?? props.opening] : []);

const activeMainTab = ref('general');

const formatAmount = (value) => {
    if (value === null || value === undefined) return '-';
    return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const transactionRows = computed(() => {
    return transactionList.value.flatMap((txn) => {
        const lines = Array.isArray(txn?.lines) ? txn.lines : [];
        return lines.map((line, lineIndex) => {
            const debit = Number(line?.debit || 0);
            const credit = Number(line?.credit || 0);
            const type = debit > 0 ? 'debit' : credit > 0 ? 'credit' : '';
            const amount = debit > 0 ? debit : credit;
            return {
                id: line?.id || `${txn?.id}-${lineIndex}`,
                type, amount,
                rate: txn?.rate || 1,
                date: txn?.date,
                transaction_number: txn?.voucher_number,
                description: line?.remark || txn?.remark || '-',
                currency: txn?.currency?.code || '',
                status: txn?.status || '',
                remark: line?.remark ?? txn?.remark ?? '',
            };
        });
    });
});

// Amounts stay in the transaction's own currency: a USD cash account holds
// dollars, not their home-currency equivalent. The currency and rate columns
// carry the conversion detail.
const transactionTableRows = computed(() =>
    transactionRows.value.map((row) => ({
        id: row.id,
        date: row.date,
        reference_number: row.transaction_number,
        description: row.description || row.remark || '-',
        status: row.status,
        // Amounts stay in the currency they were posted in; `rate` is its own
        // column, so multiplying here would report the base value twice over.
        debit: Number(row.type === 'debit' ? row.amount : 0),
        credit: Number(row.type === 'credit' ? row.amount : 0),
        currency: row.currency || '',
        rate: Number(row.rate || 1),
    }))
);

const transactionColumns = computed(() => [
    { key: 'date', label: t('general.date') },
    { key: 'reference_number', label: t('general.reference_number') },
    { key: 'description', label: t('general.description') },
    { key: 'currency', label: t('admin.currency.currency') },
    { key: 'rate', label: t('general.rate'), type: 'money', align: 'right' },
    { key: 'status', label: t('general.status') },
    { key: 'credit', label: t('general.credit'), type: 'money', align: 'right' },
    { key: 'debit', label: t('general.debit'), type: 'money', align: 'right' },
]);

const homeCurrencyCode = computed(() => page.props?.homeCurrency?.code
    || props.currencyBalances.find((row) => row.is_base_currency)?.currency_code
    || '');

// Currencies never net against each other, so the summary keeps one block per
// currency the account has moved in instead of one converted total.
const currencySections = computed(() => (props.currencyBalances.length
    ? props.currencyBalances
    : [{
        currency_id: 'none',
        currency_code: homeCurrencyCode.value,
        currency_name: '',
        is_base_currency: true,
        total_debit: 0,
        total_credit: 0,
        balance: 0,
        balance_nature: null,
        home_equivalent: 0,
    }]));

// Only meaningful once the account mixes currencies; with a single one it just
// repeats the block above it.
const homeEquivalent = computed(() => (props.currencyBalances.length > 1
    ? props.currencyBalances.reduce((total, row) => total + Number(row.home_equivalent || 0), 0)
    : null));

const balanceDisplay = (section) => {
    const amount = Number(section.balance || 0);
    const nature = section.balance_nature;
    if (!amount || !nature) return formatAmount(0);
    if (props.balanceNatureFormat === 'without_nature') return formatAmount(amount);
    return `${formatAmount(amount)} ${nature.toUpperCase()}`;
};

// The account read as a whole, in the currency it is held in. Only worth a row
// once a second currency is in play; with one it repeats the block above it.
const convertedTotal = computed(() => (currencySections.value.length > 1 ? props.convertedBalance : null));

const convertedTotalDisplay = computed(() => (convertedTotal.value
    ? balanceDisplay({ balance: convertedTotal.value.amount, balance_nature: convertedTotal.value.balance_nature })
    : ''));

const debitCreditSeries = computed(() => [
    { key: 'debit', label: t('general.debit') },
    { key: 'credit', label: t('general.credit') },
]);

// Daily totals across every currency the account moved in, same amounts the
// transactions table shows (no cross-currency conversion).
const debitCreditPoints = computed(() => {
    const byDate = new Map();
    transactionTableRows.value.forEach((row) => {
        const date = row.date || '';
        if (!byDate.has(date)) byDate.set(date, { date, values: { debit: 0, credit: 0 } });
        const entry = byDate.get(date);
        entry.values.debit += Number(row.debit || 0);
        entry.values.credit += Number(row.credit || 0);
    });
    return Array.from(byDate.values()).sort((a, b) => (a.date > b.date ? 1 : a.date < b.date ? -1 : 0));
});

const debitCreditSubtitle = computed(() => {
    const points = debitCreditPoints.value;
    if (!points.length) return '';
    return points.length > 1
        ? `${points[0].date} – ${points[points.length - 1].date}`
        : points[0].date;
});

const exportUrl = computed(() =>
    accountData.value?.id
        ? route('chart-of-accounts.export-transactions', { chart_of_account: accountData.value.id })
        : ''
);
</script>

<template>
    <AppLayout :title="`${t('account.account')} - ${accountData.local_name || accountData.name || ''}`">
        <div class="space-y-4">
            <!-- Back + edit -->
            <div class="flex flex-wrap items-center justify-between gap-3"> 
                <Button
                type="button"
                variant="outline"
                size="sm"
                class="h-8 gap-1.5 bg-background border-primary/60 hover:bg-primary/40 hover:text-balck"
                @click="router.visit(route('chart-of-accounts.index'))"
            >
                <ArrowLeft class="h-4 w-4 rtl:rotate-180 text-primary" />
                {{ t('general.back') }}
            </Button>
                <Button
                    v-if="can('accounts.update') && accountData.id"
                    variant="default"
                    size="sm"
                    class="gap-1.5 bg-primary text-primary-foreground"
                    @click="router.visit(route('chart-of-accounts.edit', accountData.id))"
                >
                    <SquarePen class="h-4 w-4" />
                    {{ t('datatable.edit') }}
                </Button>
            </div>

            <!-- Tabs -->
            <div class="border-b border-border flex gap-4">
                <button type="button" class="px-4 py-2 -mb-px border-b-2"
                    :class="activeMainTab === 'general' ? 'border-primary text-primary font-semibold' : 'border-transparent text-muted-foreground hover:text-foreground'"
                    @click="activeMainTab = 'general'">
                    {{ t('general.general') }}
                </button>
                <button type="button" class="px-4 py-2 -mb-px border-b-2"
                    :class="activeMainTab === 'opening' ? 'border-primary text-primary font-semibold' : 'border-transparent text-muted-foreground hover:text-foreground'"
                    @click="activeMainTab = 'opening'">
                    {{ t('item.opening') }}
                </button>
            </div>

            <!-- General tab -->
            <div v-if="activeMainTab === 'general'" class="space-y-3">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-2">
                    <!-- Avatar + summary -->
                    <div class="bg-card text-card-foreground rounded-xl shadow-sm border border-border p-4 flex flex-col items-center gap-4">
                        <div class="w-16 h-16 rounded-full bg-gradient-to-tr from-indigo-500 to-blue-500 flex items-center justify-center text-white text-xl font-bold">
                            {{ (accountData.local_name || '').charAt(0).toUpperCase() }}
                        </div>
                        <div class="text-center">
                            <div class="text-base font-semibold text-primary">{{ accountData.local_name }}</div>
                            <div class="text-xs text-muted-foreground mt-1">{{ accountData.number }}</div>
                            <div class="mt-2 text-xs text-muted-foreground/80">{{ t('account.account') }}</div>
                        </div>
                        <div class="w-full bg-background border border-border rounded-xl overflow-hidden mt-4 divide-y divide-border">
                            <div v-for="section in currencySections" :key="section.currency_id" class="flex flex-col divide-y divide-border">
                                <div class="flex items-center gap-2 px-5 py-2 bg-muted/40">
                                    <span class="text-sm font-semibold text-foreground">{{ section.currency_code }}</span>
                                    <span
                                        v-if="section.is_base_currency"
                                        class="rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground"
                                    >
                                        {{ t('admin.currency.home_currency') }}
                                    </span>
                                    <span class="truncate text-xs text-muted-foreground">{{ section.currency_name }}</span>
                                </div>
                                <div class="flex items-center px-5 py-2">
                                    <div class="flex-1 text-base text-foreground">{{ t('general.credit') }}</div>
                                    <div class="text-base font-medium text-green-600">{{ formatAmount(section.total_credit) }}</div>
                                </div>
                                <div class="flex items-center px-5 py-2">
                                    <div class="flex-1 text-base text-foreground">{{ t('general.debit') }}</div>
                                    <div class="text-base font-medium text-green-600">{{ formatAmount(section.total_debit) }}</div>
                                </div>
                                <div class="flex items-center px-5 py-2">
                                    <div class="flex-1 text-base text-foreground">{{ t('general.balance') }}</div>
                                    <div class="text-base font-medium" :class="section.balance_nature === 'cr' ? 'text-green-600' : 'text-primary'">
                                        <span dir="ltr" class="inline-block text-left tabular-nums">{{ balanceDisplay(section) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div v-if="convertedTotal" class="flex items-center gap-3 px-5 py-2 bg-muted/40">
                                <div class="flex-1 text-base font-medium text-foreground">
                                    {{ t('general.balance') }} ({{ convertedTotal.currency_code }})
                                </div>
                                <div class="text-base font-semibold" :class="convertedTotal.balance_nature === 'cr' ? 'text-green-600' : 'text-primary'">
                                    <span dir="ltr" class="inline-block text-left tabular-nums">{{ convertedTotalDisplay }}</span>
                                </div>
                            </div>
                            <div v-if="homeEquivalent !== null" class="flex items-center gap-3 px-5 py-2 bg-muted/40">
                                <div class="flex-1 text-xs text-muted-foreground">
                                    {{ t('report.home_equivalent_note', { code: homeCurrencyCode }) }}
                                </div>
                                <div class="text-sm font-medium tabular-nums" :class="homeEquivalent >= 0 ? 'text-primary' : 'text-green-600'">
                                    {{ formatAmount(Math.abs(homeEquivalent)) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Basic info -->
                    <div class="lg:col-span-2 bg-card text-card-foreground rounded-xl shadow-sm border border-border p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div><div class="text-xs text-muted-foreground">{{ t('general.name') }}</div><div class="font-medium">{{ accountData.name }}</div></div>
                            <div><div class="text-xs text-muted-foreground">{{ t('general.number') }}</div><div class="font-medium">{{ accountData.number }}</div></div>
                            <div><div class="text-xs text-muted-foreground">{{ t('account.account_type') }}</div><div class="font-medium">{{ accountData.account_type?.name || '' }}</div></div>
                            <div><div class="text-xs text-muted-foreground">{{ t('general.branch') }}</div><div class="font-medium">{{ accountData.branch?.name || '' }}</div></div>
                            <div><div class="text-xs text-muted-foreground">{{ t('account.parent') }}</div><div class="font-medium">{{ accountData.parent?.name || '' }}</div></div>
                            <div class="md:col-span-2"><div class="text-xs text-muted-foreground">{{ t('general.remark') }}</div><div class="font-medium">{{ accountData.remark }}</div></div>
                            <div><div class="text-xs text-muted-foreground">{{ t('general.created_by') }}</div><div class="font-medium">{{ accountData.created_by?.name || '' }}</div></div>
                            <div><div class="text-xs text-muted-foreground">{{ t('general.updated_by') }}</div><div class="font-medium">{{ accountData.updated_by?.name || '' }}</div></div>
                        </div>
                    </div>
                </div>

                <!-- Debit vs credit chart -->
                <div class="bg-card text-card-foreground rounded-xl shadow-sm border border-border p-4">
                    <TimeSeriesChart
                        :title="t('general.debit_vs_credit')"
                        :subtitle="debitCreditSubtitle || t('general.posted_totals_over_time')"
                        :series="debitCreditSeries"
                        :points="debitCreditPoints"
                        :format-value="formatAmount"
                    />
                </div>

                <!-- Transactions table -->
                <div class="bg-card text-card-foreground rounded-xl shadow-sm border border-border p-4">
                    <LedgerListTable
                        :title="t('general.transaction_summary')"
                        :rows="transactionTableRows"
                        :columns="transactionColumns"
                        :empty-message="t('general.no_data_found')"
                        :export-url="exportUrl"
                        :export-label="t('report.export_excel')"
                        :row-number-label="t('report.columns.no')"
                        default-sort-key="date"
                        default-sort-direction="desc"
                    />
                </div>
            </div>

            <!-- Opening tab -->
            <div v-else class="bg-card text-card-foreground rounded-xl shadow-sm border border-border p-4">
                <div class="text-sm font-semibold mb-3">{{ t('item.opening') }}</div>
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
                            <td colspan="5" class="py-4 text-center text-muted-foreground">{{ t('general.no_data_found') }}</td>
                        </tr>
                        <tr v-for="op in openings" :key="op.id" class="border-b border-border last:border-b-0">
                            <td class="py-2 pr-4">{{ op.currency?.name || '' }}</td>
                            <td class="py-2 pr-4">{{ formatAmount(op.amount) }}</td>
                            <td class="py-2 pr-4">{{ op.rate }}</td>
                            <td class="py-2 pr-4 capitalize">{{ op.type }}</td>
                            <td class="py-2 pr-4">{{ op.date }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
