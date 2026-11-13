<script setup>
/**
 * Landed cost on sample data: the extra costs of a shipment are shared across
 * the items of the purchase bill by value, quantity, weight, volume, equally or
 * by hand, and each item shows its landed unit cost.
 */
import { computed, reactive, ref } from 'vue'
import DemoFrame from './DemoFrame.vue'
import { FIELD, cents, money, toNum } from './shared'

const props = defineProps({ labels: { type: Object, required: true } })
const L = computed(() => props.labels)
const fmt = (n) => money(n, L.value.number_locale)

const method = ref('by_value')
const costs = reactive([
    { key: 0, amount: 12000 },
    { key: 1, amount: 8000 },
])
const manual = reactive([0, 0, 0])

const lines = computed(() => L.value.items)
const totalCost = computed(() => cents(costs.reduce((s, c) => s + toNum(c.amount), 0)))

const basis = (it, i) => {
    switch (method.value) {
        case 'by_value': return it.qty * it.cost
        case 'by_quantity': return it.qty
        case 'by_weight': return it.qty * it.weight
        case 'by_volume': return it.qty * it.volume
        case 'equal': return 1
        default: return toNum(manual[i])
    }
}
const basisTotal = computed(() => lines.value.reduce((s, it, i) => s + basis(it, i), 0))

// Each share at cents; the last line takes the remainder so the total is exact.
const allocation = computed(() => {
    if (method.value === 'manual') return lines.value.map((_, i) => cents(toNum(manual[i])))
    let left = totalCost.value
    return lines.value.map((it, i) => {
        if (i === lines.value.length - 1) return cents(left)
        const share = basisTotal.value > 0 ? cents(totalCost.value * basis(it, i) / basisTotal.value) : 0
        left = cents(left - share)
        return share
    })
})
const allocated = computed(() => cents(allocation.value.reduce((s, a) => s + a, 0)))
const remaining = computed(() => cents(totalCost.value - allocated.value))
const landedUnit = (it, i) => (it.qty > 0 ? it.cost + allocation.value[i] / it.qty : it.cost)
const methods = ['by_value', 'by_quantity', 'by_weight', 'by_volume', 'equal', 'manual']
</script>

<template>
    <DemoFrame :banner="L.banner" :title="L.form_title">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2 rounded-xl border border-border p-3">
                <p class="text-xs font-semibold text-muted-foreground">{{ L.costs }}</p>
                <label v-for="c in costs" :key="c.key" class="flex items-center gap-2 text-sm text-foreground">
                    <span class="w-24 shrink-0">{{ L.categories[c.key] }}</span>
                    <input v-model="c.amount" type="number" min="0" :class="FIELD" />
                </label>
                <p class="flex justify-between border-t border-border pt-1 text-sm font-semibold text-foreground"><span>{{ L.total_cost }}</span><span class="tabular-nums">{{ fmt(totalCost) }}</span></p>
            </div>
            <div class="space-y-2 rounded-xl border border-border p-3">
                <label class="space-y-1 text-xs font-semibold text-muted-foreground">{{ L.method }}
                    <select v-model="method" :class="FIELD"><option v-for="m in methods" :key="m" :value="m">{{ L.methods[m] }}</option></select>
                </label>
                <p class="text-xs text-muted-foreground">{{ L.method_notes[method] }}</p>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-border">
            <table class="w-full min-w-[40rem] border-collapse text-sm">
                <thead class="bg-muted/60 text-xs text-muted-foreground">
                    <tr>
                        <th class="px-2 py-2 text-start font-semibold">{{ L.item }}</th>
                        <th class="px-2 py-2 text-end font-semibold">{{ L.qty }}</th>
                        <th class="px-2 py-2 text-end font-semibold">{{ L.base_cost }}</th>
                        <th class="px-2 py-2 text-end font-semibold">{{ L.weight }}</th>
                        <th class="px-2 py-2 text-end font-semibold">{{ L.volume }}</th>
                        <th class="px-2 py-2 text-end font-semibold">{{ L.allocated }}</th>
                        <th class="px-2 py-2 text-end font-semibold">{{ L.landed_unit }}</th>
                    </tr>
                </thead>
                <tbody class="tabular-nums">
                    <tr v-for="(it, i) in lines" :key="it.name" class="border-t border-border">
                        <td class="px-2 py-2 text-foreground">{{ it.name }}</td>
                        <td class="px-2 py-2 text-end">{{ it.qty }}</td>
                        <td class="px-2 py-2 text-end">{{ fmt(it.cost) }}</td>
                        <td class="px-2 py-2 text-end">{{ it.weight }}</td>
                        <td class="px-2 py-2 text-end">{{ it.volume }}</td>
                        <td class="w-32 px-2 py-2 text-end">
                            <input v-if="method === 'manual'" v-model="manual[i]" type="number" min="0" :class="FIELD" />
                            <span v-else>{{ fmt(allocation[i]) }}</span>
                        </td>
                        <td class="px-2 py-2 text-end font-semibold text-foreground">{{ fmt(landedUnit(it, i)) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="text-sm" :class="remaining === 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-destructive'">
            {{ remaining === 0 ? L.fully_allocated : L.not_allocated + ' ' + fmt(remaining) }}
        </p>
    </DemoFrame>
</template>
