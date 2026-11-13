<script setup>
/**
 * A receipt (or a payment) on sample data: the money, the account it goes
 * through, the open bills it is matched to, and the accounting entry that
 * results. Nothing is saved.
 */
import { computed, reactive, ref, watch } from 'vue'
import DemoFrame from './DemoFrame.vue'
import { BUTTON, FIELD, cents, money, toNum } from './shared'

const props = defineProps({ labels: { type: Object, required: true } })
const L = computed(() => props.labels)
const fmt = (n) => money(n, L.value.number_locale)

const mode = ref('receipt')
const party = ref(0)
const account = ref(0)
const amount = ref(2000)
const manual = ref(false)

// Open bills, oldest first.
const docs = reactive([
    { number: 1, open: 1200 },
    { number: 2, open: 800 },
    { number: 3, open: 2500 },
])
const alloc = reactive([0, 0, 0])

const autoAllocate = () => {
    let left = Math.max(0, toNum(amount.value))
    docs.forEach((d, i) => {
        const take = Math.min(left, d.open)
        alloc[i] = cents(take)
        left = cents(left - take)
    })
    manual.value = false
}
watch([amount, mode], () => { if (!manual.value) autoAllocate() }, { immediate: true })

const setAlloc = (i, value) => {
    manual.value = true
    alloc[i] = Math.min(Math.max(0, toNum(value)), docs[i].open)
}
const allocated = computed(() => cents(alloc.reduce((s, a) => s + toNum(a), 0)))
const onAccount = computed(() => cents(Math.max(0, toNum(amount.value)) - allocated.value))
const tooMuch = computed(() => allocated.value > toNum(amount.value) + 0.0001)
const before = computed(() => cents(docs.reduce((s, d) => s + d.open, 0)))
const after = computed(() => cents(before.value - toNum(amount.value)))
const status = (i) => {
    const left = cents(docs[i].open - toNum(alloc[i]))
    return left <= 0 ? 'paid' : toNum(alloc[i]) > 0 ? 'partial' : 'open'
}

const isReceipt = computed(() => mode.value === 'receipt')
const partyList = computed(() => (isReceipt.value ? L.value.customers : L.value.suppliers))
const entry = computed(() => (isReceipt.value
    ? [
        { account: L.value.accounts[account.value], debit: toNum(amount.value), credit: 0 },
        { account: L.value.receivable, debit: 0, credit: toNum(amount.value) },
    ]
    : [
        { account: L.value.payable, debit: toNum(amount.value), credit: 0 },
        { account: L.value.accounts[account.value], debit: 0, credit: toNum(amount.value) },
    ]))
</script>

<template>
    <DemoFrame :banner="L.banner" :title="isReceipt ? L.receipt : L.payment">
        <div class="inline-flex overflow-hidden rounded-lg border border-border text-sm">
            <button type="button" class="px-4 py-1.5" :class="isReceipt ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'" @click="mode = 'receipt'">{{ L.receipt }}</button>
            <button type="button" class="px-4 py-1.5" :class="!isReceipt ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'" @click="mode = 'payment'">{{ L.payment }}</button>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
            <label class="space-y-1 text-xs text-muted-foreground">{{ isReceipt ? L.customer : L.supplier }}
                <select v-model.number="party" :class="FIELD"><option v-for="(n, i) in partyList" :key="n" :value="i">{{ n }}</option></select>
            </label>
            <label class="space-y-1 text-xs text-muted-foreground">{{ L.account }}
                <select v-model.number="account" :class="FIELD"><option v-for="(n, i) in L.accounts" :key="n" :value="i">{{ n }}</option></select>
            </label>
            <label class="space-y-1 text-xs text-muted-foreground">{{ L.amount }}
                <input v-model="amount" type="number" min="0" :class="FIELD" />
            </label>
        </div>

        <div class="overflow-x-auto rounded-xl border border-border">
            <table class="w-full min-w-[34rem] border-collapse text-sm">
                <thead class="bg-muted/60 text-xs text-muted-foreground">
                    <tr>
                        <th class="px-2 py-2 text-start font-semibold">{{ isReceipt ? L.invoice : L.bill }}</th>
                        <th class="px-2 py-2 text-end font-semibold">{{ L.open }}</th>
                        <th class="w-32 px-2 py-2 text-start font-semibold">{{ L.allocate }}</th>
                        <th class="px-2 py-2 text-end font-semibold">{{ L.left }}</th>
                        <th class="px-2 py-2 text-start font-semibold">{{ L.state }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(d, i) in docs" :key="d.number" class="border-t border-border">
                        <td class="px-2 py-2 text-foreground">#{{ d.number }}</td>
                        <td class="px-2 py-2 text-end tabular-nums">{{ fmt(d.open) }}</td>
                        <td class="px-2 py-2"><input :value="alloc[i]" type="number" min="0" :class="FIELD" @input="setAlloc(i, $event.target.value)" /></td>
                        <td class="px-2 py-2 text-end tabular-nums">{{ fmt(d.open - toNum(alloc[i])) }}</td>
                        <td class="px-2 py-2 text-foreground">{{ L[status(i)] }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap items-center gap-3 text-sm">
            <button type="button" :class="BUTTON" @click="autoAllocate">{{ L.auto }}</button>
            <span class="text-muted-foreground">{{ L.on_account }}: <b class="tabular-nums text-foreground">{{ fmt(onAccount) }}</b></span>
            <span v-if="tooMuch" class="text-destructive">{{ L.too_much }}</span>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-border p-3">
                <p class="mb-2 text-xs font-semibold text-muted-foreground">{{ L.entry }}</p>
                <table class="w-full text-sm">
                    <thead class="text-xs text-muted-foreground"><tr><th class="text-start font-semibold">{{ L.account }}</th><th class="text-end font-semibold">{{ L.debit }}</th><th class="text-end font-semibold">{{ L.credit }}</th></tr></thead>
                    <tbody class="tabular-nums">
                        <tr v-for="e in entry" :key="e.account" class="border-t border-border"><td class="py-1 text-foreground">{{ e.account }}</td><td class="py-1 text-end">{{ e.debit ? fmt(e.debit) : '' }}</td><td class="py-1 text-end">{{ e.credit ? fmt(e.credit) : '' }}</td></tr>
                    </tbody>
                </table>
            </div>
            <dl class="space-y-1 rounded-xl border border-border bg-muted/30 p-3 text-sm tabular-nums">
                <div class="flex justify-between gap-3"><dt class="text-muted-foreground">{{ L.balance_before }}</dt><dd>{{ fmt(before) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-muted-foreground">{{ isReceipt ? L.receipt : L.payment }}</dt><dd>− {{ fmt(amount) }}</dd></div>
                <div class="flex justify-between gap-3 border-t border-border pt-1 font-bold text-foreground"><dt>{{ L.balance_after }}</dt><dd>{{ fmt(after) }}</dd></div>
            </dl>
        </div>
    </DemoFrame>
</template>
