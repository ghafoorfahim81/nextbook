<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { ref, computed, watch, nextTick } from 'vue'
import NextInput from '@/Components/next/NextInput.vue'
import { useForm } from '@inertiajs/vue3'
import NextSelect from "@/Components/next/NextSelect.vue";
import NextDatePicker from '@/Components/next/NextDatePicker.vue'
import ModuleHelpButton from '@/Components/ModuleHelpButton.vue'
import { Info, Trash2,AlertCircleIcon, CheckCircle2Icon, PopcornIcon } from 'lucide-vue-next'
import { Popover, PopoverTrigger, PopoverContent } from '@/Components/ui/popover'
import { useI18n } from 'vue-i18n'
import { toast } from 'vue-sonner';
import JsBarcode from 'jsbarcode'
import { Switch } from '@/Components/ui/switch'
import { Label } from '@/Components/ui/label'
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
    sizes: { type: [Array, Object], required: true },
    user_preferences: { type: Object, required: true },
    itemTypes: { type: [Array, Object], required: true },
    otherCurrentAssetsAccounts:{ type:Object, required: true},
    incomeAccounts:{ type:Object, required: true},
    costAccounts:{ type:Object, required: true},
}) 

console.log('this is the item', props.item.data)
const { t } = useI18n()
const warehouses = computed(() => props.warehouses?.data ?? props.warehouses ?? [])
const unitMeasures = computed(() => props.unitMeasures?.data ?? props.unitMeasures ?? [])
const categories = computed(() => props.categories?.data ?? props.categories ?? [])
const brands = computed(() => props.brands?.data ?? props.brands ?? [])
const sizes = computed(() => props.sizes?.data ?? props.sizes ?? [])
const itemTypes = computed(() => props.itemTypes?.data ?? props.itemTypes ?? [])
const otherCurrentAssetsAccounts = computed(() => props.otherCurrentAssetsAccounts?.data ?? props.otherCurrentAssetsAccounts ?? [])
const incomeAccounts = computed(() => props.incomeAccounts?.data ?? props.incomeAccounts ?? [])
const costAccounts = computed(() => props.costAccounts?.data ?? props.costAccounts ?? [])

const form = useForm({
    ...props.item.data,
    selected_unit_measure: props.item.data.unitMeasure,
    selected_category: props.item.data.category,
    selected_brand: props.item.data.brand,
    selected_size: props.item.data.size,
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
            status: o.status
        }))
        : [{ batch: '', expire_date: '', unit_price: 0, quantity: 0, warehouse_id: null, selected_warehouse: null, warehouse: null, status: null }],
})


// File handler
const onPhotoChange = (e) => {
    form.photo = e.target.files?.[0] ?? null
}

// Rows
const addRow = (index) => {
    if (index === form.openings.length - 1) {
        form.openings.push({ batch: '', expire_date: '', unit_price: 0, quantity: 0, warehouse_id: null, selected_warehouse: null, warehouse: null, status: null })
    }
}
const addOpeningRow = () => {
    form.openings.push({ batch: '', expire_date: '', unit_price: 0, quantity: 0, warehouse_id: null, selected_warehouse: null, warehouse: null, status: 'draft' })
}
const removeRow = (idx) => {
    if (form.openings.length > 1) form.openings.splice(idx, 1)
}

