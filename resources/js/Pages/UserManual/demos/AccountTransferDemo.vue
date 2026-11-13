<script setup>
/**
 * Moving money between two accounts on sample data: balances before and
 * after, the entry, and the conversion when the two accounts hold different
 * currencies. A transfer changes no income or expense.
 */
import { computed, ref } from 'vue'
import DemoFrame from './DemoFrame.vue'
import { FIELD, cents, money, toNum } from './shared'

const props = defineProps({ labels: { type: Object, required: true } })
const L = computed(() => props.labels)
const fmt = (n) => money(n, L.value.number_locale)

const from = ref(0)
const to = ref(1)
const amount = ref(10000)
const rate = ref(65)

const accounts = computed(() => L.value.accounts)
const a = computed(() => accounts.value[from.value])
const b = computed(() => accounts.value[to.value])
const sameAccount = computed(() => from.value === to.value)
const crossCurrency = computed(() => a.value.currency !== b.value.currency)
const received = computed(() => {
    const v = toNum(amount.value)
    if (!crossCurrency.value) return cents(v)
    // 'rate' is AFN per USD: AFN → USD divides, USD → AFN multiplies.
    return cents(a.value.currency === 'AFN' ? v / toNum(rate.value || 1) : v * toNum(rate.value))
})
const tooMuch = computed(() => toNum(amount.value) > a.value.balance)
const error = computed(() => (sameAccount.value ? L.value.same : tooMuch.value ? L.value.too_much : ''))
</script>

<template>
    <DemoFrame :banner="L.banner" :title="L.form_title">
        <div class="grid gap-3 sm:grid-cols-4">
            <label class="space-y-1 text-xs text-muted-foreground">{{ L.from }}<select v-model.number="from" :class="FIELD"><option v-for="(x, i) in accounts" :key="x.name" :value="i">{{ x.name }}</option></select></label>
            <label class="space-y-1 text-xs text-muted-foreground">{{ L.to }}<select v-model.number="to" :class="FIELD"><option v-for="(x, i) in accounts" :key="x.name" :value="i">{{ x.name }}</option></select></label>
            <label class="space-y-1 text-xs text-muted-foreground">{{ L.amount }} ({{ a.currency }})<input v-model="amount" type="number" min="0" :class="FIELD" /></label>
            <label v-if="crossCurrency" class="space-y-1 text-xs text-muted-foreground">{{ L.rate }}<input v-model="rate" type="number" min="0" :class="FIELD" /></label>
        </div>
        <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>

        <div v-if="!error" class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-border p-3">
                <p class="mb-2 text-xs font-semibold text-muted-foreground">{{ L.entry }}</p>
                <table class="w-full text-sm">
                    <thead class="text-xs text-muted-foreground"><tr><th class="text-start font-semibold">{{ L.account }}</th><th class="text-end font-semibold">{{ L.debit }}</th><th class="text-end font-semibold">{{ L.credit }}</th></tr></thead>
                    <tbody class="tabular-nums">
                        <tr class="border-t border-border"><td class="py-1 text-foreground">{{ b.name }}</td><td class="py-1 text-end">{{ fmt(received) }} {{ b.currency }}</td><td /></tr>
                        <tr class="border-t border-border"><td class="py-1 text-foreground">{{ a.name }}</td><td /><td class="py-1 text-end">{{ fmt(amount) }} {{ a.currency }}</td></tr>
                    </tbody>
                </table>
            </div>
            <dl class="space-y-1 rounded-xl border border-border bg-muted/30 p-3 text-sm tabular-nums">
                <div class="flex justify-between gap-3"><dt class="text-muted-foreground">{{ a.name }}</dt><dd>{{ fmt(a.balance) }} → <b class="text-foreground">{{ fmt(a.balance - toNum(amount)) }}</b> {{ a.currency }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-muted-foreground">{{ b.name }}</dt><dd>{{ fmt(b.balance) }} → <b class="text-foreground">{{ fmt(b.balance + received) }}</b> {{ b.currency }}</dd></div>
                <p class="border-t border-border pt-1 text-xs text-muted-foreground">{{ L.no_profit_effect }}</p>
            </dl>
        </div>
    </DemoFrame>
</template>
