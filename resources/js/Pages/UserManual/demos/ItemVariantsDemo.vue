<script setup>
/**
 * Registering one item with several variants, on sample data: attribute
 * columns, one row per combination with its own SKU, barcode and price, a margin
 * helper that fills the sale price (and is not stored), one default variant, and
 * the duplicate-combination check.
 */
import { computed, reactive, ref } from 'vue'
import { Plus, Star, Trash2 } from 'lucide-vue-next'
import DemoFrame from './DemoFrame.vue'
import { BUTTON, FIELD, PRIMARY, cents, toNum } from './shared'

const props = defineProps({ labels: { type: Object, required: true } })
const L = computed(() => props.labels)

const attributes = reactive([...L.value.attributes_default])
let seq = 3
const variants = reactive([
    { id: 1, values: [L.value.sample_values[0][0], L.value.sample_values[1][0]], sku: 'TS-001', barcode: '', cost: 300, margin: 25, price: 375, isDefault: true },
    { id: 2, values: [L.value.sample_values[0][0], L.value.sample_values[1][1]], sku: 'TS-002', barcode: '', cost: 300, margin: 25, price: 375, isDefault: false },
])
const saved = ref(false)

const key = (v) => v.values.map((x) => String(x).trim().toLowerCase()).join('|')
const duplicate = (v) => variants.some((o) => o !== v && key(o) === key(v) && key(v).replace(/\|/g, '') !== '')
const hasDuplicate = computed(() => variants.some(duplicate))

const addVariant = () => variants.push({ id: seq, values: attributes.map(() => ''), sku: 'TS-' + String(seq++).padStart(3, '0'), barcode: '', cost: 300, margin: 25, price: 375, isDefault: false })
const removeVariant = (v) => {
    if (variants.length <= 1) return
    const wasDefault = v.isDefault
    variants.splice(variants.indexOf(v), 1)
    if (wasDefault) variants[0].isDefault = true
}
const setDefault = (v) => variants.forEach((o) => { o.isDefault = o === v })
const addAttribute = () => { if (attributes.length < 3) { attributes.push(L.value.new_attribute); variants.forEach((v) => v.values.push('')) } }
const removeAttribute = (i) => { if (attributes.length > 1) { attributes.splice(i, 1); variants.forEach((v) => v.values.splice(i, 1)) } }
const barcode = (v) => { v.barcode = String(Math.floor(100000000000 + Math.random() * 899999999999)) }
// The margin helper fills the sale price from the purchase price; it is never saved.
const applyMargin = (v) => { v.price = cents(toNum(v.cost) * (1 + toNum(v.margin) / 100)) }
const save = () => { saved.value = !hasDuplicate.value; setTimeout(() => { saved.value = false }, 3500) }
</script>

<template>
    <DemoFrame :banner="L.banner" :title="L.form_title">
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="space-y-1 text-xs text-muted-foreground">{{ L.item_name }}
                <input :class="FIELD" :value="L.item_sample" readonly />
            </label>
            <div class="space-y-1 text-xs text-muted-foreground">{{ L.attributes }}
                <div class="flex flex-wrap gap-2">
                    <span v-for="(a, i) in attributes" :key="i" class="inline-flex items-center gap-1">
                        <input v-model="attributes[i]" :class="[FIELD, 'w-28']" />
                        <button v-if="attributes.length > 1" type="button" class="text-destructive" :aria-label="L.remove" @click="removeAttribute(i)"><Trash2 class="size-3.5" /></button>
                    </span>
                    <button v-if="attributes.length < 3" type="button" :class="BUTTON" @click="addAttribute"><Plus class="size-3.5" /></button>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-border">
            <table class="w-full min-w-[52rem] border-collapse text-sm">
                <thead class="bg-muted/60 text-xs text-muted-foreground">
                    <tr>
                        <th class="w-10 px-2 py-2" />
                        <th v-for="(a, i) in attributes" :key="i" class="px-2 py-2 text-start font-semibold">{{ a }}</th>
                        <th class="px-2 py-2 text-start font-semibold">{{ L.sku }}</th>
                        <th class="px-2 py-2 text-start font-semibold">{{ L.barcode }}</th>
                        <th class="w-24 px-2 py-2 text-start font-semibold">{{ L.purchase_price }}</th>
                        <th class="w-20 px-2 py-2 text-start font-semibold">{{ L.margin }}</th>
                        <th class="w-24 px-2 py-2 text-start font-semibold">{{ L.sale_price }}</th>
                        <th class="w-8" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="v in variants" :key="v.id" class="border-t border-border align-top" :class="duplicate(v) ? 'bg-destructive/10' : ''">
                        <td class="px-2 py-2 text-center">
                            <button type="button" :aria-label="L.default_variant" :title="L.default_variant" @click="setDefault(v)"><Star class="size-4" :class="v.isDefault ? 'fill-amber-400 text-amber-500' : 'text-muted-foreground'" /></button>
                        </td>
                        <td v-for="(a, i) in attributes" :key="i" class="px-2 py-2"><input v-model="v.values[i]" :class="FIELD" /></td>
                        <td class="px-2 py-2"><input v-model="v.sku" :class="FIELD" /></td>
                        <td class="px-2 py-2">
                            <div class="flex gap-1"><input v-model="v.barcode" :class="FIELD" /><button type="button" :class="BUTTON" :title="L.generate_barcode" @click="barcode(v)">#</button></div>
                        </td>
                        <td class="px-2 py-2"><input v-model.number="v.cost" type="number" min="0" :class="FIELD" /></td>
                        <td class="px-2 py-2"><input v-model.number="v.margin" type="number" min="0" :class="FIELD" @input="applyMargin(v)" /></td>
                        <td class="px-2 py-2"><input v-model.number="v.price" type="number" min="0" :class="FIELD" /></td>
                        <td class="px-1 py-2 text-center"><button type="button" class="rounded p-1 text-destructive hover:bg-destructive/10" :aria-label="L.remove" @click="removeVariant(v)"><Trash2 class="size-4" /></button></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-if="hasDuplicate" class="text-sm text-destructive" role="alert">{{ L.duplicate }}</p>
        <p class="text-xs text-muted-foreground">{{ L.hint }}</p>

        <div class="flex flex-wrap items-center gap-3">
            <button type="button" :class="BUTTON" @click="addVariant"><Plus class="size-4" /> {{ L.add_variant }}</button>
            <button type="button" :class="PRIMARY" :disabled="hasDuplicate" @click="save">{{ L.save }}</button>
            <span v-if="saved" class="text-sm text-emerald-600 dark:text-emerald-400" role="status">{{ L.saved }}</span>
        </div>
    </DemoFrame>
</template>
