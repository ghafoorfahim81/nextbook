<script setup>
/**
 * A journal entry on sample data: debit and credit lines, the balance
 * indicator, and a Post button that only works when the two sides are equal.
 */
import { computed, reactive, ref } from 'vue'
import { Plus, Trash2 } from 'lucide-vue-next'
import DemoFrame from './DemoFrame.vue'
import { BUTTON, FIELD, PRIMARY, cents, money, toNum } from './shared'

const props = defineProps({ labels: { type: Object, required: true } })
const L = computed(() => props.labels)
const fmt = (n) => money(n, L.value.number_locale)

let seq = 3
const lines = reactive([
    { id: 1, account: 0, debit: 5000, credit: '' },
    { id: 2, account: 1, debit: '', credit: 5000 },
])
const posted = ref(false)

const debit = computed(() => cents(lines.reduce((s, l) => s + toNum(l.debit), 0)))
const credit = computed(() => cents(lines.reduce((s, l) => s + toNum(l.credit), 0)))
const diff = computed(() => cents(debit.value - credit.value))
const balanced = computed(() => debit.value > 0 && diff.value === 0)

// A line is either a debit or a credit, never both.
const setDebit = (l, v) => { l.debit = v; if (toNum(v)) l.credit = '' }
const setCredit = (l, v) => { l.credit = v; if (toNum(v)) l.debit = '' }
const add = () => lines.push({ id: seq++, account: 0, debit: '', credit: '' })
const remove = (l) => { if (lines.length > 2) lines.splice(lines.indexOf(l), 1) }
const post = () => { posted.value = true; setTimeout(() => { posted.value = false }, 3500) }
</script>

<template>
    <DemoFrame :banner="L.banner" :title="L.form_title">
        <div class="overflow-x-auto rounded-xl border border-border">
            <table class="w-full min-w-[34rem] border-collapse text-sm">
                <thead class="bg-muted/60 text-xs text-muted-foreground">
                    <tr><th class="px-2 py-2 text-start font-semibold">{{ L.account }}</th><th class="w-32 px-2 py-2 text-start font-semibold">{{ L.debit }}</th><th class="w-32 px-2 py-2 text-start font-semibold">{{ L.credit }}</th><th class="w-8" /></tr>
                </thead>
                <tbody>
                    <tr v-for="l in lines" :key="l.id" class="border-t border-border">
                        <td class="px-2 py-2"><select v-model.number="l.account" :class="FIELD"><option v-for="(a, i) in L.accounts" :key="a" :value="i">{{ a }}</option></select></td>
                        <td class="px-2 py-2"><input :value="l.debit" type="number" min="0" :class="FIELD" @input="setDebit(l, $event.target.value)" /></td>
                        <td class="px-2 py-2"><input :value="l.credit" type="number" min="0" :class="FIELD" @input="setCredit(l, $event.target.value)" /></td>
                        <td class="px-1 py-2 text-center"><button type="button" class="rounded p-1 text-destructive hover:bg-destructive/10" :aria-label="L.remove" @click="remove(l)"><Trash2 class="size-4" /></button></td>
                    </tr>
                </tbody>
                <tfoot class="tabular-nums">
                    <tr class="border-t border-border bg-muted/30 font-semibold text-foreground"><td class="px-2 py-2">{{ L.total }}</td><td class="px-2 py-2">{{ fmt(debit) }}</td><td class="px-2 py-2">{{ fmt(credit) }}</td><td /></tr>
                </tfoot>
            </table>
        </div>
        <button type="button" :class="BUTTON" @click="add"><Plus class="size-4" /> {{ L.add_line }}</button>

        <p class="rounded-lg border px-3 py-2 text-sm" :class="balanced ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300'">
            {{ balanced ? L.balanced : L.out_of_balance + ' ' + fmt(Math.abs(diff)) + ' (' + (diff > 0 ? L.debit_more : L.credit_more) + ')' }}
        </p>

        <div class="flex flex-wrap items-center gap-3">
            <button type="button" :class="PRIMARY" :disabled="!balanced" @click="post">{{ L.post }}</button>
            <span v-if="posted" class="text-sm text-emerald-600 dark:text-emerald-400" role="status">{{ L.posted }}</span>
        </div>
    </DemoFrame>
</template>
