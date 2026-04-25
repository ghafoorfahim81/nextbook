<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import LedgerListTable from '@/Components/reports/LedgerListTable.vue';
import { ArrowLeft } from 'lucide-vue-next';

const { t } = useI18n();

const props = defineProps({
    account: { type: Object, required: true },
    transactions: { type: [Array, Object], required: false, default: () => [] },
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
                transaction_number: txn?.voucher_number || txn?.id,
                description: line?.remark || txn?.remark || '-',
                currency: txn?.currency?.code || '',
                remark: line?.remark ?? txn?.remark ?? '',
            };
        });
    });
});

const transactionTableRows = computed(() =>
    transactionRows.value.map((row) => {
        const rate = Number(row.rate || 1);
        return {
            id: row.id,
            date: row.date,
            transaction_number: row.transaction_number || row.id,
            description: row.description || row.remark || '-',
            debit: Number(row.type === 'debit' ? row.amount * rate : 0),
            credit: Number(row.type === 'credit' ? row.amount * rate : 0),
            currency: row.currency || '',
            rate,
        };
    })
);

const transactionColumns = computed(() => [
    { key: 'date', label: t('general.date') },
    { key: 'description', label: t('general.description') },
    { key: 'currency', label: t('admin.currency.currency') },
    { key: 'rate', label: t('general.rate'), type: 'money', align: 'right' },
    { key: 'credit', label: t('general.credit'), type: 'money', align: 'right' },
    { key: 'debit', label: t('general.debit'), type: 'money', align: 'right' },
]);

const statement = computed(() => {
    const totals = transactionRows.value.reduce(
        (carry, txn) => {
            const amount = Number(txn.amount || 0) * Number(txn.rate || 1);
            if (txn.type === 'debit') carry.debit += amount;
            else if (txn.type === 'credit') carry.credit += amount;
            return carry;
        },
        { debit: 0, credit: 0 }
    );
    const netBalance = totals.debit - totals.credit;
    return {
        total_debit: totals.debit,
        total_credit: totals.credit,
        balance: Math.abs(netBalance),
        balance_nature: netBalance >= 0 ? 'dr' : 'cr',
    };
});

const balanceDisplay = computed(() => {
    const amount = Number(statement.value.balance || 0);
    const nature = statement.value.balance_nature;
    if (!amount || !nature) return formatAmount(0);
    if (props.balanceNatureFormat === 'without_nature') return formatAmount(amount);
    return `${formatAmount(amount)} ${nature.toUpperCase()}`;
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
            <!-- Back button -->
            <div class="flex items-center gap-3">
                <Button variant="outline" size="sm" @click="router.visit(route('chart-of-accounts.index'))">
                    <ArrowLeft class="h-4 w-4 ltr:mr-1 rtl:ml-1" />
                    {{ t('general.back') }}
                </Button>
                <h1 class="text-xl font-semibold text-foreground">
                    {{ t('account.account') }}
                    <span v-if="accountData.local_name"> - {{ accountData.local_name }}</span>
                </h1>
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
            <div v-if="activeMainTab === 'general'" class="space-y-4">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
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
                        <div class="w-full bg-background border border-border rounded-xl overflow-hidden mt-4">
                            <div class="flex flex-col divide-y divide-border">
                                <div class="flex items-center px-5 py-2">
                                    <div class="flex-1 text-base text-foreground">{{ t('general.credit') }}</div>
                                    <div class="text-base font-medium text-green-600">{{ formatAmount(statement.total_credit) }}</div>
                                </div>
                                <div class="flex items-center px-5 py-2">
                                    <div class="flex-1 text-base text-foreground">{{ t('general.debit') }}</div>
                                    <div class="text-base font-medium text-green-600">{{ formatAmount(statement.total_debit) }}</div>
                                </div>
                                <div class="flex items-center px-5 py-2">
                                    <div class="flex-1 text-base text-foreground">{{ t('general.balance') }}</div>
                                    <div class="text-base font-medium" :class="statement.balance_nature === 'cr' ? 'text-green-600' : 'text-primary'">
                                        <span dir="ltr" class="inline-block text-left tabular-nums">{{ balanceDisplay }}</span>
                                    </div>
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
