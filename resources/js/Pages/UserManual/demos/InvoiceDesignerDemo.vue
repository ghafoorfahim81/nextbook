<script setup>
/**
 * The invoice designer on sample data: pick one of five ready-made themes,
 * switch parts of the invoice on and off, edit the footer note, and watch the
 * printed invoice change. Nothing is saved.
 */
import { computed, reactive, ref } from 'vue'
import DemoFrame from './DemoFrame.vue'
import { FIELD, cents, money } from './shared'

const props = defineProps({ labels: { type: Object, required: true } })
const L = computed(() => props.labels)
const fmt = (n) => money(n, L.value.number_locale)

const THEMES = [
    { color: '#7c3aed', band: true },
    { color: '#0369a1', band: false },
    { color: '#15803d', band: true },
    { color: '#c2410c', band: false },
    { color: '#1f2937', band: true },
]
const theme = ref(0)
const opts = reactive({ logo: true, phone: true, discount: true, terms: true, signature: true })
const footer = ref(L.value.footer_sample)
const t = computed(() => THEMES[theme.value])

const lines = computed(() => L.value.lines)
const goods = computed(() => cents(lines.value.reduce((s, l) => s + l.qty * l.price, 0)))
const disc = computed(() => cents(lines.value.reduce((s, l) => s + l.discount, 0)))
const total = computed(() => cents(goods.value - (opts.discount ? disc.value : 0)))
const toggles = ['logo', 'phone', 'discount', 'terms', 'signature']
</script>

<template>
    <DemoFrame :banner="L.banner" :title="L.form_title">
        <div class="grid gap-4 lg:grid-cols-[16rem_1fr]">
            <div class="space-y-3">
                <div>
                    <p class="mb-1 text-xs font-semibold text-muted-foreground">{{ L.theme }}</p>
                    <div class="flex gap-2">
                        <button v-for="(th, i) in THEMES" :key="i" type="button" class="size-9 rounded-lg border-2 text-xs font-bold text-white" :style="{ background: th.color }" :class="theme === i ? 'border-foreground' : 'border-transparent'" :aria-label="L.theme + ' ' + (i + 1)" @click="theme = i">{{ i + 1 }}</button>
                    </div>
                </div>
                <div class="space-y-1.5">
                    <label v-for="k in toggles" :key="k" class="flex items-center gap-2 text-sm text-foreground"><input v-model="opts[k]" type="checkbox" /> {{ L.options[k] }}</label>
                </div>
                <label class="space-y-1 text-xs text-muted-foreground">{{ L.footer }}<input v-model="footer" :class="FIELD" /></label>
            </div>

            <!-- The printed invoice stays light, like paper. -->
            <div class="overflow-x-auto rounded-xl border border-border bg-white p-4 text-[13px] text-gray-800 shadow-sm">
                <div class="flex items-start justify-between gap-3 rounded-md p-3" :style="t.band ? { background: t.color, color: '#fff' } : { borderBottom: '3px solid ' + t.color }">
                    <div class="flex items-center gap-2">
                        <span v-if="opts.logo" class="flex size-10 items-center justify-center rounded-md bg-white/90 text-xs font-bold" :style="{ color: t.color }">NB</span>
                        <div><p class="font-bold">{{ L.company }}</p><p v-if="opts.phone" class="text-[11px] opacity-80">+93 700 000 000</p></div>
                    </div>
                    <div class="text-end"><p class="font-bold">{{ L.invoice }} #12</p><p class="text-[11px] opacity-80">{{ L.sample_date }}</p></div>
                </div>
                <p class="mt-3 text-xs">{{ L.customer }}: <b>{{ L.customer_name }}</b></p>
                <table class="mt-2 w-full min-w-[22rem] border-collapse text-xs">
                    <thead><tr :style="{ background: t.band ? t.color + '22' : '#f3f4f6' }">
                        <th class="p-1.5 text-start">{{ L.item }}</th><th class="p-1.5 text-end">{{ L.qty }}</th><th class="p-1.5 text-end">{{ L.price }}</th>
                        <th v-if="opts.discount" class="p-1.5 text-end">{{ L.discount }}</th><th class="p-1.5 text-end">{{ L.total }}</th>
                    </tr></thead>
                    <tbody class="tabular-nums">
                        <tr v-for="l in lines" :key="l.name" class="border-b border-gray-200">
                            <td class="p-1.5">{{ l.name }}</td><td class="p-1.5 text-end">{{ l.qty }}</td><td class="p-1.5 text-end">{{ fmt(l.price) }}</td>
                            <td v-if="opts.discount" class="p-1.5 text-end">{{ fmt(l.discount) }}</td>
                            <td class="p-1.5 text-end">{{ fmt(l.qty * l.price - (opts.discount ? l.discount : 0)) }}</td>
                        </tr>
                    </tbody>
                </table>
                <p class="mt-2 text-end text-sm font-bold" :style="{ color: t.color }">{{ L.total }}: {{ fmt(total) }} AFN</p>
                <p v-if="opts.terms" class="mt-3 text-[11px] text-gray-500">{{ L.terms }}</p>
                <p v-if="footer" class="mt-1 text-[11px] italic text-gray-600">{{ footer }}</p>
                <div v-if="opts.signature" class="mt-6 flex justify-between text-[11px] text-gray-500"><span class="border-t border-gray-400 px-6 pt-1">{{ L.seller }}</span><span class="border-t border-gray-400 px-6 pt-1">{{ L.buyer }}</span></div>
            </div>
        </div>
    </DemoFrame>
</template>
