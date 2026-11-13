<script setup>
/**
 * Discount rules on sample data: pick an item, a quantity and a customer group
 * and see which rules apply, which one wins and why. Discounts never stack: the
 * most specific rule wins, a rule tied to the customer's group beats the same
 * scope without one, then Priority, then the larger discount.
 */
import { computed, ref } from 'vue'
import DemoFrame from './DemoFrame.vue'
import { FIELD, cents, money, toNum } from './shared'

const props = defineProps({ labels: { type: Object, required: true } })
const L = computed(() => props.labels)
const fmt = (n) => money(n, L.value.number_locale)

const item = ref(0)
const qty = ref(12)
const group = ref(0)

const SPECIFICITY = { item: 4, category: 3, brand: 2, all: 1 }
const it = computed(() => L.value.items[item.value])
const lineValue = computed(() => cents(toNum(qty.value) * it.value.price))

// Why a rule does or does not apply to this line.
const check = (r) => {
    const target = { item: it.value.name, category: it.value.category, brand: it.value.brand }[r.scope]
    if (r.scope !== 'all' && r.target !== target) return 'scope'
    if (r.group !== null && r.group !== group.value) return 'group'
    if (toNum(qty.value) < r.min_qty) return 'qty'
    return ''
}
const amountOf = (r) => cents(r.type === 'percent' ? lineValue.value * r.value / 100 : Math.min(r.value, lineValue.value))

const applying = computed(() => L.value.rules.map((r, i) => ({ r, i, why: check(r) })).filter((x) => !x.why))
const winner = computed(() => {
    const list = applying.value.slice()
    list.sort((a, b) => {
        const sa = SPECIFICITY[a.r.scope] + (a.r.group !== null ? 0.5 : 0)
        const sb = SPECIFICITY[b.r.scope] + (b.r.group !== null ? 0.5 : 0)
        if (sa !== sb) return sb - sa
        if (a.r.priority !== b.r.priority) return a.r.priority ? -1 : 1
        return amountOf(b.r) - amountOf(a.r)
    })
    return list[0] ?? null
})
const discount = computed(() => (winner.value ? amountOf(winner.value.r) : 0))
const ruleText = (r) => `${L.value.scopes[r.scope]}${r.scope === 'all' ? '' : ': ' + r.target} · ${r.type === 'percent' ? r.value + '%' : fmt(r.value)}${r.min_qty ? ' · ≥ ' + r.min_qty : ''} · ${r.group === null ? L.value.all_groups : L.value.groups[r.group]}${r.priority ? ' · ' + L.value.priority : ''}`
</script>

<template>
    <DemoFrame :banner="L.banner" :title="L.form_title">
        <div class="grid gap-3 sm:grid-cols-3">
            <label class="space-y-1 text-xs text-muted-foreground">{{ L.item }}<select v-model.number="item" :class="FIELD"><option v-for="(x, i) in L.items" :key="x.name" :value="i">{{ x.name }} ({{ fmt(x.price) }})</option></select></label>
            <label class="space-y-1 text-xs text-muted-foreground">{{ L.qty }}<input v-model="qty" type="number" min="0" :class="FIELD" /></label>
            <label class="space-y-1 text-xs text-muted-foreground">{{ L.customer_group }}<select v-model.number="group" :class="FIELD"><option v-for="(g, i) in L.groups" :key="g" :value="i">{{ g }}</option></select></label>
        </div>

        <ul class="space-y-2">
            <li v-for="(r, i) in L.rules" :key="i" class="rounded-xl border px-3 py-2 text-sm"
                :class="winner && winner.i === i ? 'border-emerald-500/60 bg-emerald-500/10' : check(r) ? 'border-border opacity-60' : 'border-border'">
                <p class="font-medium text-foreground">{{ r.name }}</p>
                <p class="text-xs text-muted-foreground">{{ ruleText(r) }}</p>
                <p class="mt-1 text-xs" :class="winner && winner.i === i ? 'font-semibold text-emerald-700 dark:text-emerald-300' : 'text-muted-foreground'">
                    {{ winner && winner.i === i ? L.wins : check(r) ? L.not_applicable[check(r)] : L.beaten }}
                </p>
            </li>
        </ul>

        <dl class="space-y-1 rounded-xl border border-border bg-muted/30 p-3 text-sm tabular-nums">
            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">{{ L.line_value }}</dt><dd>{{ fmt(lineValue) }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">{{ L.discount }}<span v-if="winner"> — {{ winner.r.name }}</span></dt><dd>− {{ fmt(discount) }}</dd></div>
            <div class="flex justify-between gap-3 border-t border-border pt-1 font-bold text-foreground"><dt>{{ L.line_total }}</dt><dd>{{ fmt(lineValue - discount) }}</dd></div>
        </dl>
        <p class="text-xs text-muted-foreground">{{ L.note }}</p>
    </DemoFrame>
</template>
