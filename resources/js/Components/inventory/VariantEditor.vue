<script setup>
/**
 * Edits the variants of an item.
 *
 * Every item carries at least one variant, so this component always renders a
 * row. Trades with no choices (a bag of salt) see a single collapsed "default"
 * row holding the SKU, barcode and price; trades with choices get the full
 * grid and can add attribute columns.
 *
 * Attribute names are free text for now — the attribute-definition tables land
 * in a later phase, at which point the name input becomes a select. The stored
 * shape does not change, so nothing here needs rewriting then.
 */
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import NextInput from '@/Components/next/NextInput.vue'
import { Button } from '@/Components/ui/button'
import { Checkbox } from '@/Components/ui/checkbox'
import { Badge } from '@/Components/ui/badge'
import { Plus, Trash2, Star, Tag } from 'lucide-vue-next'

const { t } = useI18n()

const props = defineProps({
    modelValue: { type: Array, required: true },
    errors: { type: Object, default: () => ({}) },
    // When false the grid collapses to the single default row.
    enabled: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const variants = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
})

/** Union of every attribute name used across the rows — the grid columns. */
const attributeNames = computed(() => {
    const names = []
    props.modelValue.forEach((variant) => {
        Object.keys(variant.attributes ?? {}).forEach((name) => {
            if (!names.includes(name)) names.push(name)
        })
    })
    return names
})

const newAttributeName = ref('')

const addAttribute = () => {
    const name = newAttributeName.value.trim().toLowerCase()
    if (!name || attributeNames.value.includes(name)) return

    const next = props.modelValue.map((variant) => ({
        ...variant,
        attributes: { ...(variant.attributes ?? {}), [name]: '' },
    }))

    variants.value = next
    newAttributeName.value = ''
}

const removeAttribute = (name) => {
    variants.value = props.modelValue.map((variant) => {
        const attributes = { ...(variant.attributes ?? {}) }
        delete attributes[name]
        return { ...variant, attributes }
    })
}

const blankVariant = (sortOrder) => ({
    id: null,
    attributes: attributeNames.value.reduce((acc, name) => ({ ...acc, [name]: '' }), {}),
    name: '',
    sku: '',
    barcode: '',
    sale_price: '',
    purchase_price: '',
    minimum_stock: '',
    is_default: false,
    is_active: true,
    sort_order: sortOrder,
})

const addVariant = () => {
    variants.value = [...props.modelValue, blankVariant(props.modelValue.length)]
}

const removeVariant = (index) => {
    if (props.modelValue.length <= 1) return

    const next = props.modelValue.filter((_, i) => i !== index)

    // Something must remain the default, or price lookups lose their fallback.
    if (!next.some((v) => v.is_default) && next.length) {
        next[0] = { ...next[0], is_default: true }
    }

    variants.value = next.map((v, i) => ({ ...v, sort_order: i }))
}

/** Exactly one default, enforced here as well as server-side. */
const makeDefault = (index) => {
    variants.value = props.modelValue.map((variant, i) => ({
        ...variant,
        is_default: i === index,
    }))
}

const setAttribute = (index, name, value) => {
    variants.value = props.modelValue.map((variant, i) =>
        i === index
            ? { ...variant, attributes: { ...(variant.attributes ?? {}), [name]: value } }
            : variant
    )
}

const setField = (index, field, value) => {
    variants.value = props.modelValue.map((variant, i) =>
        i === index ? { ...variant, [field]: value } : variant
    )
}

const errorFor = (index, field) => props.errors?.[`variants.${index}.${field}`]

const variantLabel = (variant) => {
    const values = Object.values(variant.attributes ?? {}).filter(Boolean)
    return values.length ? values.join(' / ') : t('item.default_variant')
}
</script>

