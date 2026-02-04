<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';

const { t } = useI18n();

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    accountId: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['update:open']);

const account = ref(null);
const transactions = ref([]);
const openings = ref([]);
const loading = ref(false);

const activeMainTab = ref('general');

const accountData = computed(() => account.value ?? {});

const transactionRows = computed(() => {
    const txns = transactions.value || [];
    const accountId = accountData.value?.id || props.accountId;

    return txns.map((txn) => {
        const lines = Array.isArray(txn?.lines) ? txn.lines : [];
        const line = accountId
            ? lines.find((l) => l.account_id === accountId) || lines[0]
            : lines[0];

        const debit = Number(line?.debit || 0);
        const credit = Number(line?.credit || 0);
        const type = debit > 0 ? 'debit' : credit > 0 ? 'credit' : '';
        const amount = debit > 0 ? debit : credit;

        return {
            ...txn,
            _account_line: line || null,
            type,
            amount,
            remark: txn?.remark ?? line?.remark ?? '',
        };
    });
});

const statement = computed(() => {
    const rows = transactionRows.value || [];
    const totals = rows.reduce(
        (carry, txn) => {
            const amount = Number(txn.amount || 0) * Number(txn.rate || 1);
            if (txn.type === 'debit') {
                carry.debit += amount;
            } else if (txn.type === 'credit') {
                carry.credit += amount;
            }
            return carry;
        },
        { debit: 0, credit: 0 }
    );

    const netBalance = totals.debit - totals.credit;
    const balanceAmount = Math.abs(netBalance);
    const balanceNature = netBalance >= 0 ? 'dr' : 'cr';

    return {
        total_debit: totals.debit,
        total_credit: totals.credit,
        balance: balanceAmount,
        balance_nature: balanceNature,
    };
});

const formatAmount = (value) => {
    if (value === null || value === undefined) return '-';
    return Number(value).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

const loadAccount = async (id) => {
    if (!id) return;
    loading.value = true;
    try {
        const response = await axios.get(`/chart-of-accounts/${id}`);
        const data = response.data;
        account.value = data.account?.data ?? data.account ?? null;
        transactions.value = data.transactions?.data ?? data.transactions ?? [];
        const opening = data.opening?.data ?? data.opening ?? null;
        openings.value = opening ? [opening] : [];
    } catch (error) {
        console.error('Error loading account:', error);
    } finally {
        loading.value = false;
    }
};

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen && props.accountId) {
            await loadAccount(props.accountId);
        } else if (!isOpen) {
            account.value = null;
            transactions.value = [];
            openings.value = [];
            activeMainTab.value = 'general';
        }
    }
);

