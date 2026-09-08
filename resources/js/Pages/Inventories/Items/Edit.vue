<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { useFormGuard } from '@/composables/useFormGuard'
import { ref, computed, watch, reactive, toRef } from 'vue'
import NextInput from '@/Components/next/NextInput.vue'
import { useForm, router } from '@inertiajs/vue3'
import AttachmentUploader from '@/Components/AttachmentUploader.vue'
import NextSelect from "@/Components/next/NextSelect.vue";
import NextDatePicker from '@/Components/next/NextDatePicker.vue'
import FormPageToolbar from '@/Components/FormPageToolbar.vue'
import FormPreferencesPanel from '@/Components/FormPreferencesPanel.vue'
import VariantEditor from '@/Components/inventory/VariantEditor.vue'
import ItemDetailFields from '@/Components/inventory/ItemDetailFields.vue'
import { useBusinessProfile } from '@/composables/useBusinessProfile'
import { Trash2, AlertCircleIcon } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { toast } from 'vue-sonner';
import { Checkbox } from '@/Components/ui/checkbox'
import {
  Alert,
  AlertDescription,
  AlertTitle,
} from '@/Components/ui/alert'
const props = defineProps({
    item: { type: Object, required: true },
    warehouses: { type: [Array, Object], required: true },
    unitMeasures: { type: [Array, Object], required: true },
    categories: { type: [Array, Object], required: true },
    brands: { type: [Array, Object], required: true },
    user_preferences: { type: Object, required: true },
    itemTypes: { type: [Array, Object], required: true },
    otherCurrentAssetsAccounts:{ type:Object, required: true},
    incomeAccounts:{ type:Object, required: true},
    costAccounts:{ type:Object, required: true},
    costingMethods: { type: [Array, Object], default: () => [] },
    pricingMethods: { type: [Array, Object], default: () => [] },
    businessProfile: { type: Object, default: null },
    companyCostingMethod: { type: String, default: null },
})

const { t } = useI18n()
const warehouses = computed(() => props.warehouses?.data ?? props.warehouses ?? [])
const unitMeasures = computed(() => props.unitMeasures?.data ?? props.unitMeasures ?? [])
const categories = computed(() => props.categories?.data ?? props.categories ?? [])
const brands = computed(() => props.brands?.data ?? props.brands ?? [])
const itemTypes = computed(() => props.itemTypes?.data ?? props.itemTypes ?? [])
const costingMethods = computed(() => props.costingMethods?.data ?? props.costingMethods ?? [])
const pricingMethods = computed(() => props.pricingMethods?.data ?? props.pricingMethods ?? [])
const otherCurrentAssetsAccounts = computed(() => props.otherCurrentAssetsAccounts?.data ?? props.otherCurrentAssetsAccounts ?? [])
const incomeAccounts = computed(() => props.incomeAccounts?.data ?? props.incomeAccounts ?? [])
const costAccounts = computed(() => props.costAccounts?.data ?? props.costAccounts ?? [])

// A fresh opening is never locked — the lock only exists once stock has been
// issued against the layer, which cannot have happened before it is saved.
const blankOpening = () => ({
    batch: '',
    expire_date: '',
    unit_price: 0,
    quantity: 0,
    warehouse_id: null,
    selected_warehouse: null,
    warehouse: null,
    variant_index: null,
    status: 'draft',
    is_locked: false,
    lock_reason: null,
})

// Variants come back from ItemResource; fall back to a single default row
// for items created before the variant migration ran.
const initialVariants = props.item.data.variants?.length
    ? props.item.data.variants.map((v, index) => ({
        id: v.id,
        attributes: { ...(v.attributes ?? {}) },
        name: v.name ?? '',
        sku: v.sku ?? '',
        barcode: v.barcode ?? '',
        margin: '',
        sale_price: v.sale_price ?? '',
        purchase_price: v.purchase_price ?? '',
        minimum_stock: v.minimum_stock ?? '',
        maximum_stock: v.maximum_stock ?? '',
        is_default: Boolean(v.is_default),
        is_active: v.is_active !== false,
        sort_order: v.sort_order ?? index,
    }))
    : [{
        id: null,
        attributes: {},
        name: '',
        sku: props.item.data.sku ?? '',
        barcode: props.item.data.barcode ?? '',
        margin: '',
        sale_price: props.item.data.sale_price ?? '',
        purchase_price: props.item.data.purchase_price ?? '',
        minimum_stock: props.item.data.minimum_stock ?? '',
        maximum_stock: props.item.data.maximum_stock ?? '',
        is_default: true,
        is_active: true,
        sort_order: 0,
    }]