// Normalize numbers before submit
const normalize = () => {
    const toNum = v => (v === '' || v === null ? null : Number(v))
    form.minimum_stock = toNum(form.minimum_stock)
    form.maximum_stock = toNum(form.maximum_stock)
    form.purchase_price = toNum(form.purchase_price)
    form.cost = toNum(form.cost)
    form.sale_price = toNum(form.sale_price)
    form.margin_percentage = toNum(form.margin_percentage)
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

let salePriceTimeout = null
watch(
    () => form.sale_price,
    () => {
        if (salePriceTimeout) clearTimeout(salePriceTimeout)
        salePriceTimeout = setTimeout(() => {
            if (form.sale_price && form.purchase_price && form.sale_price < form.purchase_price) {
                toast.error(t('item.low_price_warning'), {
                    description: t('item.sale_price_less_than_purchase_price') || 'Sale price cannot be less than purchase price.',
                    class: 'bg-red-600',
                });
            }
        }, 2000)
    }
)

const disabled = ref(false);
watch(
    () => form.openings.map(o => [o.selected_warehouse, o.batch, o.expire_date].join('|')).join(';'),
    (newVal, oldVal) => {
        let foundDuplicate = false;
        form.openings.forEach((currentOpening, index) => {
            const { selected_warehouse, batch, expire_date } = currentOpening;
            if (selected_warehouse && batch && expire_date) {
                const duplicate = form.openings.some((o, i) =>
                    i !== index &&
                    o.warehouse_id === selected_warehouse.id &&
                    o.batch === batch &&
                    o.expire_date === expire_date &&
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
const visibleFields = computed(() => user_preferences.value.item_management.visible_fields ?? []).value
const specText = computed(() => user_preferences.value.item_management.spec_text ?? '')

const isBarcodePopoverOpen = ref(false)
const barcodeSvg = ref(null)

const generateBarcode = () => {
    const random = Math.floor(100000000 + Math.random() * 900000000)
    form.barcode = `ITM${random}`
}

const renderBarcode = async (retries = 4) => {
    if (!form.barcode) return

    await nextTick()

    // Popover content is mounted lazily, so SVG may not exist immediately.
    if (!barcodeSvg.value) {
        if (retries > 0) {
            requestAnimationFrame(() => {
                renderBarcode(retries - 1)
            })
        }
        return
    }

    JsBarcode(barcodeSvg.value, form.barcode, {
        format: "CODE128",
        width: 2,
        height: 60,
        displayValue: true,
        margin: 0,
    })
}

watch(
    () => form.barcode,
    () => {
        renderBarcode()
    },
    { immediate: true }
)

watch(
    () => isBarcodePopoverOpen.value,
    (open) => {
        if (open) renderBarcode()
    }
)
const showOpeningWarning = computed(() => {
    return props.item.data.openings.some(o => o.status === 'posted')
})
console.log('this is the showOpeningWarning', showOpeningWarning.value)
</script>
<template>
    <AppLayout :title="t('general.edit', { name: t('item.item') })">
        <form @submit.prevent="handleSubmit">
            <div class="mb-5 rounded-xl border p-4 shadow-sm border-primary relative">
                <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
                    {{ t('general.edit', { name: t('item.item') }) }}
                </div>
                <ModuleHelpButton module="inventory_item" />
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                    <NextInput :label="t('general.name')" v-model="form.name" :error="form.errors?.name" :placeholder="t('general.enter', { text: t('general.name') })" />
                    <NextInput :label="t('admin.currency.code')" v-model="form.code" :error="form.errors?.code" :placeholder="t('general.enter', { text: t('admin.currency.code') })" />
                    <NextInput v-if="visibleFields.generic_name" :label="t('item.generic_name')" v-model="form.generic_name" :error="form.errors?.generic_name" :placeholder="t('general.enter', { text: t('item.generic_name') })" />
                    <NextInput :label="t('item.packing')" v-if="visibleFields.packing" v-model="form.packing" :error="form.errors?.packing" :placeholder="t('general.enter', { text: t('item.packing') })" />
                    <NextInput :label="t('item.colors')" v-if="visibleFields.colors" v-model="form.colors" :error="form.errors?.colors" :placeholder="t('general.enter', { text: t('item.colors') })" />
                    <NextSelect
                        v-if="visibleFields.size"
                        v-model="form.selected_size"
                        :options="sizes"
                        @update:modelValue="(value) => handleSelectChange('size_id', value)"
                        label-key="name"
                        value-key="id"
                        id="size_id"
                        :floating-text="t('item.size')"
                        :searchable="true"
                        resource-type="sizes"
                        :search-fields="['name']"
                        :error="form.errors.size_id"
                    />
                    <NextInput :label="t('item.photo')" v-if="visibleFields.photo" type="file"  @input="onPhotoChange" :error="form.errors?.photo" :placeholder="t('general.enter', { text: t('item.photo') })" />
                    <NextSelect
                        v-model="form.selected_unit_measure"
                        :options="unitMeasures"
                        @update:modelValue="(value) => handleSelectChange('unit_measure_id', value)"
                        label-key="name"
                        value-key="id"
                        id="measure"
                        :floating-text="t('admin.unit_measure.unit_measure')"
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
                        :options="otherCurrentAssetsAccounts"
                        v-model="form.asset_account_id"
                        label-key="name"
                        value-key="id"
                        id="assets_account"
                        :floating-text="t('item.asset_account')"
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
                        :searchable="true"
                        resource-type="cost_accounts"
                        :search-fields="['name']"
                        :error="form.errors.cost_account_id"
                    />
                    <NextInput v-show="visibleFields.sku" :label="t('item.sku')" v-model="form.sku" :error="form.errors?.sku" :placeholder="t('general.enter', { text: t('item.sku') })" />
                    <NextSelect
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
                    <NextInput v-show="visibleFields.minimum_stock" :label="t('item.minimum_stock')" type="number" :placeholder="t('general.enter', { text: t('item.minimum_stock') })" v-model="form.minimum_stock" :error="form.errors?.minimum_stock" />
                    <NextInput v-show="visibleFields.maximum_stock" :label="t('item.maximum_stock')" type="number" :placeholder="t('general.enter', { text: t('item.maximum_stock') })" v-model="form.maximum_stock" :error="form.errors?.maximum_stock" />
                    <NextInput :label="t('item.purchase_price')" type="number" :placeholder="t('general.enter', { text: t('item.purchase_price') })" v-model="form.purchase_price" :error="form.errors?.purchase_price" />
                    <NextInput :label="t('item.cost')" type="number" v-model="form.cost" :error="form.errors?.cost" />
                    <div class="flex flex-row gap-4 w-full">
                        <div class="flex-1">
                            <NextInput 
                                :label="t('item.sale_price')" 
                                type="number" 
                                :placeholder="t('general.enter', { text: t('item.sale_price') })" 
                                v-model="form.sale_price" 
                                :error="form.errors?.sale_price" 
                            />
                        </div>
                        <div class="flex-1 flex items-center justify-between border rounded-md">
                            <div>
                                <NextInput 
                                :label="t('item.margin_percentage')" 
                                type="number" 
                                :placeholder="t('general.enter', { text: t('item.margin_percentage') })" 
                                v-model="form.margin_percentage" 
                                :error="form.errors?.margin_percentage" 
                            /> 
                            </div>
                        <div class="flex items-center justify-end px-2">
                            <Popover v-model:open="isMarginPercentagePopoverOpen">
                                <PopoverTrigger as-child>
                                  <Button variant="outline">
                                    <Info class="w-4 h-4 text-primary hover:cursor-pointer" />
                                  </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-80">
                                  <div class="grid gap-4">
                                    <p>{{ t('item.margin_percentage_description') || 'Margin percentage is the percentage of the sale price that is profit.' }}</p>
                                  </div>
                                </PopoverContent>
                              </Popover>
                        </div>
                        </div>
                    </div>
                    <NextInput v-show="visibleFields.rate_a" :label="t('item.rate_a')" type="number" :placeholder="t('general.enter', { text: t('item.rate_a') })" v-model="form.rate_a" :error="form.errors?.rate_a" />
                    <NextInput v-show="visibleFields.rate_b" :label="t('item.rate_b')" type="number" :placeholder="t('general.enter', { text: t('item.rate_b') })" v-model="form.rate_b" :error="form.errors?.rate_b" />
                    <NextInput v-show="visibleFields.rate_c" :label="t('item.rate_c')" type="number" :placeholder="t('general.enter', { text: t('item.rate_c') })" v-model="form.rate_c" :error="form.errors?.rate_c" />
                    <div v-show="visibleFields.barcode" class="flex items-center justify-between border rounded-md">
                        <div class="w-full">
                            <NextInput
                            :label="t('item.barcode')"
                            v-model="form.barcode"
                            readonly="true"
                            :placeholder="t('general.enter', { text: t('item.barcode') })"
                            :error="form.errors?.barcode"
                        />
                        </div>
                        <div class="flex items-center justify-end px-2">
                            <Popover v-model:open="isBarcodePopoverOpen">
                                <PopoverTrigger as-child>
                                  <Button variant="outline">
                                    <Info class="w-4 h-4 text-primary hover:cursor-pointer" />
                                  </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-80">
                                  <div class="grid gap-4">
                                    <svg ref="barcodeSvg" class="w-full h-[80px]"></svg>
                                    <button
                                    type="button"
                                    v-if="!form.barcode"
                                    class="text-sm text-primary underline mt-2"
                                    @click="generateBarcode"
                                >
                                    {{ t('item.generate_new_barcode') || 'Generate New Barcode' }}
                                </button>
                                  </div>
                                </PopoverContent>
                              </Popover>
                        </div>
                    </div>
                    <NextInput v-show="visibleFields.rack_no" :label="t('item.rack_no')" v-model="form.rack_no" :placeholder="t('general.enter', { text: t('item.rack_no') })" :error="form.errors?.rack_no" />
                    <NextInput v-show="visibleFields.fast_search" :label="t('item.fast_search')" v-model="form.fast_search" :placeholder="t('general.enter', { text: t('item.fast_search') })" :error="form.errors?.fast_search" />
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 rtl:text-right">
                        <div v-show="visibleFields.is_batch_tracked" class="flex items-center gap-2">
                            <Label class="text-base">{{ t('item.is_batch_tracked') }}</Label>
                            <Switch
                                disabled="true"
                                :model-value="form.is_batch_tracked"
                                @update:model-value="(v) => form.is_batch_tracked = v"
                            />
                        </div>
                        <div v-show="visibleFields.is_expiry_tracked" class="flex items-center gap-2">
                            <Label class="text-base">{{ t('item.is_expiry_tracked') }}</Label>
                            <Switch
                                disabled="true"
                                :model-value="form.is_expiry_tracked"
                                @update:model-value="(v) => form.is_expiry_tracked = v"
                            />
                        </div>
                    </div>
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
                        <div class="flex items-center justify-between" >
                            <span class="font-bold">{{ t('item.opening') }}</span>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary px-3"
                                @click="addOpeningRow"
                            >
                                + {{ t('general.add', { title: t('item.opening') }) }}
                            </button>
                        </div>
                        <div
                            v-for="(opening, index) in form.openings"
                            :key="index" 
                            class="mt-3 grid grid-cols-1 md:grid-cols-6 gap-4 items-start"
                            :class="{ 'opacity-50': opening.status === 'posted' }"
                        > 
                            <NextInput :label="t('item.batch')" v-show="form.is_batch_tracked" v-model="opening.batch" :error="form.errors?.[`openings.${index}.batch`]" :disabled="opening.status === 'posted'" />
                            <NextDatePicker v-show="form.is_expiry_tracked" :disabled="opening.status === 'posted'" v-model="opening.expire_date" :error="form.errors?.[`openings.${index}.expire_date`]" :placeholder="t('general.enter', { text: t('item.expire_date') })" />
                            <NextInput :label="t('general.quantity')" :disabled="opening.status === 'posted'" type="number" v-model="opening.quantity" :error="form.errors?.[`openings.${index}.quantity`]" />
                            <NextInput :label="t('general.unit_price')" :disabled="opening.status === 'posted'" type="number" v-model="opening.unit_price" :error="form.errors?.[`openings.${index}.unit_price`]" />
                            <NextSelect
                                v-model="opening.selected_warehouse"
                                :disabled="opening.status === 'posted'"
                                @update:modelValue="(value) => handleOpeningSelectChange(index, value)"
                                :options="warehouses"
                                label-key="name"
                                value-key="id"
                                :reduce="warehouse => warehouse"
                                id="warehouse"
                                :floating-text="t('admin.warehouse.warehouse')"
                                :error="form.errors[`openings.${index}.warehouse_id`]"
                                :searchable="true"
                                resource-type="warehouses"
                                :search-fields="['name', 'address']"
                            />
                            <div  class="mt-2" v-if="form.openings.length > 1 && opening.status !== 'posted' ">
                                <button
                                    type="button"
                                    :disabled="opening.status === 'posted'"
                                    class="btn btn-sm btn-outline-danger px-3"
                                    @click="removeRow(index)"
                                >
                                    <Trash2 class="w-4 h-4 text-fuchsia-800 hover:cursor-pointer" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
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
