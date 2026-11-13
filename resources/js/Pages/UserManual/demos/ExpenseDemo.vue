<script setup>
/**
 * A multi-line expense on sample data: each line is a category and an amount,
 * the whole is paid from a cash/bank account or left payable to a party, and
 * the accounting entry follows.
 */
import { computed, reactive, ref } from 'vue'
import { Plus, Trash2 } from 'lucide-vue-next'
import DemoFrame from './DemoFrame.vue'
import { BUTTON, FIELD, cents, money, toNum } from './shared'

const props = defineProps({ labels: { type: Object, required: true } })
const L = computed(() => props.labels)
const fmt = (n) => money(n, L.value.number_locale)

let seq = 3
const lines = reactive([
    { id: 1, category: 0, amount: 4500 },
    { id: 2, category: 1, amount: 1200 },
])
const mode = ref('paid') // paid | payable
const account = ref(0)
const party = ref(0)

const total = computed(() => cents(lines.reduce((s, l) => s + toNum(l.amount), 0)))
const add = () => lines.push({ id: seq++, category: 0, amount: '' })
const remove = (l) => { if (lines.length > 1) lines.splice(lines.indexOf(l), 1) }

// One debit per category, one credit for the whole.
const entry = computed(() => {
    const rows = lines.filter((l) => toNum(l.amount) > 0).map((l) => ({ account: L.value.categories[l.category], debit: cents(toNum(l.amount)), credit: 0 }))
    rows.push({ account: mode.value === 'paid' ? L.value.accounts[account.value] : L.value.payable + ' — ' + L.value.parties[party.value], debit: 0, credit: total.value })
    return rows
})
</script>

<template>
    <DemoFrame :banner="L.banner" :title="L.form_title">
        <div class="overflow-x-auto rounded-xl border border-border">
            <table class="w-full min-w-[28rem] border-collapse text-sm">
                <thead class="bg-muted/60 text-xs text-muted-foreground"><tr><th class="px-2 py-2 text-start font-semibold">{{ L.category }}</th><th class="w-36 px-2 py-2 text-start font-semibold">{{ L.amount }}</th><th class="w-8" /></tr></thead>
                <tbody>
                    <tr v-for="l in lines" :key="l.id" class="border-t border-border">
                        <td class="px-2 py-2"><select v-model.number="l.category" :class="FIELD"><option v-for="(c, i) in L.categories" :key="c" :value="i">{{ c }}</option></select></td>
                        <td class="px-2 py-2"><input v-model="l.amount" type="number" min="0" :class="FIELD" /></td>
                        <td class="px-1 py-2 text-center"><button type="button" class="rounded p-1 text-destructive hover:bg-destructive/10" :aria-label="L.remove" @click="remove(l)"><Trash2 class="size-4" /></button></td>
                    </tr>
                </tbody>
                <tfoot><tr class="border-t border-border bg-muted/30 font-semibold text-foreground"><td class="px-2 py-2">{{ L.total }}</td><td class="px-2 py-2 tabular-nums">{{ fmt(total) }}</td><td /></tr></tfoot>
            </table>
        </div>
        <button type="button" :class="BUTTON" @click="add"><Plus class="size-4" /> {{ L.add_line }}</button>

        <div class="grid gap-3 sm:grid-cols-2">
            <div class="space-y-2 rounded-xl border border-border p-3 text-sm text-foreground">
                <label class="flex items-center gap-2"><input v-model="mode" type="radio" value="paid" /> {{ L.paid_from }}</label>
                <select v-model.number="account" :disabled="mode !== 'paid'" :class="FIELD"><option v-for="(a, i) in L.accounts" :key="a" :value="i">{{ a }}</option></select>
                <label class="flex items-center gap-2"><input v-model="mode" type="radio" value="payable" /> {{ L.payable_to }}</label>
                <select v-model.number="party" :disabled="mode !== 'payable'" :class="FIELD"><option v-for="(p, i) in L.parties" :key="p" :value="i">{{ p }}</option></select>
            </div>
            <div class="rounded-xl border border-border p-3">
                <p class="mb-2 text-xs font-semibold text-muted-foreground">{{ L.entry }}</p>
                <table class="w-full text-sm">
                    <thead class="text-xs text-muted-foreground"><tr><th class="text-start font-semibold">{{ L.account }}</th><th class="text-end font-semibold">{{ L.debit }}</th><th class="text-end font-semibold">{{ L.credit }}</th></tr></thead>
                    <tbody class="tabular-nums"><tr v-for="(e, i) in entry" :key="i" class="border-t border-border"><td class="py-1 text-foreground">{{ e.account }}</td><td class="py-1 text-end">{{ e.debit ? fmt(e.debit) : '' }}</td><td class="py-1 text-end">{{ e.credit ? fmt(e.credit) : '' }}</td></tr></tbody>
                </table>
            </div>
        </div>
    </DemoFrame>
</template>