// An existing opening carries variant_id; map it back to the variant's row
// index so the picker shows it. -1 (not found) becomes null.
const variantIndexFor = (variantId) => {
    if (!variantId) return null
    const idx = initialVariants.findIndex(v => v.id === variantId)
    // String to match variantOptions ids (NextSelect treats numeric 0 as empty).
    return idx === -1 ? null : String(idx)
}

const form = useForm({
    ...props.item.data,
    selected_unit_measure: props.item.data.unitMeasure,
    selected_category: props.item.data.category,
    selected_brand: props.item.data.brand,
    item_type: props.item.data.item_type_id ?? props.item.data.item_type ?? null,
    photo: null,
    openings: props.item.data.openings?.length
        ? props.item.data.openings.map(o => ({
            id: o.id,
            batch: o.batch,
            expire_date: o.expire_date,
            unit_price: o.unit_price,
            quantity: o.quantity,
            warehouse_id: o.warehouse_id,
            selected_warehouse: o.warehouse,
            variant_index: variantIndexFor(o.variant_id),
            status: o.status,
            is_locked: o.is_locked,
            lock_reason: o.lock_reason,
        }))
        : [blankOpening()],

    variants: initialVariants,

    attachments: [],
})

// Variant choices for the per-opening variant picker, labelled by their
// attribute values. The id is the row index as a string — NextSelect treats
// a numeric 0 as "no selection".
const variantOptions = computed(() => form.variants.map((v, i) => ({
    id: String(i),
    name: Object.values(v.attributes ?? {}).filter(Boolean).join(' / ')
        || v.sku
        || t('item.default_variant'),
})))

// Picking a variant fills the opening's unit price with that variant's
// purchase price — a suggestion the user can still override. Opening unit
// price is what StockService costs the layer at and what avg_cost is built
// from, so it stays a real editable field.
const onOpeningVariantChange = (index, value) => {
    form.openings[index].variant_index = value

    if (value === null || value === undefined || value === '') return

    const price = Number(form.variants[Number(value)]?.purchase_price)
    if (Number.isFinite(price) && price > 0) {
        form.openings[index].unit_price = price
    }
}

const existingAttachments = ref(props.item.data.attachments || [])
const removeExistingAttachment = (id) => {
  router.delete(route('attachments.destroy', id), {
    preserveScroll: true,
    onSuccess: () => { existingAttachments.value = existingAttachments.value.filter(a => a.id !== id) },
  })
}


// File handler
const onPhotoChange = (e) => {
    form.photo = e.target.files?.[0] ?? null
}

// Rows
const addRow = (index) => {
    if (index === form.openings.length - 1) {
        form.openings.push(blankOpening())
    }
}
const addOpeningRow = () => {
    form.openings.push(blankOpening())
}
const removeRow = (idx) => {
    if (form.openings[idx]?.is_locked) return
    if (form.openings.length > 1) form.openings.splice(idx, 1)
}

// Normalize numbers before submit
const normalize = () => {
    const toNum = v => (v === '' || v === null ? null : Number(v))
    form.rate_a = toNum(form.rate_a)
    form.rate_b = toNum(form.rate_b)
    form.rate_c = toNum(form.rate_c)
    form.openings = form.openings.map(o => ({
        ...o,
        quantity: toNum(o.quantity),
        unit_price: toNum(o.unit_price),
        expire_date: o.expire_date || null,
        batch: o.batch || null,
        warehouse_id: o.warehouse_id ?? (o.selected_warehouse ? o.selected_warehouse.id : null),
        variant_index: (o.variant_index === null || o.variant_index === undefined || o.variant_index === '')
            ? null
            : Number(o.variant_index),
    }))
}

