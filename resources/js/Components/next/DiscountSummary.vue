<template>
    <div class="rounded-xl border bg-gradient-to-b from-muted/50 to-background p-4 shadow-sm">
        <div class="text-lg font-semibold mb-3 text-violet-500 text-sm">{{ t('general.discount_summary') }}</div>

        <div class="space-y-2">
            <div class="flex items-center justify-between hover:bg-muted hover:text-violet-500">
                <span class="text-muted-foreground hover:text-violet-500">{{ t('general.total_item_discount') }}:</span>
                <span class="tabular-nums text-sm">{{ format(totalItemDiscount) }}</span>
            </div>
            <div class="flex items-center justify-between hover:bg-muted hover:text-violet-500">
                <span class="text-muted-foreground hover:text-violet-500">{{ t('general.bill_disc') }}:</span>
                <span class="tabular-nums text-sm hover:text-violet-500">{{ format(billDiscount) }}</span>
            </div>

            <div class="flex items-center justify-between font-semibold hover:bg-muted hover:text-violet-500">
                <span class="text-sm">{{ t('general.total_discount') }}:</span>
                <span class="tabular-nums text-sm">{{ format(totalDiscount) }}</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
const { t } = useI18n();

const props = defineProps({
    title: { type: String, default: 'Discount Info' },
    totalItemDiscount: { type: [Number, String], default: 0 },
    billDiscount: { type: [Number, String], default: 0 },
    totalDiscount: { type: [Number, String], default: 0 },
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

// Avoid destructuring props to keep reactivity intact
</script>


