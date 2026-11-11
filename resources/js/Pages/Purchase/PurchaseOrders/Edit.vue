<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { useFormGuard } from '@/composables/useFormGuard'
import { ref, watch, computed, reactive, onMounted, onUnmounted } from 'vue';
import axios from 'axios'
import { useForm, usePage } from '@inertiajs/vue3';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import DiscountField from '@/Components/next/DiscountField.vue';
import NextDate from '@/Components/next/NextDatePicker.vue'
import SubmitButtons from '@/Components/SubmitButtons.vue';
import FormPageToolbar from '@/Components/FormPageToolbar.vue'
import FormPreferencesPanel from '@/Components/FormPreferencesPanel.vue'
import { Trash2, ScanBarcode } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { useToast } from '@/Components/ui/toast/use-toast'
import { pickDefaultVariant, repriceRow, linePriceToHome, repriceRowsForRate, priceInLineUnit } from '@/composables/useVariantLine'
import { toDocumentCurrency } from '@/utils/currency'
import VariantCell from '@/Components/inventory/VariantCell.vue'
import { useSaveShortcut } from '@/composables/useSaveShortcut'
import { useLineEnterNavigation } from '@/composables/useLineEnterNavigation'
import { variantStockFigure, stockInLineUnit } from '@/composables/useVariantLine'
import BarcodeSearchInput from '@/Components/next/BarcodeSearchInput.vue'

const { t } = useI18n();
const { toast } = useToast()
const page = usePage()

const props = defineProps({
    purchaseOrder: { type: Object, required: true },
})

const purchaseOrderData = computed(() => props.purchaseOrder?.data ?? props.purchaseOrder ?? {})

const currencies = computed(() => page.props.currencies ?? { data: [] })
const warehouses = computed(() => page.props.warehouses ?? { data: [] })
const unitMeasures = computed(() => page.props.unitMeasures ?? { data: [] })
const decimalPlaces = Number(page.props.auth?.user?.preferences?.appearance?.decimal_places ?? 2)

const user_preferences = computed(() => page.props.user_preferences?.data ?? page.props.user_preferences ?? {})
const purchasePrefs = reactive(JSON.parse(JSON.stringify(user_preferences.value?.purchase_order ?? {})))
if (!purchasePrefs.general_fields || typeof purchasePrefs.general_fields !== 'object') purchasePrefs.general_fields = {}
if (!purchasePrefs.item_columns || typeof purchasePrefs.item_columns !== 'object') purchasePrefs.item_columns = {}
const general_fields = purchasePrefs.general_fields
const localColumns = purchasePrefs.item_columns
const showPreferencesPanel = ref(false)

const findById = (list, id) => (list || []).find((entry) => entry.id === id) ?? null

const buildInitialRows = () => (purchaseOrderData.value.item_list || []).map((item) => ({
    item_id: item.item_id,
    selected_item: { id: item.item_id, name: item.item_name },
    quantity: item.quantity,
    unit_measure_id: item.unit_measure_id,
    selected_measure: findById(unitMeasures.value?.data, item.unit_measure_id) || { id: item.unit_measure_id, name: '' },
    available_measures: [],
    batch: item.batch || '',
    expire_date: item.expire_date || '',
    unit_price: item.unit_price,
    // Home currency: the saved price is in the document's own.
    base_unit_price: (Number(item.unit_price) || 0) * (Number(purchaseOrderData.value.rate) || 1),
    // A saved price was agreed when the document was written.
    price_touched: true,
    free: item.free || '',
    discount: item.discount || '',
    variant_id: item.variant_id || null,
    selected_variant: item.variant || null,
    item_variants: item.item?.item_variants || [],
}))

const form = useForm({
    number: purchaseOrderData.value.number,
    supplier_id: purchaseOrderData.value.supplier_id,
    selected_ledger: purchaseOrderData.value.supplier ? { id: purchaseOrderData.value.supplier_id, name: purchaseOrderData.value.supplier_name } : '',
    date: purchaseOrderData.value.date || '',
    delivery_date: purchaseOrderData.value.delivery_date || '',
    currency_id: purchaseOrderData.value.currency_id || '',
    selected_currency: findById(currencies.value?.data, purchaseOrderData.value.currency_id) || '',
    rate: purchaseOrderData.value.rate || '',
    warehouse_id: purchaseOrderData.value.warehouse_id || '',
    selected_warehouse: findById(warehouses.value?.data, purchaseOrderData.value.warehouse_id) || '',
    discount: purchaseOrderData.value.discount || '',
    discount_type: purchaseOrderData.value.discount_type || 'percentage',
    note: purchaseOrderData.value.note || '',
    item_list: [],
    items: buildInitialRows(),
})