const handleSubmit = () => {
    normalize()
    form.patch(route('items.update', form.id), {
        onSuccess: () => {
            form.reset()
            toast.success(t('general.success'),{
                description: t('general.update_success', { name: t('item.item') }),
                class: 'bg-green-600',
            });
        },
    })
}
const handleSelectChange = (field, value) => {
    form[field] = value;
};

const disabled = ref(false);
watch(
    () => form.openings.map(o => [o.selected_warehouse, o.batch, o.expire_date, o.variant_index].join('|')).join(';'),
    (newVal, oldVal) => {
        let foundDuplicate = false;
        form.openings.forEach((currentOpening, index) => {
            const { selected_warehouse, batch, expire_date, variant_index } = currentOpening;
            if (selected_warehouse && batch && expire_date) {
                const duplicate = form.openings.some((o, i) =>
                    i !== index &&
                    o.warehouse_id === selected_warehouse.id &&
                    o.batch === batch &&
                    o.expire_date === expire_date &&
                    o.variant_index === variant_index &&
                    o.warehouse_id && o.batch && o.expire_date
                );
                if (duplicate && !foundDuplicate) {
                    foundDuplicate = true;
                    disabled.value = true;
                    toast.error(t('general.duplicate_found'), {
                        description: t('item.duplicate_warehouse_batch_expiry') || 'This warehouse with the same batch and expiry already exists.',
                        class: 'bg-red-600',
                    });
                }
            }
        });
        if (!foundDuplicate) {
            disabled.value = false;
        }
    },
    { deep: true }
)
const handleOpeningSelectChange = (index, value) => {
    form.openings[index].selected_warehouse = value;
    form.openings[index].warehouse_id = value.id ? value.id : null;
};

const user_preferences = computed(() => props.user_preferences?.data ?? props.user_preferences ?? [])
// Single reactive copy of the item-management preferences so the panel and form stay in sync live.
const itemPrefs = reactive(JSON.parse(JSON.stringify(user_preferences.value?.item_management ?? {})))
if (!itemPrefs.visible_fields || typeof itemPrefs.visible_fields !== 'object') itemPrefs.visible_fields = {}

// Trade profile first, then the owner's per-field overrides on top.
const { fields: profileFields, showsSection, specLabel } = useBusinessProfile(toRef(props, 'businessProfile'))
const visibleFields = computed(() => ({ ...profileFields.value, ...itemPrefs.visible_fields }))
const specText = computed(() => itemPrefs.spec_text || t(`item.spec.${specLabel.value}`))
const showPreferencesPanel = ref(false)

// Locked openings have had stock issued against them, so the figure is already
// carried into COGS and the GL and can no longer be restated.
const hasLockedOpening = computed(() => form.openings.some(o => o.is_locked))
const showOpeningWarning = hasLockedOpening

