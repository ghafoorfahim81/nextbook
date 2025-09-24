<template>
    <div class="rounded-xl border bg-gradient-to-b from-muted/50 to-background p-4 shadow-sm">
        <div class="text-lg font-semibold mb-3">{{ title }}</div>

        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-muted-foreground">Total Item Disc:</span>
                <span class="tabular-nums">{{ format(totalItemDiscount) }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-muted-foreground">Bill Discount:</span>
                <span class="tabular-nums">{{ format(billDiscount) }}</span>
            </div>

            <div class="flex items-center justify-between font-semibold">
                <span>Total Disc:</span>
                <span class="tabular-nums">{{ format(totalDiscount) }}</span>
            </div>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    title: { type: String, default: 'Discount Info' },
    totalItemDiscount: { type: [Number, String], default: 0 },
    billDiscount: { type: [Number, String], default: 0 },
    totalDiscount: { type: [Number, String], default: 0 },
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

const { title, totalItemDiscount, billDiscount, totalDiscount } = props
</script>


