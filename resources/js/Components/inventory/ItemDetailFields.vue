<script setup>
/**
 * The item fields beyond the core identity/pricing block, grouped and gated by
 * the company's trade profile.
 *
 * Shared by Create and Edit so the two cannot drift apart — that drift is what
 * left the update request carrying the store request's rules.
 *
 * A whole group disappears when the trade uses none of its fields, so a
 * supermarket never sees warranty or HS code, and a computer shop never sees
 * shelf life.
 */
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import NextInput from '@/Components/next/NextInput.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import { Checkbox } from '@/Components/ui/checkbox'
import { Ruler, ShieldCheck, PackageSearch, SlidersHorizontal } from 'lucide-vue-next'

const { t } = useI18n()

const props = defineProps({
    // The Inertia useForm object, mutated in place.
    form: { type: Object, required: true },
    visibleFields: { type: Object, required: true },
    warehouses: { type: Array, default: () => [] },
    costingMethods: { type: Array, default: () => [] },
    pricingMethods: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
})

const shows = (field) => Boolean(props.visibleFields?.[field])

/** Any field in the group switched on for this trade. */
const anyOf = (fields) => fields.some(shows)

const showPhysical = computed(() => anyOf(['weight', 'dimensions']))

const showCompliance = computed(() => anyOf([
    'manufacturer', 'model', 'country_of_origin', 'hs_code', 'warranty_months',
    'shelf_life_days', 'min_shelf_life_percent', 'storage_zone',
    'requires_prescription', 'is_controlled',
]))

const showPlanning = computed(() => anyOf([
    'reorder_quantity', 'lead_time_days', 'default_warehouse',
    'costing_method', 'pricing_method',
]))

const showBehaviour = computed(() => anyOf([
    'is_active', 'is_stockable', 'is_sellable', 'is_purchasable',
    'is_weighted', 'allow_negative_stock',
]))

const storageZoneOptions = computed(() => ['ambient', 'chilled', 'frozen'].map((zone) => ({
    id: zone,
    name: t(`item.storage_zones.${zone}`),
})))

// Checkbox rows rendered from a list so the markup stays one block.
const behaviourFlags = computed(() => ([
    { key: 'is_active', hint: null },
    { key: 'is_stockable', hint: 'is_stockable' },
    { key: 'is_sellable', hint: 'is_sellable' },
    { key: 'is_purchasable', hint: 'is_purchasable' },
    { key: 'is_weighted', hint: 'is_weighted' },
    { key: 'allow_negative_stock', hint: 'allow_negative_stock' },
].filter((flag) => shows(flag.key))))
</script>