const closeDialog = () => {
    emit('update:open', false);
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-5xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>
                    {{ t('account.account') }}
                    <span v-if="accountData.name"> - {{ accountData.name }}</span>
                </DialogTitle>
            </DialogHeader>

            <div v-if="loading" class="flex justify-center items-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-foreground"></div>
            </div>

            <div v-else-if="accountData && accountData.id" class="space-y-4">
                <!-- Top tabs: General / Opening -->
                <div class="border-b border-border flex gap-4">
                    <button
                        type="button"
                        class="px-4 py-2 -mb-px border-b-2"
                        :class="activeMainTab === 'general'
                            ? 'border-primary text-primary font-semibold'
                            : 'border-transparent text-muted-foreground hover:text-foreground'"
                        @click="activeMainTab = 'general'"
                    >
                        {{ t('general.general') }}
                    </button>
                    <button
                        type="button"
                        class="px-4 py-2 -mb-px border-b-2"
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
                        <!-- Left: avatar + summary -->
                        <div
                            class="bg-card text-card-foreground rounded-xl shadow-sm border border-border p-4 flex flex-col items-center gap-4"
                        >
                            <div
                                class="w-16 h-16 rounded-full bg-gradient-to-tr from-indigo-500 to-blue-500 flex items-center justify-center text-white text-xl font-bold"
                            >
                                {{ (accountData.name || '').charAt(0).toUpperCase() }}
                            </div>
                            <div class="text-center">
                                <div class="text-base font-semibold text-primary">
                                    {{ accountData.name }}
                                </div>
                                <div class="text-xs text-muted-foreground mt-1">
                                    {{ accountData.number }}
                                </div>
                                <div class="mt-2 text-xs text-muted-foreground/80">
                                    {{ t('account.account') }}
                                </div>
                            </div>

                            <!-- Statement summary -->
                            <div class="w-full bg-background border border-border rounded-xl overflow-hidden mt-4">
                                <div class="flex flex-col divide-y divide-border">
                                    <div class="flex items-center px-5 py-2">
                                        <div class="flex-1 text-base text-foreground">
                                            {{ t('general.credit') }}
                                        </div>
                                        <div class="text-base font-medium text-green-600">
                                            {{ formatAmount(statement.total_credit) }}
                                        </div>
                                    </div>
                                    <div class="flex items-center px-5 py-2 mt-1">
                                        <div class="flex-1 text-base text-foreground">
                                            {{ t('general.debit') }}
                                        </div>
                                        <div class="text-base font-medium text-green-600">
                                            {{ formatAmount(statement.total_debit) }}
                                        </div>
                                    </div>
                                    <div class="flex items-center px-5 py-2">
                                        <div class="flex-1 text-base text-foreground">
                                            {{ t('general.balance') }}
                                        </div>
                                        <div
                                            class="text-base font-medium"
                                            :class="statement.balance_nature === 'cr'
                                                ? 'text-green-600'
                                                : 'text-green-600'"
                                        >
                                            {{ formatAmount(statement.balance) }}
                                            {{ statement.balance > 0 ? statement.balance_nature : '' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right: basic info -->
                        <div
                            class="lg:col-span-2 bg-card text-card-foreground rounded-xl shadow-sm border border-border p-4"
                        >
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ t('general.name') }}
                                    </div>
                                    <div class="font-medium">
                                        {{ accountData.name }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ t('general.number') }}
                                    </div>
                                    <div class="font-medium">
                                        {{ accountData.number }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ t('account.account_type') }}
                                    </div>
                                    <div class="font-medium">
                                        {{ accountData.account_type?.name || '' }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ t('general.branch') }}
                                    </div>
                                    <div class="font-medium">
                                        {{ accountData.branch?.name || '' }}
                                    </div>
                                </div>
                                <div class="md:col-span-2">
                                    <div class="text-xs text-muted-foreground">
                                        {{ t('general.remark') }}
                                    </div>
                                    <div class="font-medium">
                                        {{ accountData.remark }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Transactions table -->
                    <div class="bg-card text-card-foreground rounded-xl shadow-sm border border-border p-4">
                        <div class="flex items-center justify-between mb-4">
                            <div class="text-sm font-semibold">
                                {{ t('general.transaction_summary') }}
                            </div>
                        </div>

                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-border text-left rtl:text-right text-muted-foreground">
                                    <th class="py-2 pr-4">#</th>
                                    <th class="py-2 pr-4">{{ t('general.date') }}</th>
                                    <th class="py-2 pr-4">{{ t('general.type') }}</th>
                                    <th class="py-2 pr-4">{{ t('general.amount') }}</th>
                                    <th class="py-2 pr-4">{{ t('admin.currency.currency') }}</th>
                                    <th class="py-2 pr-4">{{ t('general.rate') }}</th>
                                    <th class="py-2 pr-4">{{ t('general.remark') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!transactionRows.length">
                                    <td
                                        colspan="6"
                                        class="py-4 text-center text-muted-foreground"
                                    >
                                        {{ t('general.no_data_found') }}
                                    </td>
                                </tr>
                                <tr
                                    v-for="(row, index) in transactionRows"
                                    :key="row.id"
                                    class="border-b border-border last:border-b-0"
                                >
                                    <td class="py-2 pr-4">
                                        {{ index + 1 }}
                                    </td>
                                    <td class="py-2 pr-4">
                                        {{ row.date }}
                                    </td>
                                    <td class="py-2 pr-4 capitalize">
                                        {{ row.type }}
                                    </td>
                                    <td class="py-2 pr-4">
                                        {{ formatAmount(row.amount) }}
                                    </td>
                                    <td class="py-2 pr-4">
                                        {{ row.currency?.code || row.currency?.name || '' }}
                                    </td>
                                    <td class="py-2 pr-4">
                                        {{ row.rate }}
                                    </td>
                                    <td class="py-2 pr-4">
                                        {{ row.remark }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- OPENING TAB -->
                <div
                    v-else
                    class="bg-card text-card-foreground rounded-xl shadow-sm border border-border p-4"
                >
                    <div class="text-sm font-semibold mb-3">
                        {{ t('item.opening') }}
                    </div>
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-border text-left rtl:text-right text-muted-foreground">
                                <th class="py-2 pr-4">
                                    {{ t('admin.currency.currency') }}
                                </th>
                                <th class="py-2 pr-4">
                                    {{ t('general.amount') }}
                                </th>
                                <th class="py-2 pr-4">
                                    {{ t('general.rate') }}
                                </th>
                                <th class="py-2 pr-4">
                                    {{ t('general.type') }}
                                </th>
                                <th class="py-2 pr-4">
                                    {{ t('general.date') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!openings.length">
                                <td
                                    colspan="5"
                                    class="py-4 text-center text-muted-foreground"
                                >
                                    {{ t('general.no_data_found') }}
                                </td>
                            </tr>
                            <tr
                                v-for="opening in openings"
                                :key="opening.id"
                                class="border-b border-border last:border-b-0"
                            >
                                <td class="py-2 pr-4">
                                    {{ opening.currency?.name || '' }}
                                </td>
                                <td class="py-2 pr-4">
                                    {{ formatAmount(opening.amount) }}
                                </td>
                                <td class="py-2 pr-4">
                                    {{ opening.rate }}
                                </td>
                                <td class="py-2 pr-4 capitalize">
                                    {{ opening.type }}
                                </td>
                                <td class="py-2 pr-4">
                                    {{ opening.date }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex justify-end mt-4">
                <Button variant="outline" @click="closeDialog">
                    {{ t('general.close') }}
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>