const itemSearchOptions = computed(() => {
    const additionalParams = {}
    if (form.warehouse_id) additionalParams.warehouse_id = form.warehouse_id
    return { additionalParams, limit: 200 }
})

const itemOptions = ref([]);
const loadItemOptions = async (warehouseId = form.warehouse_id) => {
    try {
        const response = await axios.get(route('search.items-list'), {
            params: { warehouse_id: warehouseId || undefined, limit: 50, in_stock_only: false }
        })
        itemOptions.value = response.data?.data || []
    } catch (error) {
        console.error('Failed to load items', error)
        itemOptions.value = []
    }
}
loadItemOptions()

const toNum = (v, d = 0) => {
    const n = Number(v)
    return isNaN(n) ? d : n
}

const fmtMoney = (value) => {
    const symbol = form.selected_currency?.symbol ?? ''
    const amount = toNum(value, 0).toFixed(decimalPlaces)
    return symbol ? `${amount} ${symbol}` : amount
}

const handleSelectChange = (field, value) => {
    if (field === 'currency_id') form.rate = value.exchange_rate;
    form[field] = value.id;
};

// See repriceRowsForRate: a currency change converts every price, a rate
// change leaves the ones typed by hand. Item prices are home currency and the
// line is in the document's: this used to multiply by the rate, turning
// 12,000 AFN into 780,000 "USD".
const setLinePrice = (row, value) => {
    row.unit_price = value
    row.price_touched = true
    row.base_unit_price = linePriceToHome(row, form.rate)
}
watch(() => [form.rate, form.currency_id], ([rate, currencyId], [, previousCurrencyId]) => {
    if (!Array.isArray(form.items)) return
    repriceRowsForRate(form.items, rate, currencyId !== previousCurrencyId)
})

const handleItemChange = (index, selected_item) => {
    const row = form.items[index]
    if (!row || !selected_item) return

    const selUM = selected_item?.unitMeasure || {}
    const selectedQuantityId = selUM.quantity_id ?? selUM.quantity?.id
    const selectedQuantityName = (selUM.quantity?.name || selUM.quantity?.code || '').toString().toLowerCase()
    row.available_measures = (unitMeasures.value?.data || []).filter((unit) => {
        const unitQtyId = unit?.quantity_id ?? unit?.quantity?.id
        const unitQtyName = (unit?.quantity?.name || unit?.quantity?.code || '').toString().toLowerCase()
        return (selectedQuantityId && unitQtyId === selectedQuantityId) || (!!selectedQuantityName && unitQtyName === selectedQuantityName)
    })
    row.selected_measure = selected_item.unitMeasure
    row.item_id = selected_item.id
    row.batch = ''
    row.expire_date = ''
    row.quantity = row.quantity || 1

    row.item_variants = selected_item.item_variants || []
    row.selected_variant = pickDefaultVariant(row.item_variants)
    row.variant_id = row.selected_variant?.id || null

    row.base_unit_price = row.selected_variant?.purchase_price ?? selected_item.purchase_price ?? selected_item.avg_cost ?? 0
    const baseUnit = Number(selected_item.unitMeasure?.unit) || 1
    row.unit_price = priceInLineUnit(row.base_unit_price, selected_item, row.selected_measure, form.rate);
    row.price_touched = false
}

const handleVariantChange = (index, variant) => {
    const row = form.items[index]
    if (!row) return
    row.selected_variant = variant || null
    row.variant_id = variant?.id || null
    // Switching variant moves the price with it — that is what pricing a
    // variant separately is for.
    repriceRow(row, variant, 'purchase')
    const baseUnit = Number(row.selected_item?.unitMeasure?.unit) || 1
    const selectedUnit = Number(row.selected_measure?.unit) || baseUnit
    row.unit_price = priceInLineUnit(row.base_unit_price, row.selected_item, row.selected_measure, form.rate)
    row.price_touched = false
}

const rowTotal = (index) => {
    const item = form.items[index]
    if (!item || !item.selected_item) return ''
    const qty = toNum(item.quantity, 0)
    const price = toNum(item.unit_price, 0)
    const disc = toNum(item.discount, 0)
    return Number((qty * price - disc).toFixed(decimalPlaces))
}

const deleteRow = (index) => {
    if (form.items.length === 1) return;
    form.items.splice(index, 1)
}