<template>
    <div class="space-y-4">
        <!-- Physical -->
        <fieldset v-if="showPhysical" class="rounded-xl border border-border bg-muted/30 px-4 pb-4 pt-2">
            <legend class="flex items-center gap-2 px-2 text-sm font-semibold text-violet-500">
                <Ruler class="h-4 w-4" />
                {{ t('item.section.physical') }}
            </legend>
            <div class="grid gap-3 pt-2 sm:grid-cols-2 lg:grid-cols-4">
                <NextInput
                    v-if="shows('weight')"
                    :label="t('item.weight')"
                    type="number"
                    step="0.0001"
                    v-model="form.weight"
                    :disabled="disabled"
                    :error="form.errors?.weight"
                />
                <template v-if="shows('dimensions')">
                    <NextInput
                        :label="t('item.length')"
                        type="number"
                        step="0.0001"
                        v-model="form.length"
                        :disabled="disabled"
                        :error="form.errors?.length"
                    />
                    <NextInput
                        :label="t('item.width')"
                        type="number"
                        step="0.0001"
                        v-model="form.width"
                        :disabled="disabled"
                        :error="form.errors?.width"
                    />
                    <NextInput
                        :label="t('item.height')"
                        type="number"
                        step="0.0001"
                        v-model="form.height"
                        :disabled="disabled"
                        :error="form.errors?.height"
                    />
                </template>
            </div>
        </fieldset>

        <!-- Sourcing & compliance -->
        <fieldset v-if="showCompliance" class="rounded-xl border border-border bg-muted/30 px-4 pb-4 pt-2">
            <legend class="flex items-center gap-2 px-2 text-sm font-semibold text-violet-500">
                <ShieldCheck class="h-4 w-4" />
                {{ t('item.section.compliance') }}
            </legend>
            <div class="grid gap-3 pt-2 sm:grid-cols-2 lg:grid-cols-3">
                <NextInput
                    v-if="shows('manufacturer')"
                    :label="t('item.manufacturer')"
                    v-model="form.manufacturer"
                    :disabled="disabled"
                    :error="form.errors?.manufacturer"
                />
                <NextInput
                    v-if="shows('model')"
                    :label="t('item.model')"
                    v-model="form.model"
                    :disabled="disabled"
                    :error="form.errors?.model"
                />
                <NextInput
                    v-if="shows('country_of_origin')"
                    :label="t('item.country_of_origin')"
                    v-model="form.country_of_origin"
                    :disabled="disabled"
                    :error="form.errors?.country_of_origin"
                />
                <NextInput
                    v-if="shows('hs_code')"
                    :label="t('item.hs_code')"
                    v-model="form.hs_code"
                    :disabled="disabled"
                    :error="form.errors?.hs_code"
                />
                <NextInput
                    v-if="shows('warranty_months')"
                    :label="t('item.warranty_months')"
                    type="number"
                    v-model="form.warranty_months"
                    :disabled="disabled"
                    :error="form.errors?.warranty_months"
                />
                <NextInput
                    v-if="shows('shelf_life_days')"
                    :label="t('item.shelf_life_days')"
                    type="number"
                    v-model="form.shelf_life_days"
                    :disabled="disabled"
                    :error="form.errors?.shelf_life_days"
                    :hint="t('item.hint.shelf_life_days')"
                />
                <NextInput
                    v-if="shows('min_shelf_life_percent')"
                    :label="t('item.min_shelf_life_percent')"
                    type="number"
                    v-model="form.min_shelf_life_percent"
                    :disabled="disabled"
                    :error="form.errors?.min_shelf_life_percent"
                    :hint="t('item.hint.min_shelf_life_percent')"
                />
                <NextSelect
                    v-if="shows('storage_zone')"
                    v-model="form.storage_zone"
                    :options="storageZoneOptions"
                    :reduce="(o) => o.id"
                    :floatingText="t('item.storage_zone')"
                    :placeholder="t('item.storage_zone')"
                    :error="form.errors?.storage_zone"
                />
            </div>

            <div
                v-if="anyOf(['requires_prescription', 'is_controlled'])"
                class="mt-3 grid gap-3 sm:grid-cols-2"
            >
                <label
                    v-if="shows('requires_prescription')"
                    class="flex cursor-pointer items-start gap-3 rounded-lg border p-3"
                >
                    <Checkbox
                        class="mt-0.5"
                        :checked="form.requires_prescription"
                        :disabled="disabled"
                        @update:checked="(v) => form.requires_prescription = v"
                    />
                    <p class="text-sm font-semibold">{{ t('item.requires_prescription') }}</p>
                </label>
                <label
                    v-if="shows('is_controlled')"
                    class="flex cursor-pointer items-start gap-3 rounded-lg border p-3"
                >
                    <Checkbox
                        class="mt-0.5"
                        :checked="form.is_controlled"
                        :disabled="disabled"
                        @update:checked="(v) => form.is_controlled = v"
                    />
                    <p class="text-sm font-semibold">{{ t('item.is_controlled') }}</p>
                </label>
            </div>
        </fieldset>

        <!-- Planning -->
        <fieldset v-if="showPlanning" class="rounded-xl border border-border bg-muted/30 px-4 pb-4 pt-2">
            <legend class="flex items-center gap-2 px-2 text-sm font-semibold text-violet-500">
                <PackageSearch class="h-4 w-4" />
                {{ t('item.section.stock_control') }}
            </legend>
            <div class="grid gap-3 pt-2 sm:grid-cols-2 lg:grid-cols-3">
                <NextInput
                    v-if="shows('reorder_quantity')"
                    :label="t('item.reorder_quantity')"
                    type="number"
                    step="0.0001"
                    v-model="form.reorder_quantity"
                    :disabled="disabled"
                    :error="form.errors?.reorder_quantity"
                />
                <NextInput
                    v-if="shows('lead_time_days')"
                    :label="t('item.lead_time_days')"
                    type="number"
                    v-model="form.lead_time_days"
                    :disabled="disabled"
                    :error="form.errors?.lead_time_days"
                    :hint="t('item.hint.lead_time_days')"
                />
                <NextSelect
                    v-if="shows('default_warehouse')"
                    v-model="form.default_warehouse_id"
                    :options="warehouses"
                    :reduce="(o) => o.id"
                    :floatingText="t('item.default_warehouse')"
                    :placeholder="t('item.default_warehouse')"
                    :error="form.errors?.default_warehouse_id"
                />
                <NextSelect
                    v-if="shows('costing_method')"
                    v-model="form.costing_method"
                    :options="costingMethods"
                    :reduce="(o) => o.id"
                    :floatingText="t('item.costing_method')"
                    :placeholder="t('item.costing_method')"
                    :error="form.errors?.costing_method"
                />
                <NextSelect
                    v-if="shows('pricing_method')"
                    v-model="form.pricing_method"
                    :options="pricingMethods"
                    :reduce="(o) => o.id"
                    :floatingText="t('item.pricing_method')"
                    :placeholder="t('item.pricing_method')"
                    :error="form.errors?.pricing_method"
                />
            </div>
        </fieldset>

        <!-- Behaviour -->
        <fieldset v-if="showBehaviour" class="rounded-xl border border-border bg-muted/30 px-4 pb-4 pt-2">
            <legend class="flex items-center gap-2 px-2 text-sm font-semibold text-violet-500">
                <SlidersHorizontal class="h-4 w-4" />
                {{ t('item.section.behaviour') }}
            </legend>
            <div class="grid gap-3 pt-2 sm:grid-cols-2 lg:grid-cols-3">
                <label
                    v-for="flag in behaviourFlags"
                    :key="flag.key"
                    class="flex cursor-pointer items-start gap-3 rounded-lg border p-3"
                >
                    <Checkbox
                        class="mt-0.5"
                        :checked="form[flag.key]"
                        :disabled="disabled"
                        @update:checked="(v) => form[flag.key] = v"
                    />
                    <div>
                        <p class="text-sm font-semibold">{{ t(`item.${flag.key}`) }}</p>
                        <p v-if="flag.hint" class="text-xs text-muted-foreground">
                            {{ t(`item.hint.${flag.hint}`) }}
                        </p>
                    </div>
                </label>
            </div>
        </fieldset>
    </div>
</template>
