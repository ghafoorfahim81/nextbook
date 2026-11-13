<script setup>
/**
 * A purchase bill on sample data: discounts lower what the goods cost, free
 * units share the cost, tax is part of the cost, and the payment type decides
 * what is still owed. The same arithmetic as the real form.
 */
import { computed, reactive, ref } from 'vue'
import { Plus, Trash2 } from 'lucide-vue-next'
import DemoFrame from './DemoFrame.vue'
import { BUTTON, FIELD, PRIMARY, cents, money, toNum } from './shared'

const props = defineProps({ labels: { type: Object, required: true } })
const L = computed(() => props.labels)
const fmt = (n) => money(n, L.value.number_locale)

const type = ref('credit')
const billDiscount = ref(5)
const paid = ref(1000)
const saved = ref(false)
let nextId = 3
const rows = reactive([
    { id: 1, item: 0, qty: 45, free: 5, price: 40, discount: 0, tax: 0 },
    { id: 2, item: 1, qty: 10, free: 0, price: 300, discount: 100, tax: 0 },
])

const gross = (r) => cents(toNum(r.qty) * toNum(r.price))
const goods = computed(() => cents(rows.reduce((s, r) => s + gross(r), 0)))
// A percentage bill discount is taken on the value of the goods, each line its share.
const share = (r) => cents(gross(r) * (toNum(billDiscount.value) / 100))
const net = (r) => cents(gross(r) - toNum(r.discount) - share(r))
const lineCost = (r) => cents(net(r) + toNum(r.tax))
const received = (r) => toNum(r.qty) + toNum(r.free)
const unitCost = (r) => (received(r) > 0 ? lineCost(r) / received(r) : 0)

const total = computed(() => cents(rows.reduce((s, r) => s + lineCost(r), 0)))
const paidNow = computed(() => {
    if (type.value === 'cash') return total.value
    if (type.value === 'loan') return 0
    return Math.min(Math.max(0, toNum(paid.value)), total.value)
})
const owed = computed(() => cents(total.value - paidNow.value))
const status = computed(() => (owed.value <= 0 ? 'paid' : paidNow.value > 0 ? 'partial' : 'unpaid'))

const addRow = () => rows.push({ id: nextId++, item: 0, qty: 1, free: 0, price: L.value.items[0].price, discount: 0, tax: 0 })
const removeRow = (r) => { if (rows.length > 1) rows.splice(rows.indexOf(r), 1) }
const pick = (r, i) => { r.item = i; r.price = L.value.items[i].price }
const save = () => { saved.value = true; setTimeout(() => { saved.value = false }, 3500) }
</script>

<template>
    <DemoFrame :banner="L.banner" :title="L.form_title">
        <div class="grid gap-3 sm:grid-cols-3">
            <label class="space-y-1 text-xs text-muted-foreground">{{ L.supplier }}
                <select :class="FIELD"><option v-for="n in L.suppliers" :key="n">{{ n }}</option></select>
            </label>
            <label class="space-y-1 text-xs text-muted-foreground">{{ L.purchase_type }}
                <select v-model="type" :class="FIELD">
                    <option value="cash">{{ L.cash }}</option>
                    <option value="credit">{{ L.credit }}</option>
                    <option value="loan">{{ L.loan }}</option>
                </select>
            </label>
            <label v-if="type === 'credit'" class="space-y-1 text-xs text-muted-foreground">{{ L.paid_now }}
                <input v-model="paid" type="number" min="0" :class="FIELD" />
            </label>
        </div>

        <div class="overflow-x-auto rounded-xl border border-border">
            <table class="w-full min-w-[46rem] border-collapse text-sm">
                <thead class="bg-muted/60 text-xs text-muted-foreground">
                    <tr>
                        <th class="px-2 py-2 text-start font-semibold">{{ L.item }}</th>
                        <th class="w-20 px-2 py-2 text-start font-semibold">{{ L.qty }}</th>
                        <th class="w-20 px-2 py-2 text-start font-semibold">{{ L.free }}</th>
                        <th class="w-24 px-2 py-2 text-start font-semibold">{{ L.price }}</th>
                        <th class="w-24 px-2 py-2 text-start font-semibold">{{ L.discount }}</th>
                        <th class="w-20 px-2 py-2 text-start font-semibold">{{ L.tax }}</th>
                        <th class="w-24 px-2 py-2 text-end font-semibold">{{ L.unit_cost }}</th>
                        <th class="w-8" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in rows" :key="r.id" class="border-t border-border align-top">
                        <td class="px-2 py-2"><select :value="r.item" :class="FIELD" @change="pick(r, Number($event.target.value))"><option v-for="(it, i) in L.items" :key="it.name" :value="i">{{ it.name }}</option></select></td>
                        <td class="px-2 py-2"><input v-model="r.qty" type="number" min="0" :class="FIELD" /></td>
                        <td class="px-2 py-2"><input v-model="r.free" type="number" min="0" :class="FIELD" /></td>
                        <td class="px-2 py-2"><input v-model="r.price" type="number" min="0" :class="FIELD" /></td>
                        <td class="px-2 py-2"><input v-model="r.discount" type="number" min="0" :class="FIELD" /></td>
                        <td class="px-2 py-2"><input v-model="r.tax" type="number" min="0" :class="FIELD" /></td>
                        <td class="px-2 py-2 text-end tabular-nums font-medium text-foreground">{{ fmt(unitCost(r)) }}</td>
                        <td class="px-1 py-2 text-center"><button type="button" class="rounded p-1 text-destructive hover:bg-destructive/10" :aria-label="L.remove" @click="removeRow(r)"><Trash2 class="size-4" /></button></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <button type="button" :class="BUTTON" @click="addRow"><Plus class="size-4" /> {{ L.add_line }}</button>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2 rounded-xl border border-border p-3">
                <p class="text-xs font-semibold text-muted-foreground">{{ L.bill_discount }} (%)</p>
                <input v-model="billDiscount" type="number" min="0" max="100" :class="FIELD" />
                <p class="text-xs text-muted-foreground">{{ L.cost_note }}</p>
            </div>
            <dl class="space-y-1 rounded-xl border border-border bg-muted/30 p-3 text-sm tabular-nums">
                <div class="flex justify-between gap-3"><dt class="text-muted-foreground">{{ L.goods }}</dt><dd>{{ fmt(goods) }}</dd></div>
                <div class="flex justify-between gap-3 border-t border-border pt-1 font-bold text-foreground"><dt>{{ L.total }}</dt><dd>{{ fmt(total) }} {{ L.currency }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-muted-foreground">{{ L.paid }}</dt><dd>{{ fmt(paidNow) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-muted-foreground">{{ L.owed }}</dt><dd>{{ fmt(owed) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-muted-foreground">{{ L.status }}</dt><dd class="font-medium text-foreground">{{ L[status] }}</dd></div>
            </dl>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button type="button" :class="PRIMARY" @click="save">{{ L.save }}</button>
            <span v-if="saved" class="text-sm text-emerald-600 dark:text-emerald-400" role="status">{{ L.saved }}</span>
        </div>
    </DemoFrame>
</template>