const buildEmptyRow = () => ({
    item_id: '', selected_item: '', quantity: '', unit_measure_id: '', selected_measure: '',
    available_measures: [], batch: '', expire_date: '', unit_price: '', base_unit_price: '',
    free: '', discount: '', variant_id: null, selected_variant: null, item_variants: [],
})

const addRow = () => {
    form.items.push(buildEmptyRow())
}

const totalRows = computed(() => form.items.length)
const totalQuantity = computed(() => Number(form.items.reduce((acc, item) => acc + toNum(item.quantity, 0), 0).toFixed(decimalPlaces)))
const totalFree = computed(() => Number(form.items.reduce((acc, item) => acc + toNum(item.free, 0), 0).toFixed(decimalPlaces)))
const totalItemDiscount = computed(() => form.items.reduce((acc, item) => acc + toNum(item.discount, 0), 0))
// Each line at cents, the sum at cents: the server's formula, so the form and
// the posted bill agree (49 kg at 71.4286 is 3,500.00, not 3,500.0014).
const goodsTotal = computed(() => Number(form.items.reduce((acc, item) => acc + Number((toNum(item.unit_price, 0) * toNum(item.quantity, 0)).toFixed(2)), 0).toFixed(2)))
const billDiscountCurrency = computed(() => {
    const billDisc = toNum(form.discount, 0)
    if (form.discount_type === 'percentage') return goodsTotal.value * (billDisc / 100)
    return billDisc
})
const totalDiscount = computed(() => Number((billDiscountCurrency.value + totalItemDiscount.value).toFixed(decimalPlaces)))
const totalRowTotal = computed(() => Number((goodsTotal.value - totalDiscount.value).toFixed(decimalPlaces)))

const handleSubmit = () => {
    form.item_list = form.items
        .filter((item) => item.selected_item && item.item_id)
        .map((item) => ({
            ...item,
            unit_measure_id: item.selected_measure?.id,
            variant_id: item.selected_variant?.id ?? item.variant_id ?? null,
        }));

    form.put(route('purchase-orders.update', purchaseOrderData.value.id))
}

// Stock on hand for the line, in the line's own unit. Shown for reference only:
// an order or a quotation moves no stock.
if (localColumns.on_hand === undefined) localColumns.on_hand = true
if (localColumns.variant === undefined) localColumns.variant = true
const onHandDisplay = (index) => {
    const row = form.items[index]
    if (!row?.selected_item) return ''
    const variantOnHand = variantStockFigure(row, row.selected_variant, 'on_hand')
    return stockInLineUnit(row, variantOnHand ?? row.selected_batch?.on_hand ?? row.selected_item?.on_hand ?? 0)
}

// A saved line carries only the item's name. Fetch the live items once so the
// form can show their stock and offer their units, as a freshly picked item does.
const hydrateLines = async () => {
    const ids = [...new Set(form.items.map((row) => row.item_id).filter(Boolean))]
    if (!ids.length) return
    try {
        const response = await axios.get(route('search.items-list'), {
            params: { warehouse_id: form.warehouse_id || undefined, ids, in_stock_only: false, limit: 250 },
        })
        const live = new Map((response.data?.data || []).map((entry) => [entry.id, entry]))
        form.items.forEach((row) => {
            const found = live.get(row.item_id)
            if (!found) return
            row.selected_item = { ...found }
            const selUM = found.unitMeasure || {}
            const quantityId = selUM.quantity_id ?? selUM.quantity?.id
            row.available_measures = (unitMeasures.value?.data || []).filter((unit) => (unit?.quantity_id ?? unit?.quantity?.id) === quantityId)
            if (!row.item_variants?.length) row.item_variants = found.item_variants || []
        })
    } catch (error) {
        console.error('Failed to load item stock', error)
    }
}
onMounted(hydrateLines)

/* ---- Barcode / item search and keyboard flow, as on the sale and purchase forms ---- */
const barcodeRef = ref(null)
const focusBarcode = () => barcodeRef.value?.focus?.()

const handleScannedItem = async (item) => {
    if (!item) return
    // A plain (single-variant) line for the same item just increments.
    const existing = form.items.find((r) => r.selected_item && r.item_id === item.id && (r.item_variants?.length || 0) <= 1)
    if (existing) {
        existing.quantity = toNum(existing.quantity, 0) + 1
    } else {
        let idx = form.items.findIndex((r) => !r.selected_item)
        if (idx === -1) { addRow(); idx = form.items.length - 1 }
        form.items[idx].selected_item = item
        await handleItemChange(idx, item)
        form.items[idx].quantity = 1
    }
    focusBarcode()
}