useFormGuard(form)
</script>
<template>
    <AppLayout :title="t('general.edit', { name: t('item.item') })">
        <FormPageToolbar
            back-route="items.index"
            module="inventory_item"
            :show-preferences="true"
            @preferences="showPreferencesPanel = true"
        />
        <FormPreferencesPanel module="item"
            v-model:open="showPreferencesPanel"
            pref-group="item_management"
            :prefs="itemPrefs"
            :title="t('preferences.tabs.item_management')"
        />
        <form @submit.prevent="handleSubmit()">
            <div class="mb-5 rounded-xl border p-4 shadow-sm border-primary relative">
                <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
                    {{ t('general.edit', { name: t('item.item') }) }}
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-x-4 gap-y-5 mt-3 items-start">
                    <NextInput is-required :label="t('general.name')" v-model="form.name" :error="form.errors?.name" :placeholder="t('general.enter', { text: t('general.name') })" />
                    <NextInput is-required :label="t('admin.currency.code')" v-model="form.code" :error="form.errors?.code" :placeholder="t('general.enter', { text: t('admin.currency.code') })" />
                    <NextInput v-if="visibleFields.generic_name" :label="t('item.generic_name')" v-model="form.generic_name" :error="form.errors?.generic_name" :placeholder="t('general.enter', { text: t('item.generic_name') })" />
                    <NextInput :label="t('item.packing')" v-if="visibleFields.packing" v-model="form.packing" :error="form.errors?.packing" :placeholder="t('general.enter', { text: t('item.packing') })" />
                    <NextInput :label="t('item.description')" v-if="visibleFields.description" v-model="form.description" :error="form.errors?.description" :placeholder="t('general.enter', { text: t('item.description') })" />
                    <NextInput :label="t('item.photo')" v-if="visibleFields.photo" type="file"  @input="onPhotoChange" :error="form.errors?.photo" :placeholder="t('general.enter', { text: t('item.photo') })" />
                    <NextSelect
                        v-show="visibleFields.unit_measure"
                        v-model="form.selected_unit_measure"
                        :options="unitMeasures"
                        @update:modelValue="(value) => handleSelectChange('unit_measure_id', value)"
                        label-key="name"
                        value-key="id"
                        :disabled="hasLockedOpening"
                        id="measure"
                        :floating-text="t('admin.unit_measure.unit_measure')"
                        is-required
                        :searchable="true"
                        resource-type="unit_measures"
                        :search-fields="['name','unit','symbol']"
                        :error="form.errors.unit_measure_id"
                    />
                    <NextSelect
                        v-model="form.item_type"
                        :options="itemTypes"
                        label-key="name"
                        value-key="id"
                        id="item_type"
                        :floating-text="t('item.item_type')"
                    />
                    <NextSelect
                        v-show="visibleFields.category"
                        v-model="form.selected_category"
                        @update:modelValue="(value) => handleSelectChange('category_id', value)"
                        :options="categories"
                        label-key="name"
                        value-key="id"
                        id="category"
                        :floating-text="t('admin.category.category')"
                        :searchable="true"
                        resource-type="categories"
                        :search-fields="['name']"
                        :error="form.errors.category_id"
                    />
                     <NextSelect
                        v-if="visibleFields.brand"
                        v-model="form.selected_brand"
                        @update:modelValue="(value) => handleSelectChange('brand_id', value)"
                        :options="brands"
                        label-key="name"
                        value-key="id"
                        id="brand"
                        :floating-text="t('admin.brand.brand')"
                        :searchable="true"
                        resource-type="brands"
                        :search-fields="['name', 'legal_name', 'registration_number', 'email', 'phone', 'website', 'industry', 'type', 'city', 'country']"
                        :error="form.errors.brand_id"
                    />
                    <NextSelect
                        :options="otherCurrentAssetsAccounts"
                        v-model="form.asset_account_id"
                        label-key="name"
                        value-key="id"
                        id="assets_account"
                        :floating-text="t('item.asset_account')"
                        is-required
                        :searchable="true"
                        resource-type="assets_accounts"
                        :search-fields="['name']"
                        :error="form.errors.asset_account_id"
                    />
                    <NextSelect
                        :options="incomeAccounts"
                        v-model="form.income_account_id"
                        label-key="name"
                        value-key="id"
                        id="income_account"
                        :floating-text="t('item.income_account')"
                        is-required
                        :searchable="true"
                        resource-type="income_accounts"
                        :search-fields="['name']"
                        :error="form.errors.income_account_id"
                    />
                    <NextSelect
                        :options="costAccounts"
                        v-model="form.cost_account_id"
                        label-key="name"
                        value-key="id"
                        id="cost_account"
                        :floating-text="t('item.cost_account')"
                        is-required
                        :searchable="true"
                        resource-type="cost_accounts"
                        :search-fields="['name']"
                        :error="form.errors.cost_account_id"
                    />

                    <NextInput v-show="visibleFields.rate_a" :label="t('item.rate_a')" type="number" :placeholder="t('general.enter', { text: t('item.rate_a') })" v-model="form.rate_a" :error="form.errors?.rate_a" />
                    <NextInput v-show="visibleFields.rate_b" :label="t('item.rate_b')" type="number" :placeholder="t('general.enter', { text: t('item.rate_b') })" v-model="form.rate_b" :error="form.errors?.rate_b" />
                    <NextInput v-show="visibleFields.rate_c" :label="t('item.rate_c')" type="number" :placeholder="t('general.enter', { text: t('item.rate_c') })" v-model="form.rate_c" :error="form.errors?.rate_c" />
                    <NextInput v-show="visibleFields.rack_no" :label="t('item.rack_no')" v-model="form.rack_no" :placeholder="t('general.enter', { text: t('item.rack_no') })" :error="form.errors?.rack_no" />
                    <NextInput v-show="visibleFields.fast_search" :label="t('item.fast_search')" v-model="form.fast_search" :placeholder="t('general.enter', { text: t('item.fast_search') })" :error="form.errors?.fast_search" />
                    <div class="md:col-span-3 grid grid-cols-1 sm:grid-cols-2 gap-3 rtl:text-right">
                        <label v-show="visibleFields.is_batch_tracked" class="flex items-start gap-3 rounded-lg border p-3 opacity-70 cursor-not-allowed">
                            <Checkbox class="mt-0.5" disabled :checked="form.is_batch_tracked" @update:checked="(v) => form.is_batch_tracked = v" />
                            <div>
                                <p class="font-semibold text-sm">{{ t('item.is_batch_tracked') }}</p>
                                <p class="text-sm text-muted-foreground">{{ t('item.batch_warning') }}</p>
                            </div>
                        </label>
                        <label v-show="visibleFields.is_expiry_tracked" class="flex items-start gap-3 rounded-lg border p-3 opacity-70 cursor-not-allowed">
                            <Checkbox class="mt-0.5" disabled :checked="form.is_expiry_tracked" @update:checked="(v) => form.is_expiry_tracked = v" />
                            <div>
                                <p class="font-semibold text-sm">{{ t('item.is_expiry_tracked') }}</p>
                                <p class="text-sm text-muted-foreground">{{ t('item.expiry_warning') }}</p>
                            </div>
                        </label>
                        <label v-show="visibleFields.is_serial_tracked" class="flex items-start gap-3 rounded-lg border p-3 opacity-70 cursor-not-allowed">
                            <Checkbox class="mt-0.5" disabled :checked="form.is_serial_tracked" @update:checked="(v) => form.is_serial_tracked = v" />
                            <div>
                                <p class="font-semibold text-sm">{{ t('item.is_serial_tracked') }}</p>
                                <p class="text-sm text-muted-foreground">{{ t('item.serial_warning') }}</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="mt-4">
                    <VariantEditor
                        v-model="form.variants"
                        :errors="form.errors"
                        :enabled="showsSection('variants')"
                    />
                </div>

                <div class="mt-4">
                    <ItemDetailFields
                        :form="form"
                        :visible-fields="visibleFields"
                        :warehouses="warehouses"
                        :costing-methods="costingMethods"
                        :pricing-methods="pricingMethods"
                        :company-costing-method="companyCostingMethod"
                    />
                </div>
                <div class="mt-2" v-if="showOpeningWarning">
                    <div class="w-full max-w-md">
                        <Alert variant="destructive">
                            <div class="flex items-center gap-2">
                                <AlertCircleIcon class="w-4 h-4 text-red-400" />
                                <AlertTitle class="text-red-500">{{ t('item.opening_lock_warning') }}</AlertTitle>
                            </div>
                            <AlertDescription>
                                <p class="text-red-500">{{ t('item.opening_lock_warning_description') }}</p>
                            </AlertDescription>
                        </Alert>
                    </div>
                </div>
                <div class="md:col-span-3 mt-4">
                    <div class="pt-2">
                        <div class="flex items-center justify-between">
                            <span class="font-bold">{{ t('item.opening') }}</span>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary px-3"
                                @click="addOpeningRow"
                            >
                                + {{ t('general.add', { title: t('item.opening') }) }}
                            </button>
                        </div>
                        <div class="rounded-md border border-primary overflow-hidden overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-primary text-white h-9">
                                        <th v-show="showsSection('variants')" class="py-2 px-3 text-start font-medium whitespace-nowrap">{{ t('item.variant') }}</th>
                                        <th v-show="form.is_batch_tracked" class="py-2 px-3 text-start font-medium whitespace-nowrap">{{ specText || t('item.batch') }}</th>
                                        <th v-show="form.is_expiry_tracked" class="py-2 px-3 text-start font-medium whitespace-nowrap">{{ t('item.expire_date') }}</th>
                                        <th class="py-2 px-3 text-start font-medium whitespace-nowrap">{{ t('general.quantity') }}</th>
                                        <th class="py-2 px-3 text-start font-medium whitespace-nowrap">{{ t('general.unit_price') }}</th>
                                        <th class="py-2 px-3 text-start font-medium whitespace-nowrap">{{ t('admin.warehouse.warehouse') }}</th>
                                        <th class="py-2 px-3 text-start font-medium whitespace-nowrap w-10"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(opening, index) in form.openings"
                                        :key="index"
                                        class="border-t border-border hover:bg-muted/50 align-top"
                                        :class="{ 'opacity-50': opening.is_locked }"
                                        :title="opening.is_locked ? t('item.opening_locked_cannot_update') : null"
                                    >
                                        <td v-show="showsSection('variants')" class="p-2 min-w-[170px]">
                                            <NextSelect
                                                :model-value="opening.variant_index"
                                                @update:modelValue="(value) => onOpeningVariantChange(index, value)"
                                                :options="variantOptions"
                                                label-key="name"
                                                value-key="id"
                                                :reduce="o => o.id"
                                                :id="`opening_variant_${index}`"
                                                :disabled="opening.is_locked"
                                                :error="form.errors?.[`openings.${index}.variant_index`]"
                                                :append-to-body="true"
                                            />
                                        </td>
                                        <td v-show="form.is_batch_tracked" class="p-2 min-w-[140px]">
                                            <NextInput label="" v-model="opening.batch" :error="form.errors?.[`openings.${index}.batch`]" :disabled="opening.is_locked" />
                                        </td>
                                        <td v-show="form.is_expiry_tracked" class="p-2 min-w-[160px]">
                                            <NextDatePicker :disabled="opening.is_locked" v-model="opening.expire_date" :lock-future-dates="false" :error="form.errors?.[`openings.${index}.expire_date`]" :placeholder="t('general.enter', { text: t('item.expire_date') })" />
                                        </td>
                                        <td class="p-2 min-w-[110px]">
                                            <NextInput label="" :disabled="opening.is_locked" type="number" v-model="opening.quantity" :error="form.errors?.[`openings.${index}.quantity`]" />
                                        </td>
                                        <td class="p-2 min-w-[110px]">
                                            <NextInput label="" :disabled="opening.is_locked" type="number" v-model="opening.unit_price" :error="form.errors?.[`openings.${index}.unit_price`]" />
                                        </td>
                                        <td class="p-2 min-w-[180px]">
                                            <NextSelect
                                                v-model="opening.selected_warehouse"
                                                @update:modelValue="(value) => handleOpeningSelectChange(index, value)"
                                                :options="warehouses"
                                                label-key="name"
                                                value-key="id"
                                                :reduce="warehouse => warehouse"
                                                :id="`warehouse_${index}`"
                                                :disabled="opening.is_locked"
                                                :error="form.errors[`openings.${index}.warehouse_id`]"
                                                :searchable="true"
                                                resource-type="warehouses"
                                                :search-fields="['name', 'address']"
                                                :append-to-body="true"
                                            />
                                        </td>
                                        <td class="p-2 text-center">
                                            <button
                                                type="button"
                                                v-if="form.openings.length > 1 && !opening.is_locked"
                                                class="btn btn-sm btn-outline-danger px-2"
                                                @click="removeRow(index)"
                                            >
                                                <Trash2 class="w-4 h-4 text-fuchsia-800 hover:cursor-pointer" />
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-if="form.errors?.openings" class="mt-2 text-sm text-red-500">{{ form.errors.openings }}</p>
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <AttachmentUploader v-model="form.attachments" :existing="existingAttachments" :label="t('general.attachments')" :error="form.errors['attachments.0']" @remove-existing="removeExistingAttachment" />
            </div>
            <progress v-if="form.progress" :value="form.progress.percentage" max="100">
            {{ form.progress.percentage }}%
            </progress>
            <div class="mt-4 flex gap-2">
                <button type="submit" class="btn btn-primary px-4 py-2 rounded-md bg-primary text-white" :disabled="disabled">{{ t('general.update') }}</button>
                <button type="button" class="btn px-4 py-2 rounded-md border" @click="() => $inertia.visit('/items')">{{ t('general.cancel') }}</button>
            </div>
        </form>
    </AppLayout>
</template>