<template>
    <fieldset class="rounded-xl border border-border bg-muted/30 px-4 pb-4 pt-2">
        <legend class="flex items-center gap-2 px-2 text-sm font-semibold text-violet-500">
            <Tag class="h-4 w-4" />
            {{ t('item.variants') }}
            <Badge v-if="enabled" variant="secondary">{{ modelValue.length }}</Badge>
        </legend>

        <!-- Simple trades: one row, no grid. -->
        <div v-if="!enabled" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 pt-2">
            <NextInput
                :label="t('item.sku')"
                :model-value="modelValue[0]?.sku ?? ''"
                :disabled="disabled"
                :error="errorFor(0, 'sku')"
                @update:modelValue="(v) => setField(0, 'sku', v)"
            />
            <NextInput
                :label="t('item.barcode')"
                :model-value="modelValue[0]?.barcode ?? ''"
                :disabled="disabled"
                :error="errorFor(0, 'barcode')"
                @update:modelValue="(v) => setField(0, 'barcode', v)"
            />
            <NextInput
                :label="t('item.sale_price')"
                type="number"
                :model-value="modelValue[0]?.sale_price ?? ''"
                :disabled="disabled"
                :error="errorFor(0, 'sale_price')"
                @update:modelValue="(v) => setField(0, 'sale_price', v)"
            />
            <NextInput
                :label="t('item.minimum_stock')"
                type="number"
                :model-value="modelValue[0]?.minimum_stock ?? ''"
                :disabled="disabled"
                :error="errorFor(0, 'minimum_stock')"
                @update:modelValue="(v) => setField(0, 'minimum_stock', v)"
            />
        </div>

        <template v-else>
            <!-- Attribute columns -->
            <div class="flex flex-wrap items-center gap-2 py-3">
                <span class="text-xs text-muted-foreground">{{ t('item.variant_attributes') }}:</span>

                <Badge
                    v-for="name in attributeNames"
                    :key="name"
                    variant="outline"
                    class="gap-1 border-violet-500/40 text-violet-600 dark:text-violet-300"
                >
                    {{ name }}
                    <button
                        v-if="!disabled"
                        type="button"
                        class="ms-1 text-muted-foreground hover:text-red-500"
                        @click="removeAttribute(name)"
                    >
                        &times;
                    </button>
                </Badge>

                <div v-if="!disabled" class="flex items-center gap-1">
                    <input
                        v-model="newAttributeName"
                        :placeholder="t('item.add_attribute_placeholder')"
                        class="h-7 w-40 rounded-md border border-border bg-background px-2 text-xs"
                        @keydown.enter.prevent="addAttribute"
                    />
                    <Button type="button" size="sm" variant="outline" class="h-7" @click="addAttribute">
                        <Plus class="h-3 w-3" />
                    </Button>
                </div>
            </div>

            <div class="overflow-x-auto rounded-lg border border-border">
                <table class="w-full text-xs">
                    <thead class="bg-violet-500 text-white">
                        <tr>
                            <th class="px-2 py-2 text-center font-medium w-10">
                                <Star class="mx-auto h-3.5 w-3.5" />
                            </th>
                            <th
                                v-for="name in attributeNames"
                                :key="name"
                                class="px-2 py-2 text-start font-medium capitalize whitespace-nowrap"
                            >
                                {{ name }}
                            </th>
                            <th class="px-2 py-2 text-start font-medium">{{ t('item.sku') }}</th>
                            <th class="px-2 py-2 text-start font-medium">{{ t('item.barcode') }}</th>
                            <th class="px-2 py-2 text-start font-medium">{{ t('item.sale_price') }}</th>
                            <th class="px-2 py-2 text-start font-medium">{{ t('item.minimum_stock') }}</th>
                            <th class="px-2 py-2 text-center font-medium w-12">{{ t('general.active') }}</th>
                            <th class="px-2 py-2 text-center font-medium w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(variant, index) in modelValue"
                            :key="variant.id ?? index"
                            class="border-t border-border/60 hover:bg-muted/40"
                            :class="{ 'opacity-50': variant.is_active === false }"
                        >
                            <td class="px-2 py-1.5 text-center">
                                <button
                                    type="button"
                                    :disabled="disabled"
                                    :title="t('item.make_default_variant')"
                                    @click="makeDefault(index)"
                                >
                                    <Star
                                        class="h-4 w-4"
                                        :class="variant.is_default
                                            ? 'fill-amber-400 text-amber-500'
                                            : 'text-muted-foreground'"
                                    />
                                </button>
                            </td>

                            <td v-for="name in attributeNames" :key="name" class="px-1 py-1">
                                <input
                                    :value="variant.attributes?.[name] ?? ''"
                                    :disabled="disabled"
                                    class="h-8 w-full rounded border border-border bg-background px-2"
                                    @input="(e) => setAttribute(index, name, e.target.value)"
                                />
                            </td>

                            <td class="px-1 py-1">
                                <input
                                    :value="variant.sku ?? ''"
                                    :disabled="disabled"
                                    class="h-8 w-28 rounded border border-border bg-background px-2"
                                    :class="{ 'border-red-500': errorFor(index, 'sku') }"
                                    @input="(e) => setField(index, 'sku', e.target.value)"
                                />
                            </td>
                            <td class="px-1 py-1">
                                <input
                                    :value="variant.barcode ?? ''"
                                    :disabled="disabled"
                                    class="h-8 w-32 rounded border border-border bg-background px-2"
                                    :class="{ 'border-red-500': errorFor(index, 'barcode') }"
                                    @input="(e) => setField(index, 'barcode', e.target.value)"
                                />
                            </td>
                            <td class="px-1 py-1">
                                <input
                                    :value="variant.sale_price ?? ''"
                                    type="number"
                                    step="0.01"
                                    :disabled="disabled"
                                    class="h-8 w-24 rounded border border-border bg-background px-2 text-end"
                                    @input="(e) => setField(index, 'sale_price', e.target.value)"
                                />
                            </td>
                            <td class="px-1 py-1">
                                <input
                                    :value="variant.minimum_stock ?? ''"
                                    type="number"
                                    step="0.01"
                                    :disabled="disabled"
                                    class="h-8 w-24 rounded border border-border bg-background px-2 text-end"
                                    @input="(e) => setField(index, 'minimum_stock', e.target.value)"
                                />
                            </td>
                            <td class="px-2 py-1.5 text-center">
                                <Checkbox
                                    :model-value="variant.is_active !== false"
                                    :disabled="disabled"
                                    @update:modelValue="(v) => setField(index, 'is_active', v)"
                                />
                            </td>
                            <td class="px-2 py-1.5 text-center">
                                <button
                                    type="button"
                                    :disabled="disabled || modelValue.length <= 1"
                                    class="text-muted-foreground hover:text-red-500 disabled:opacity-30"
                                    @click="removeVariant(index)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-if="errors?.variants" class="mt-1 text-xs text-red-500">{{ errors.variants }}</p>

            <Button
                v-if="!disabled"
                type="button"
                variant="outline"
                size="sm"
                class="mt-3 gap-1.5"
                @click="addVariant"
            >
                <Plus class="h-3.5 w-3.5" />
                {{ t('item.add_variant') }}
            </Button>

            <p class="mt-2 text-xs text-muted-foreground">
                {{ t('item.variant_hint') }}
            </p>
        </template>
    </fieldset>
</template>
