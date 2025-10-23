<template>
    <div class="rounded-xl border border-violet-500 bg-gradient-to-b from-muted/50 to-background p-4 shadow-sm">
        <div class="text-sm font-semibold mb-3 text-violet-500">{{ t('general.transaction_summary') }}</div>

        <div class="space-y-2">
            <div v-for="row in rows" :key="row.key" class="flex items-center justify-between hover:bg-muted hover:text-violet-500">
                <span class="text-muted-foreground hover:text-violet-500">{{ row.label }}:</span>
                <span class="tabular-nums text-sm hover:text-violet-500">{{ row.value }} {{ row.value>0 ? summary.currencySymbol : '' }}</span>
            </div>
            <div class="flex items-center justify-between hover:bg-muted hover:text-violet-500">
                <span class="text-muted-foreground hover:text-violet-500"> {{ t('general.old_balance') }}:</span>
                <span class="tabular-nums text-sm hover:text-violet-500">{{ format(summary.oldBalance) }} {{ summary.oldBalance ? summary.currencySymbol : '' }}  {{ summary.oldBalance ? summary.balanceNature : '' }}</span>
            </div>
            <div class="flex items-center justify-between font-semibold">
                <span>{{ t('general.grand_total') }}:</span>
                <span class="tabular-nums text-sm">{{ format(summary.grandTotal) }} {{ summary.grandTotal ? summary.currencySymbol : '' }}</span>
            </div>

            <div class="border-t my-2"></div>

            <div class="flex items-center justify-between hover:bg-muted hover:text-violet-500">
                <span class="text-muted-foreground hover:text-violet-500"> {{ t('general.balance') }}:</span>
                <span class="tabular-nums text-sm hover:text-violet-500">{{ format(summary.balance) }} {{ summary.balance ? summary.currencySymbol : '' }}  {{ summary.balance ? summary.balanceNature : '' }}</span>
            </div>
        </div>
    </div>

</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n';
const { t } = useI18n();

const props = defineProps({
    title: { type: String, default: 'Grand Total' },
    summary: {
        type: Object,
        default: () => ({
            valueOfGoods: 0,
            billDiscountPercent: 0,
            billDiscount: 0,
            itemDiscount: 0,
            cashReceived: 0,
            balance: 0,
            grandTotal: 0,
            oldBalance: 0,
            balanceNature: '',
        }),
    },
    fractionDigits: { type: Number, default: 1 },
    locale: { type: String, default: undefined },
})

const format = (num) => {
    const n = Number(num ?? 0)
    try {
        return new Intl.NumberFormat(props.locale, {
            minimumFractionDigits: props.fractionDigits,
            maximumFractionDigits: props.fractionDigits,
        }).format(n)
    } catch (e) {
        return n.toFixed(props.fractionDigits)
    }
}

const rows = computed(() => [
    { key: 'valueOfGoods', label: t('general.value_of_goods'), value: format(props.summary.valueOfGoods) },
    { key: 'billDiscount', label: t('general.bill_disc')+`.(${format(props.summary.billDiscountPercent)} %)`, value: format(props.summary.billDiscount) },
    { key: 'itemDiscount', label: t('general.item_disc'), value: format(props.summary.itemDiscount) },
    { key: 'cashReceived', label: t('general.cash_paid'), value: format(props.summary.cashReceived) },
    // { key: 'balance', label: t('general.balance'), value: format(props.summary.balance) },
])
</script>