const handleScanNotFound = (code) => {
    toast({ title: t('general.barcode_not_found', { code }), variant: 'destructive' })
    focusBarcode()
}

const handleScanError = () => {
    toast({ title: t('general.barcode_lookup_failed'), variant: 'destructive' })
    focusBarcode()
}

const handleGlobalKeydown = (e) => {
    if (e.key === 'F2' || (e.altKey && (e.code === 'KeyB' || e.key === 'b' || e.key === 'B'))) {
        e.preventDefault()
        focusBarcode()
    }
}
onMounted(() => window.addEventListener('keydown', handleGlobalKeydown))
onUnmounted(() => window.removeEventListener('keydown', handleGlobalKeydown))

const clearLine = (index) => {
    const row = form.items[index]
    if (!row) return
    Object.assign(row, buildEmptyRow())
    // An emptied line in the middle is a gap: close it.
    if (index < form.items.length - 1) form.items.splice(index, 1)
    if (!form.items.length) addRow()
}

// Enter walks a line: item -> variant -> quantity -> price -> next line.
// Delete on a line empties it.
const { onLineEnter, onCustomerEnter, onGridArrow, onNumberBeforeInput, onNumberPaste, onNumberKeydown } = useLineEnterNavigation({
    hasItem: (row) => !!form.items[row]?.selected_item,
    ensureRow: (row) => { while (form.items.length <= row) addRow() },
    focusBarcode,
    clearRow: clearLine,
})

useFormGuard(form)

const saveFormRef = useSaveShortcut({ form })
</script>

