<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    /** Rows from LedgerStatementService::balancesByCurrency(). */
    balances: { type: Array, default: () => [] },
    /** Home-currency code, shown on the equivalent total. */
    homeCurrencyCode: { type: String, default: '' },
});

const { t } = useI18n();

const rows = computed(() => props.balances ?? []);

// Only worth showing once more than one currency is in play; with a single
// currency it just repeats the row above it.
const homeEquivalent = computed(() => (rows.value.length > 1
    ? rows.value.reduce((total, row) => total + Number(row.home_equivalent ?? 0), 0)
    : null));

const formatAmount = (value) => Number(value ?? 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const natureLabel = (nature) => (nature === 'cr' ? t('general.credit') : t('general.debit'));

const natureClass = (nature) => (nature === 'cr'
    ? 'text-emerald-600 dark:text-emerald-400'
    : 'text-blue-600 dark:text-blue-400');
</script>

<template>
    <div class="bg-card text-card-foreground rounded-xl shadow-sm border border-border divide-y divide-border overflow-hidden">
        <div class="px-4 py-2.5">
            <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                {{ t('ledger.balance_by_currency') }}
            </span>
        </div>

        <div v-if="!rows.length" class="px-4 py-3 text-sm text-muted-foreground">
            {{ t('ledger.no_currency_balance') }}
        </div>

        <div
            v-for="row in rows"
            :key="row.currency_id"
            class="flex items-center justify-between gap-3 px-4 py-2.5"
        >
            <div class="min-w-0">
                <div class="flex items-center gap-1.5">
                    <span class="text-sm font-semibold text-foreground">{{ row.currency_code }}</span>
                    <span
                        v-if="row.is_base_currency"
                        class="rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground"
                    >
                        {{ t('admin.currency.home_currency') }}
                    </span>
                </div>
                <div class="truncate text-xs text-muted-foreground">{{ row.currency_name }}</div>
            </div>
            <div class="text-end">
                <div class="text-sm font-semibold tabular-nums" :class="natureClass(row.balance_nature)">
                    {{ formatAmount(row.balance) }}
                </div>
                <div class="text-[11px] uppercase" :class="natureClass(row.balance_nature)">
                    {{ natureLabel(row.balance_nature) }}
                </div>
            </div>
        </div>

        <div v-if="homeEquivalent !== null" class="flex items-center justify-between gap-3 bg-muted/40 px-4 py-2.5">
            <span class="text-xs text-muted-foreground">
                {{ t('report.home_equivalent_note', { code: homeCurrencyCode }) }}
            </span>
            <span
                class="text-sm font-semibold tabular-nums"
                :class="natureClass(homeEquivalent >= 0 ? 'dr' : 'cr')"
            >
                {{ formatAmount(Math.abs(homeEquivalent)) }}
            </span>
        </div>
    </div>
</template>
