<script setup>
/**
 * A small, self-contained sales-invoice form for the user manual: the same
 * arithmetic as the real form (line discount and tax as amounts, bill discount
 * as a percentage of the goods or a fixed amount, one currency conversion), on
 * sample data, with nothing sent anywhere. Every word comes from `labels`, so
 * the demo reads in the language the guide is shown in.
 */
import { computed, reactive, ref } from 'vue'
import { Plus, Sparkles, Trash2 } from 'lucide-vue-next'

const props = defineProps({
    labels: { type: Object, required: true },
})

const L = computed(() => props.labels)

// The company currency and one foreign currency with a rate, as on a real bill.
const RATE = 65
const currency = ref('base')
const rate = computed(() => (currency.value === 'base' ? 1 : RATE))
const symbol = computed(() => (currency.value === 'base' ? L.value.base_currency : L.value.foreign_currency))

const customer = ref(0)
const billDiscountType = ref('percentage')
const billDiscount = ref(5)
const saved = ref(false)

let nextId = 3
const rows = reactive([
    { id: 1, item: 0, qty: 5, price: 280, discount: 0, tax: 0 },
    { id: 2, item: 1, qty: 2, price: 1450, discount: 100, tax: 0 },
])

const toNum = (value) => {
    const n = Number(String(value ?? '').replace(/[,٬]/g, ''))
    return Number.isFinite(n) ? n : 0
}
const cents = (value) => Math.round((value + Number.EPSILON) * 100) / 100

const lineGoods = (row) => cents(toNum(row.qty) * toNum(row.price))
const lineTotal = (row) => cents(lineGoods(row) - toNum(row.discount) + toNum(row.tax))

const goods = computed(() => cents(rows.reduce((sum, row) => sum + lineGoods(row), 0)))
const lineDiscounts = computed(() => cents(rows.reduce((sum, row) => sum + toNum(row.discount), 0)))
const tax = computed(() => cents(rows.reduce((sum, row) => sum + toNum(row.tax), 0)))

// A percentage is taken on the value of the goods, before line discounts; a
// fixed amount comes off once.
const billDiscountAmount = computed(() => {
    const raw = billDiscountType.value === 'percentage'
        ? goods.value * (toNum(billDiscount.value) / 100)
        : toNum(billDiscount.value)
    return cents(Math.max(0, raw))
})
const total = computed(() => cents(goods.value - lineDiscounts.value - billDiscountAmount.value + tax.value))
const discountTooBig = computed(() => goods.value - lineDiscounts.value - billDiscountAmount.value < 0)

// Prices are kept in the company currency; a foreign bill shows them divided
// by the rate and turns what you type back into the company currency.
const shownPrice = (row) => (rate.value === 1 ? row.price : Number((toNum(row.price) / rate.value).toFixed(4)))
const setPrice = (row, value) => { row.price = rate.value === 1 ? value : toNum(value) * rate.value }
const shown = (amount) => (rate.value === 1 ? amount : cents(amount / rate.value))

const money = (amount) => toNum(amount).toLocaleString(L.value.number_locale || 'en', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
})

const pickItem = (row, index) => {
    row.item = index
    row.price = L.value.items[index].price
    row.discount = 0
    row.tax = 0
}
const addRow = () => {
    rows.push({ id: nextId++, item: 0, qty: 1, price: L.value.items[0].price, discount: 0, tax: 0 })
}
const removeRow = (row) => {
    const index = rows.indexOf(row)
    if (index >= 0 && rows.length > 1) rows.splice(index, 1)
}
const save = () => { saved.value = true; setTimeout(() => { saved.value = false }, 3500) }

// The option rows get the card colours too: a native list otherwise opens white with pale text in dark mode.
const field = 'w-full rounded-lg border border-border bg-background px-2 py-1.5 text-sm text-foreground outline-none focus:border-primary focus:ring-1 focus:ring-primary [&>option]:bg-card [&>option]:text-foreground'
</script>