<template>
    <AppLayout :title="t('general.edit', { name: t('purchase_order.purchase_order') })">
        <FormPageToolbar
            back-route="purchase-orders.index"
            module="purchase_orders"
            :show-preferences="true"
            @preferences="showPreferencesPanel = true"
        />
        <FormPreferencesPanel module="purchase_order"
            v-model:open="showPreferencesPanel"
            pref-group="purchase_order"
            :prefs="purchasePrefs"
            :title="t('purchase_order.purchase_order')"
        />
        <form ref="saveFormRef" @submit.prevent="handleSubmit"
              @beforeinput.capture="onNumberBeforeInput"
              @paste.capture="onNumberPaste"
              @keydown.capture="onNumberKeydown">
            <div class="mb-5 rounded-xl border border-violet-500 p-4 shadow-sm relative">
                <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
                    {{ t('general.edit', { name: t('purchase_order.purchase_order') }) }} #{{ purchaseOrderData.number }}
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                    <div @keydown.enter="onCustomerEnter">
                        <NextSelect
                            :options="[]"
                            v-model="form.selected_ledger"
                            @update:modelValue="(value) => handleSelectChange('supplier_id', value)"
                            label-key="name"
                            value-key="id"
                            :reduce="ledger => ledger"
                            :floating-text="t('ledger.supplier.supplier')"
                            is-required
                            :error="form.errors?.supplier_id"
                            :searchable="true"
                            resource-type="ledgers"
                            :search-fields="['name', 'email', 'phone_no']"
                            :search-options="{ type: 'supplier' }"
                        />
                    </div>
                    <NextInput is-required v-if="general_fields.number" :error="form.errors?.number" type="number" v-model="form.number" :label="t('general.bill_number')" />
                    <NextDate v-if="general_fields.date" v-model="form.date" :current-date="true" :error="form.errors?.date" :placeholder="t('general.enter', { text: t('general.date') })" :label="t('general.date')" />
                    <NextDate v-model="form.delivery_date" :lock-future-dates="false" :error="form.errors?.delivery_date" :label="t('purchase_order.delivery_date')" />

                    <div class="grid grid-cols-2 gap-2" v-if="general_fields.currency">
                        <NextSelect
                            :options="currencies.data"
                            v-model="form.selected_currency"
                            label-key="display_name"
                            value-key="id"
                            :clearable="false"
                            @update:modelValue="(value) => handleSelectChange('currency_id', value)"
                            :reduce="currency => currency"
                            :floating-text="t('admin.currency.currency')"
                            :error="form.errors?.currency_id"
                        />
                        <NextInput :error="form.errors?.rate" type="number" step="any" :disabled="form.selected_currency?.is_base_currency === true" v-model="form.rate" :label="t('general.rate')" />
                    </div>

                    <NextSelect v-if="general_fields.warehouse"
                        :options="warehouses.data"
                        :clearable="true"
                        v-model="form.selected_warehouse"
                        @update:modelValue="(value) => handleSelectChange('warehouse_id', value)"
                        label-key="name"
                        value-key="id"
                        :reduce="warehouse => warehouse"
                        :floating-text="t('admin.warehouse.warehouse')"
                        :error="form.errors?.warehouse_id"
                    />
                    <div class="md:col-span-3">
                        <NextTextarea v-model="form.note" :error="form.errors?.note" :label="t('general.note')" />
                    </div>
                </div>
            </div>

            <!-- Barcode scanner / item search: scan or type to add or increment a line -->
            <div class="mb-2 flex flex-wrap items-center gap-3">
                <ScanBarcode class="h-5 w-5 shrink-0 text-violet-500" />
                <div class="w-full max-w-xs">
                    <BarcodeSearchInput
                        ref="barcodeRef"
                        :warehouse-id="form.warehouse_id"
                        :in-stock-only="false"
                        :disabled="!form.selected_ledger"
                        :disabled-hint="t('general.select_ledger_first')"
                        @select="handleScannedItem"
                        @not-found="handleScanNotFound"
                        @error="handleScanError"
                    />
                </div>
                <span class="hidden text-xs text-muted-foreground sm:inline">{{ t('general.sale_keyboard_hint') }}</span>
            </div>
            <div class="rounded-md border bg-card shadow-sm border-violet-500">
                <table class="w-full table-fixed min-w-[1200px] border-separate" @keydown.enter="onLineEnter" @keydown.capture="onGridArrow">
                    <thead class="bg-card sticky top-0 z-10">
                        <tr class="rounded-xl text-muted-foreground font-semibold text-sm text-violet-500">
                            <th class="px-1 py-1 w-5 text-center">#</th>
                            <th class="px-1 py-1 w-40 min-w-64">{{ t('item.item') }} <span class="text-red-500">*</span></th>
                            <th class="px-1 py-1 w-32" v-if="localColumns.variant">{{ t('item.variant') }}</th>
                            <th class="px-1 py-1 w-32" v-if="localColumns.batch">{{ t('general.batch') }}</th>
                            <th class="px-1 py-1 w-36" v-if="localColumns.expiry">{{ t('general.expire_date') }}</th>
                            <th class="px-1 py-1 w-20" v-if="localColumns.on_hand">{{ t('general.on_hand') }}</th>
                            <th class="px-1 py-1 w-16">{{ t('general.qty') }} <span class="text-red-500">*</span></th>
                            <th class="px-1 py-1 w-24" v-if="localColumns.measure">{{ t('general.unit') }}</th>
                            <th class="px-1 py-1 w-24">{{ t('general.price') }} <span class="text-red-500">*</span></th>
                            <th class="px-1 py-1 w-24" v-if="localColumns.discount">{{ t('general.discount') }}</th>
                            <th class="px-1 py-1 w-16" v-if="localColumns.free">{{ t('general.free') }}</th>
                            <th class="px-1 py-1 w-16">{{ t('general.total') }}</th>
                            <th class="px-1 py-1 w-10">
                                <Trash2 class="w-4 h-4 text-fuchsia-700 inline" />
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, index) in form.items" :key="index" class="hover:bg-muted/40 transition-colors">
                            <td class="px-1 py-2 align-top w-5 text-center">{{ index + 1 }}</td>
                            <td data-nav="item" :data-row="index">
                                <NextSelect
                                    :options="itemOptions"
                                    v-model="item.selected_item"
                                    label-key="name"
                                    :placeholder="t('general.search_or_select')"
                                    :error="form.errors?.[`item_list.${index}.item_id`]"
                                    :show-arrow="false"
                                    :has-add-button="false"
                                    :searchable="true"
                                    resource-type="items-list"
                                    :search-fields="['name', 'code', 'generic_name', 'packing', 'barcode', 'fast_search']"
                                    value-key="id"
                                    :reduce="itemValue => itemValue"
                                    :search-options="itemSearchOptions"
                                    @update:modelValue="value => handleItemChange(index, value)"
                                />
                            </td>
                            <td v-if="localColumns.variant" data-nav="variant" :data-row="index">
                                <VariantCell
                                    :model-value="item.selected_variant"
                                    :item-variants="item.item_variants"
                                    :disabled="!item?.selected_item"
                                    :error="form.errors?.[`item_list.${index}.variant_id`]"
                                    :id="`order_variant_${index}`"
                                    @update:modelValue="value => handleVariantChange(index, value)"
                                />
                            </td>
                            <td v-if="localColumns.batch">
                                <NextInput v-model="item.batch" :disabled="!item?.selected_item" :error="form.errors?.[`item_list.${index}.batch`]" />
                            </td>
                            <td v-if="localColumns.expiry">
                                <NextDate v-model="item.expire_date" :lock-future-dates="false" popover="top-left" :error="form.errors?.[`item_list.${index}.expire_date`]" />
                            </td>
                            <td class="text-center text-sm" v-if="localColumns.on_hand">{{ onHandDisplay(index) }}</td>
                            <td data-nav="qty" :data-row="index">
                                <NextInput v-model="item.quantity" :disabled="!item?.selected_item" type="number" step="any" inputmode="decimal" :error="form.errors?.[`item_list.${index}.quantity`]" />
                            </td>
                            <td v-if="localColumns.measure">
                                <NextSelect
                                    :options="item.available_measures.length ? item.available_measures : [item.selected_measure].filter(Boolean)"
                                    v-model="item.selected_measure"
                                    label-key="name"
                                    :error="form.errors?.[`item_list.${index}.unit_measure_id`]"
                                    value-key="id"
                                    :show-arrow="false"
                                    :reduce="unit => unit"
                                />
                            </td>
                            <td data-nav="price" :data-row="index">
                                <NextInput :model-value="item.unit_price" @update:model-value="setLinePrice(item, $event)" :disabled="!item?.selected_item" type="number" step="any" inputmode="decimal" :error="form.errors?.[`item_list.${index}.unit_price`]" />
                            </td>
                            <td v-if="localColumns.discount">
                                <NextInput v-model="item.discount" :disabled="!item?.selected_item" type="number" step="any" inputmode="decimal" :error="form.errors?.[`item_list.${index}.discount`]" />
                            </td>
                            <td v-if="localColumns.free">
                                <NextInput v-model="item.free" :disabled="!item?.selected_item" type="number" step="any" inputmode="decimal" :error="form.errors?.[`item_list.${index}.free`]" />
                            </td>
                            <td class="text-center">{{ rowTotal(index) }}</td>
                            <td class="w-10 text-center">
                                <Trash2 class="w-4 h-4 cursor-pointer text-fuchsia-500 inline" @click="deleteRow(index)" />
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="sticky bottom-0 bg-card">
                        <tr class="bg-violet-500/10 hover:bg-violet-500/30 transition-colors">
                            <td></td>
                            <td class="text-center">{{ totalRows }}</td>
                            <td v-if="localColumns.variant"></td>
                            <td v-if="localColumns.batch"></td>
                            <td v-if="localColumns.expiry"></td>
                            <td v-if="localColumns.on_hand"></td>
                            <td class="text-center">{{ totalQuantity || 0 }}</td>
                            <td v-if="localColumns.measure"></td>
                            <td class="text-center">{{ goodsTotal || 0 }}</td>
                            <td class="text-center" v-if="localColumns.discount">{{ totalItemDiscount || 0 }}</td>
                            <td class="text-center" v-if="localColumns.free">{{ totalFree || 0 }}</td>
                            <td class="text-center">{{ totalRowTotal || 0 }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
                <div class="p-2">
                    <button type="button" class="text-sm text-violet-600 hover:underline" @click="addRow">+ {{ t('general.add') }}</button>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-2 items-start">
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-xs font-medium uppercase tracking-wide text-muted-foreground mb-1">{{ t('general.bill_discount') }} ({{ form.discount_type === 'percentage' ? '%' : form.selected_currency?.symbol }})</div>
                    <DiscountField v-model="form.discount" v-model:discount-type="form.discount_type" label=" " :error="form.errors?.discount" />
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-sm font-semibold mb-3 text-violet-500">{{ t('purchase_order.order_total') }}</div>
                    <div class="text-2xl font-bold text-violet-600 dark:text-violet-400">{{ fmtMoney(totalRowTotal) }}</div>
                </div>
            </div>


            <SubmitButtons
                class="mt-4"
                :create-label="t('general.save')"
                :cancel-label="t('general.cancel')"
                :creating-label="t('general.saving')"
                :create-loading="form.processing"
                :show-create-and-new="false"
                module="purchase_orders"
                @cancel="$inertia.visit(route('purchase-orders.index'))"
            />
        </form>
    </AppLayout>
</template>
