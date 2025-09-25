<template>
    <div class="rounded-xl border bg-gradient-to-b from-muted/50 to-background p-4 shadow-sm">
        <div class="text-lg font-semibold mb-3">{{ title }}</div>

        <div class="space-y-2">
            <div v-for="row in rows" :key="row.key" class="flex items-center justify-between">
                <span class="text-muted-foreground">{{ row.label }}:</span>
                <span class="tabular-nums">{{ row.value }}</span>
            </div>

            <div class="flex items-center justify-between font-semibold">
                <span>Grand Total:</span>
                <span class="tabular-nums">{{ format(summary.grandTotal) }}</span>
            </div>

            <div class="border-t my-2"></div>

            <div class="flex items-center justify-between">
                <span class="text-muted-foreground">Old Balance:</span>
                <span class="tabular-nums">{{ format(summary.oldBalance) }}. {{ summary.balanceNature }}</span>
            </div>
        </div>
    </div>

</template>

<script setup>
import { computed } from 'vue'

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
    fractionDigits: { type: Number, default: 2 },
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
    { key: 'valueOfGoods', label: 'Value Of Goods', value: format(props.summary.valueOfGoods) },
    { key: 'billDiscount', label: `Bill disc. (${format(props.summary.billDiscountPercent)} %)`, value: format(props.summary.billDiscount) },
    { key: 'itemDiscount', label: 'Item Disc', value: format(props.summary.itemDiscount) },
    { key: 'cashReceived', label: 'Cash Received:', value: format(props.summary.cashReceived) },
    { key: 'balance', label: 'Balance:', value: format(props.summary.balance) },
])
</script>