<template>
    <div class="overflow-hidden rounded-2xl border-2 border-dashed border-primary/40 bg-card">
        <div class="flex items-center justify-between gap-2 border-b border-dashed border-primary/40 bg-primary/10 px-4 py-2 text-xs font-medium text-primary">
            <span class="inline-flex items-center gap-1.5"><Sparkles class="size-3.5" /> {{ L.banner }}</span>
            <span class="text-muted-foreground">{{ L.form_title }}</span>
        </div>

        <div class="space-y-4 p-4">
            <div class="grid gap-3 sm:grid-cols-3">
                <label class="space-y-1 text-xs text-muted-foreground">
                    {{ L.customer }}
                    <select v-model.number="customer" :class="field">
                        <option v-for="(name, i) in L.customers" :key="name" :value="i">{{ name }}</option>
                    </select>
                </label>
                <label class="space-y-1 text-xs text-muted-foreground">
                    {{ L.date }}
                    <input :class="field" :value="L.sample_date" readonly />
                </label>
                <label class="space-y-1 text-xs text-muted-foreground">
                    {{ L.currency }}
                    <select v-model="currency" :class="field">
                        <option value="base">{{ L.base_currency }}</option>
                        <option value="foreign">{{ L.foreign_currency }} ({{ L.rate }} {{ RATE }})</option>
                    </select>
                </label>
            </div>

            <div class="overflow-x-auto rounded-xl border border-border">
                <table class="w-full min-w-[40rem] border-collapse text-sm">
                    <thead class="bg-muted/60 text-xs text-muted-foreground">
                        <tr>
                            <th class="px-2 py-2 text-start font-semibold">{{ L.item }}</th>
                            <th class="w-20 px-2 py-2 text-start font-semibold">{{ L.qty }}</th>
                            <th class="w-28 px-2 py-2 text-start font-semibold">{{ L.price }}</th>
                            <th class="w-24 px-2 py-2 text-start font-semibold">{{ L.line_discount }}</th>
                            <th class="w-20 px-2 py-2 text-start font-semibold">{{ L.tax }}</th>
                            <th class="w-28 px-2 py-2 text-end font-semibold">{{ L.total }}</th>
                            <th class="w-8 px-1 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.id" class="border-t border-border align-top">
                            <td class="px-2 py-2">
                                <select :value="row.item" :class="field" @change="pickItem(row, Number($event.target.value))">
                                    <option v-for="(it, i) in L.items" :key="it.name" :value="i">{{ it.name }}</option>
                                </select>
                            </td>
                            <td class="px-2 py-2"><input v-model="row.qty" type="number" min="0" :class="field" /></td>
                            <td class="px-2 py-2">
                                <input :value="shownPrice(row)" type="number" min="0" step="any" :class="field" @input="setPrice(row, $event.target.value)" />
                            </td>
                            <td class="px-2 py-2"><input v-model="row.discount" type="number" min="0" :class="field" /></td>
                            <td class="px-2 py-2"><input v-model="row.tax" type="number" min="0" :class="field" /></td>
                            <td class="px-2 py-2 text-end font-medium tabular-nums text-foreground">{{ money(shown(lineTotal(row))) }}</td>
                            <td class="px-1 py-2 text-center">
                                <button type="button" class="rounded p-1 text-destructive hover:bg-destructive/10" :aria-label="L.remove" @click="removeRow(row)">
                                    <Trash2 class="size-4" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-border px-3 py-1.5 text-sm text-foreground hover:bg-muted" @click="addRow">
                <Plus class="size-4" /> {{ L.add_line }}
            </button>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-2 rounded-xl border border-border p-3">
                    <p class="text-xs font-semibold text-muted-foreground">{{ L.bill_discount }}</p>
                    <div class="flex gap-2">
                        <input v-model="billDiscount" type="number" min="0" :class="[field, 'min-w-0 flex-1']" />
                        <select v-model="billDiscountType" class="w-24 shrink-0 rounded-lg border border-border bg-background px-2 py-1.5 text-sm text-foreground outline-none focus:border-primary focus:ring-1 focus:ring-primary [&>option]:bg-card [&>option]:text-foreground">
                            <option value="percentage">%</option>
                            <option value="fixed">{{ symbol }}</option>
                        </select>
                    </div>
                    <p v-if="discountTooBig" class="text-xs text-destructive">{{ L.discount_too_big }}</p>
                </div>

                <dl class="space-y-1 rounded-xl border border-border bg-muted/30 p-3 text-sm tabular-nums">
                    <div class="flex justify-between gap-3"><dt class="text-muted-foreground">{{ L.goods }}</dt><dd>{{ money(shown(goods)) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted-foreground">{{ L.line_discounts }}</dt><dd>− {{ money(shown(lineDiscounts)) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted-foreground">{{ L.bill_discount }}</dt><dd>− {{ money(shown(billDiscountAmount)) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted-foreground">{{ L.tax }}</dt><dd>+ {{ money(shown(tax)) }}</dd></div>
                    <div class="flex justify-between gap-3 border-t border-border pt-1 text-base font-bold text-foreground">
                        <dt>{{ L.grand_total }}</dt><dd>{{ money(shown(total)) }} {{ symbol }}</dd>
                    </div>
                </dl>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button type="button" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:bg-primary/90" @click="save">
                    {{ L.save }}
                </button>
                <span v-if="saved" class="text-sm text-emerald-600 dark:text-emerald-400" role="status">{{ L.saved }}</span>
            </div>
        </div>
    </div>
</template>
