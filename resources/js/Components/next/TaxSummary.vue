<template>
    <div class="rounded-xl border bg-gradient-to-b from-muted/50 to-background p-4 shadow-sm">
        <div class="text-lg font-semibold mb-3 text-violet-500 text-sm">{{ title }}</div>

        <div class="flex items-center justify-between hover:bg-muted hover:text-violet-500">
            <span class="text-muted-foreground hover:text-violet-500">Total Item Tax:</span>
            <span class="tabular-nums text-sm hover:text-violet-500">{{ format(totalItemTax) }}</span>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    title: { type: String, default: 'Tax Info' },
    totalItemTax: { type: [Number, String], default: 0 },
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

// Avoid destructuring props to keep reactivity intact
</script>


