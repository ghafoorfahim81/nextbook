<script setup>
/**
 * Bulk pricing on sample data: tick rows, pick an action, a direction and a
 * value, Apply, review, then Save. Prices under cost are marked and rows with
 * no cost are skipped when a margin is set.
 */
import { computed, reactive, ref } from 'vue'
import DemoFrame from './DemoFrame.vue'
import { BUTTON, FIELD, PRIMARY, cents, money, toNum } from './shared'

const props = defineProps({ labels: { type: Object, required: true } })
const L = computed(() => props.labels)
const fmt = (n) => money(n, L.value.number_locale)

const rows = reactive(L.value.items.map((it, i) => ({ id: i, name: it.name, cost: it.cost, saved: it.price, price: it.price, picked: i < 2 })))
const action = ref('percent')
const direction = ref('increase')
const value = ref(10)
const message = ref('')

const changed = computed(() => rows.filter((r) => cents(r.price) !== cents(r.saved)).length)
const needsDirection = computed(() => action.value === 'percent' || action.value === 'amount')

const apply = () => {
    const v = toNum(value.value)
    const sign = direction.value === 'increase' ? 1 : -1
    let skipped = 0
    rows.filter((r) => r.picked).forEach((r) => {
        if (action.value === 'percent') r.price = cents(r.price * (1 + sign * v / 100))
        else if (action.value === 'amount') r.price = cents(r.price + sign * v)
        else if (action.value === 'margin') {
            if (!r.cost) { skipped++; return }
            r.price = cents(r.cost * (1 + v / 100))
        } else if (v > 0) r.price = cents(Math.round(r.price / v) * v)
        if (r.price < 0) r.price = 0
    })
    message.value = skipped ? `${skipped} ${L.value.skipped}` : ''
}
const revert = () => { rows.forEach((r) => { r.price = r.saved }); message.value = L.value.reverted }
const save = () => { rows.forEach((r) => { r.saved = r.price }); message.value = L.value.saved }
const all = computed({
    get: () => rows.every((r) => r.picked),
    set: (v) => rows.forEach((r) => { r.picked = v }),
})
</script>

<template>
    <DemoFrame :banner="L.banner" :title="L.form_title">
        <div class="grid gap-3 sm:grid-cols-4">
            <label class="space-y-1 text-xs text-muted-foreground">{{ L.action }}
                <select v-model="action" :class="FIELD">
                    <option value="percent">{{ L.percent }}</option>
                    <option value="amount">{{ L.amount }}</option>
                    <option value="margin">{{ L.margin }}</option>
                    <option value="round">{{ L.round }}</option>
                </select>
            </label>
            <label class="space-y-1 text-xs text-muted-foreground" :class="needsDirection ? '' : 'opacity-50'">{{ L.direction }}
                <select v-model="direction" :disabled="!needsDirection" :class="FIELD">
                    <option value="increase">{{ L.increase }}</option>
                    <option value="decrease">{{ L.decrease }}</option>
                </select>
            </label>
            <label class="space-y-1 text-xs text-muted-foreground">{{ L.value }}
                <input v-model="value" type="number" min="0" :class="FIELD" />
            </label>
            <div class="flex items-end gap-2">
                <button type="button" :class="PRIMARY" @click="apply">{{ L.apply }}</button>
                <button type="button" :class="BUTTON" @click="revert">{{ L.revert }}</button>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-border">
            <table class="w-full min-w-[34rem] border-collapse text-sm">
                <thead class="bg-muted/60 text-xs text-muted-foreground">
                    <tr>
                        <th class="w-10 px-2 py-2"><input v-model="all" type="checkbox" :aria-label="L.select_all" /></th>
                        <th class="px-2 py-2 text-start font-semibold">{{ L.item }}</th>
                        <th class="px-2 py-2 text-end font-semibold">{{ L.cost }}</th>
                        <th class="px-2 py-2 text-end font-semibold">{{ L.price }}</th>
                        <th class="px-2 py-2 text-end font-semibold">{{ L.margin_col }}</th>
                    </tr>
                </thead>
                <tbody class="tabular-nums">
                    <tr v-for="r in rows" :key="r.id" class="border-t border-border" :class="cents(r.price) !== cents(r.saved) ? 'bg-primary/5' : ''">
                        <td class="px-2 py-2 text-center"><input v-model="r.picked" type="checkbox" /></td>
                        <td class="px-2 py-2 text-foreground">{{ r.name }}</td>
                        <td class="px-2 py-2 text-end">{{ r.cost ? fmt(r.cost) : '—' }}</td>
                        <td class="w-32 px-2 py-2"><input v-model.number="r.price" type="number" min="0" :class="[FIELD, 'text-end']" /></td>
                        <td class="px-2 py-2 text-end">
                            <span v-if="r.cost && r.price < r.cost" class="font-medium text-destructive">{{ L.below_cost }}</span>
                            <span v-else-if="r.cost">{{ ((r.price - r.cost) / r.cost * 100).toFixed(1) }}%</span>
                            <span v-else>—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center gap-3 text-sm">
            <button type="button" :class="PRIMARY" :disabled="!changed" @click="save">{{ L.save }}</button>
            <span class="text-muted-foreground">{{ changed ? changed + ' ' + L.changed : L.nothing_changed }}</span>
            <span v-if="message" class="text-foreground" role="status">{{ message }}</span>
        </div>
    </DemoFrame>
</template>
