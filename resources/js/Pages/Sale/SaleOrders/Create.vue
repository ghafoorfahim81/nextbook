<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { useFormGuard } from '@/composables/useFormGuard'
import { h, ref, watch, onMounted, onUnmounted, computed, reactive } from 'vue';
import axios from 'axios'
import { useForm, usePage } from '@inertiajs/vue3';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import DiscountField from '@/Components/next/DiscountField.vue';
import { useI18n } from 'vue-i18n';
import SubmitButtons from '@/Components/SubmitButtons.vue';
import FormPageToolbar from '@/Components/FormPageToolbar.vue'
import FormPreferencesPanel from '@/Components/FormPreferencesPanel.vue'
import { useSidebar } from '@/Components/ui/sidebar/utils';
import { ToastAction } from '@/Components/ui/toast'
import { useToast } from '@/Components/ui/toast/use-toast'
import NextDate from '@/Components/next/NextDatePicker.vue'
import { Trash2 } from 'lucide-vue-next';
import { todayValueForCalendar } from '@/utils/dateDefaults'

const { t } = useI18n();
const { toast } = useToast()
const page = usePage()
const calendarType = computed(() => page.props.auth?.user?.calendar_type || 'gregorian')

const props = defineProps({
    ledgers: { type: Object, required: false, default: () => ({ data: [] }) },
    saleOrderNumber: { type: [String, Number], required: true },
})

const currencies = computed(() => page.props.currencies ?? { data: [] })
const warehouses = computed(() => page.props.warehouses ?? { data: [] })
const unitMeasures = computed(() => page.props.unitMeasures ?? { data: [] })
const sizes = computed(() => page.props.sizes ?? { data: [] })
const categories = computed(() => page.props.categories ?? { data: [] })
const decimalPlaces = Number(page.props.auth?.user?.preferences?.appearance?.decimal_places ?? 2)

const user_preferences = computed(() => page.props.user_preferences?.data ?? page.props.user_preferences ?? {})
// Single reactive copy of the sale_order preferences so the panel and form stay in sync live.
const salePrefs = reactive(JSON.parse(JSON.stringify(user_preferences.value?.sale_order ?? {})))
if (!salePrefs.general_fields || typeof salePrefs.general_fields !== 'object') salePrefs.general_fields = {}
if (!salePrefs.item_columns || typeof salePrefs.item_columns !== 'object') salePrefs.item_columns = {}
const general_fields = salePrefs.general_fields
const localColumns = salePrefs.item_columns
const showPreferencesPanel = ref(false)

const buildEmptyRow = () => ({
    item_id: '',
    selected_item: '',
    quantity: '',
    unit_measure_id: '',
    selected_measure: '',
    available_measures: [],
    batch: '',
    selected_batch: null,
    expire_date: '',
    unit_price: '',
    base_unit_price: '',
    free: '',
    discount: '',
    size_id: '',
    selected_size: '',
    category_id: '',
    selected_category: '',
})

const form = useForm({
    number: props.saleOrderNumber,
    customer_id: '',
    selected_ledger: '',
    date: '',
    delivery_date: '',
    currency_id: '',
    selected_currency: '',
    rate: '',
    warehouse_id: '',
    selected_warehouse: '',
    discount: '',
    discount_type: 'percentage',
    note: '',
    sale_order_id: '',
    item_list: [],
    items: Array.from({ length: 6 }, buildEmptyRow),
})

const resolveDefaultCurrency = () => currencies.value?.data?.find((currency) => currency.is_base_currency) ?? null
const resolveDefaultWarehouse = () => warehouses.value?.data?.find((warehouse) => warehouse.is_main === true) ?? null

const applyCreateDefaults = ({ number = props.saleOrderNumber } = {}) => {
    const defaultCurrency = resolveDefaultCurrency()
    if (defaultCurrency) {
        form.selected_currency = defaultCurrency
        form.rate = defaultCurrency.exchange_rate
        form.currency_id = defaultCurrency.id
    }

    const defaultWarehouse = resolveDefaultWarehouse()
    if (defaultWarehouse) {
        form.selected_warehouse = defaultWarehouse
        form.warehouse_id = defaultWarehouse.id
    }

    form.number = number
    form.date = todayValueForCalendar(calendarType.value)
}

const resetFormForCreate = ({ number = props.saleOrderNumber } = {}) => {
    form.reset()
    form.clearErrors()
    form.item_list = []
    form.items = Array.from({ length: 6 }, buildEmptyRow)
    applyCreateDefaults({ number })
    loadItemOptions(form.warehouse_id)
}

// Items search
const itemSearchOptions = computed(() => {
    const additionalParams = {}
    if (form.warehouse_id) {
        additionalParams.warehouse_id = form.warehouse_id
    }
    return { additionalParams, limit: 200 }
})

const itemOptions = ref([]);
const loadItemOptions = async (warehouseId = form.warehouse_id) => {
    try {
        const response = await axios.get(route('search.items-list'), {
            params: { warehouse_id: warehouseId || undefined, limit: 50 }
        })
        itemOptions.value = response.data?.data || []
    } catch (error) {
        console.error('Failed to load items', error)
        itemOptions.value = []
    }
}

watch(() => form.warehouse_id, () => { loadItemOptions() }, { immediate: true });

watch(() => props.saleOrderNumber, (newNumber) => {
    if (newNumber) form.number = newNumber;
}, { immediate: true });

watch(currencies, (value) => {
    if (value?.data && !form.currency_id) {
        const baseCurrency = resolveDefaultCurrency();
        if (baseCurrency) {
            form.selected_currency = baseCurrency;
            form.rate = baseCurrency.exchange_rate;
            form.currency_id = baseCurrency.id;
        }
    }
}, { immediate: true });

watch(warehouses, (value) => {
    if (value?.data && !form.selected_warehouse) {
        const baseWarehouse = resolveDefaultWarehouse();
        if (baseWarehouse) {
            form.selected_warehouse = baseWarehouse;
            form.warehouse_id = baseWarehouse.id;
        }
    }
}, { immediate: true });

const handleSelectChange = (field, value) => {
    if (field === 'warehouse_id') {
        form.items = Array.from({ length: 6 }, buildEmptyRow);
    }
    if (field === 'currency_id') {
        form.rate = value.exchange_rate;
    }
    form[field] = value.id;
};

const submitAction = ref(null);
const createLoading = computed(() => form.processing && submitAction.value === 'create');
const createAndNewLoading = computed(() => form.processing && submitAction.value === 'create_and_new');

const handleSubmitAction = (action = 'create') => {
    submitAction.value = action;
    handleSubmit({ createAndNew: action === 'create_and_new' });
};

const notifySound = (type) => {
    if (type === 'success') {
        const sound = new Audio('/notify_sounds/filling-your-inbox.mp3');
        sound.play().catch((error) => console.error('Error playing sound:', error));
    } else {
        const sound = new Audio('/notify_sounds/glass-breaking.mp3');
        sound.play().catch((error) => console.error('Error playing sound:', error));
    }
}

function handleSubmit({ createAndNew = false } = {}) {
    if (!form.items[0]?.selected_item) {
        notifySound('error');
        toast({
            title: t('sale.no_item_warning'),
            description: t('sale.no_item_warning_description'),
            variant: 'destructive',
            class: 'bg-yellow-600 text-white',
        })
        return;
    }

    form.item_list = form.items
        .filter((item) => item.selected_item && item.item_id)
        .map((item) => ({
            ...item,
            unit_measure_id: item.selected_measure?.id,
            size_id: item.selected_size?.id ?? null,
            category_id: item.selected_category?.id ?? null,
        }));

    const payload = { create_and_new: createAndNew };

    form.transform((data) => ({ ...data, ...payload })).post(route('sale-orders.store'), {
        onSuccess: () => {
            notifySound('success');
            if (createAndNew) {
                resetFormForCreate();
            }
            toast({
                title: t('general.success'),
                description: t('general.create_success', { name: t('sale_order.sale_order') }),
                variant: 'success',
                class: 'bg-green-600 text-white',
            })
        },
        onError: () => {
            notifySound('error');
            toast({
                title: t('general.error'),
                description: t('general.error_creating_sale_order'),
                variant: 'destructive',
                class: 'bg-pink-600 text-white',
            })
        }
    })
}

let sidebar = null
try { sidebar = useSidebar() } catch (e) { sidebar = null }
const prevSidebarOpen = ref(true)
onMounted(() => {
    if (sidebar) {
        prevSidebarOpen.value = sidebar.open.value
        sidebar.setOpen(false)
    }
    applyCreateDefaults()
})
onUnmounted(() => {
    if (sidebar) sidebar.setOpen(prevSidebarOpen.value)
})

watch(() => form.rate, (newRate) => {
    if (!Array.isArray(form.items)) return
    form.items.forEach((row) => {
        if (!row || !row?.selected_item) return
        const baseUnit = Number(row.selected_item?.unitMeasure?.unit) || 1
        const selectedUnit = Number(row.selected_measure?.unit) || baseUnit
        const baseUnitPrice = Number(row.base_unit_price ?? row.selected_item?.unit_price ?? row.selected_item?.sale_price ?? 0)
        row.unit_price = (baseUnitPrice * selectedUnit * (Number(newRate) || 0)) / baseUnit
    })
})

const handleItemChange = (index, selected_item) => {
    const row = form.items[index]
    if (!row || !selected_item) {
        row.available_measures = []
        row.selected_measure = ''
        row.unit_price = ''
        row.base_unit_price = ''
        row.quantity = ''
        row.selected_batch = null
        row.batch = ''
        row.expire_date = ''
        row.free = ''
        row.discount = ''
        return
    }

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
    row.selected_batch = null
    row.quantity = 1
    row.batch = ''
    row.expire_date = ''

    const marginPercentage = toNum(selected_item.margin_percentage, 0).toFixed(decimalPlaces);
    row.base_unit_price = selected_item.sale_price ?? selected_item.avg_cost * (1 + marginPercentage / 100) ?? 0

    const baseUnit = Number(selected_item.unitMeasure?.unit) || 1
    row.unit_price = Number(((row.base_unit_price * Number(row.selected_measure.unit) * form.rate) / baseUnit).toFixed(2));

    if (index === form.items.length - 1) {
        addRow()
    }

    notifyIfDuplicate(index)
}

const isRowEnabled = (index) => {
    if (!form.selected_ledger) return false
    for (let i = 0; i < index; i++) {
        if (!form.items[i]?.selected_item) return false
    }
    return true
}

const buildRowKey = (r) => {
    const measureId = r?.selected_measure?.id
        || (typeof r?.selected_measure === 'object' ? (r?.selected_measure?.name || r?.selected_measure?.unit) : r?.selected_measure)
        || ''
    return [
        (r.item_id || r.selected_item?.id || '').toString(),
        (r.batch || '').toString().trim().toLowerCase(),
        (r.expire_date || '').toString().trim(),
        measureId.toString()
    ].join('|')
}

const isDuplicateRow = (index) => {
    const r = form.items[index]
    if (!r || !r.selected_item || !r.selected_measure) return false
    const key = buildRowKey(r)
    let count = 0
    for (let i = 0; i < form.items.length; i++) {
        if (buildRowKey(form.items[i]) === key) count++
        if (count > 1) return true
    }
    return false
}

const resetRow = (index) => {
    const r = form.items[index]
    if (!r) return
    Object.assign(r, buildEmptyRow())
}

const duplicateToast = ref(null)
const hasDuplicateRows = computed(() => {
    const seen = new Set()
    for (const r of form.items) {
        if (!r?.selected_item || !r?.selected_measure) continue
        const key = buildRowKey(r)
        if (seen.has(key)) return true
        seen.add(key)
    }
    return false
})

watch(hasDuplicateRows, (hasDuplicates) => {
    if (!hasDuplicates && duplicateToast.value) {
        duplicateToast.value.dismiss()
        duplicateToast.value = null
    }
})

const notifyIfDuplicate = (index) => {
    if (isDuplicateRow(index)) {
        const item = form.items[index]
        const batchText = item.batch ? `Batch: ${item.batch}` : 'No batch'
        const expiryText = item.expire_date ? `Expiry: ${item.expire_date}` : 'No expiry'
        duplicateToast.value = toast({
            title: t('general.duplicate_item_detected'),
            description: t('general.duplicate_item_detected_description', { batchText, expiryText }),
            variant: 'destructive',
            class: 'bg-pink-600 text-white',
            duration: Infinity,
            action: h(ToastAction, { altText: t('general.unselect'), onClick: () => resetRow(index) }, { default: () => t('general.unselect') }),
        })
    }
}

function handleBatchChange(index, batch) {
    const row = form.items[index]
    row.selected_batch = batch ?? null
    row.batch = batch?.batch
    row.expire_date = batch?.expire_date
    notifyIfDuplicate(index)
}

const toNum = (v, d = 0) => {
    const n = Number(v)
    return isNaN(n) ? d : n
}

const fmtMoney = (value) => {
    const symbol = form.selected_currency?.symbol ?? ''
    const amount = toNum(value, 0).toFixed(decimalPlaces)
    return symbol ? `${amount} ${symbol}` : amount
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

const totalRows = computed(() => form.items.length)
const totalItemDiscount = computed(() => form.items.reduce((acc, item) => acc + toNum(item.discount, 0), 0))
const goodsTotal = computed(() => form.items.reduce((acc, item) => acc + (toNum(item.unit_price, 0) * toNum(item.quantity, 0)), 0))
const billDiscountCurrency = computed(() => {
    const billDisc = toNum(form.discount, 0)
    if (form.discount_type === 'percentage') return goodsTotal.value * (billDisc / 100)
    return billDisc
})
const totalDiscount = computed(() => Number((billDiscountCurrency.value + totalItemDiscount.value).toFixed(decimalPlaces)))
const totalRowTotal = computed(() => Number((goodsTotal.value - totalDiscount.value).toFixed(decimalPlaces)))
const totalQuantity = computed(() => Number(form.items.reduce((acc, item) => acc + toNum(item.quantity, 0), 0).toFixed(decimalPlaces)))
const totalFree = computed(() => Number(form.items.reduce((acc, item) => acc + toNum(item.free, 0), 0).toFixed(decimalPlaces)))

const addRow = () => {
    form.items.push(buildEmptyRow())
}

useFormGuard(form)
</script>

<template>
    <AppLayout :title="t('general.create', { name: t('sale_order.sale_order') })" :sidebar-collapsed="true">
        <FormPageToolbar
            back-route="sale-orders.index"
            module="sale_orders"
            :show-preferences="true"
            @preferences="showPreferencesPanel = true"
        />
        <FormPreferencesPanel module="sale_order"
            v-model:open="showPreferencesPanel"
            pref-group="sale_order"
            :prefs="salePrefs"
            :title="t('sale_order.sale_order')"
        />

        <form @submit.prevent="handleSubmitAction('create')">
            <div class="mb-5 rounded-xl border border-violet-500 p-4 shadow-sm relative">
                <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">{{ t('general.create', { name: t('sale_order.sale_order') }) }}</div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                    <NextSelect
                        autofocus
                        :options="ledgers?.data || []"
                        v-model="form.selected_ledger"
                        @update:modelValue="(value) => handleSelectChange('customer_id', value)"
                        label-key="name"
                        value-key="id"
                        :reduce="ledger => ledger"
                        :floating-text="t('ledger.customer.customer')"
                        :error="form.errors?.customer_id"
                        :searchable="true"
                        resource-type="ledgers"
                        :search-fields="['name', 'email', 'phone_no']"
                        :search-options="{ type: 'customer' }"
                    />
                    <NextInput placeholder="Number" v-if="general_fields.number" :error="form.errors?.number" type="number" v-model="form.number" :label="t('general.bill_number')" />
                    <NextDate v-if="general_fields.date" v-model="form.date" :current-date="true" :error="form.errors?.date" :placeholder="t('general.enter', { text: t('general.date') })" :label="t('general.date')" />
                    <NextDate v-model="form.delivery_date" :error="form.errors?.delivery_date" :placeholder="t('general.enter', { text: t('sale_order.delivery_date') })" :label="t('sale_order.delivery_date')" />

                    <div class="grid grid-cols-2 gap-2" v-if="general_fields.currency">
                        <NextSelect
                            :options="currencies.data"
                            v-model="form.selected_currency"
                            label-key="code"
                            value-key="id"
                            :clearable="false"
                            @update:modelValue="(value) => handleSelectChange('currency_id', value)"
                            :reduce="currency => currency"
                            :floating-text="t('admin.currency.currency')"
                            :error="form.errors?.currency_id"
                            :searchable="true"
                            resource-type="currencies"
                            :search-fields="['name', 'code', 'symbol']"
                        />
                        <NextInput placeholder="Rate" :error="form.errors?.rate" type="number" step="any" :disabled="form.selected_currency?.is_base_currency === true" v-model="form.rate" :label="t('general.rate')" />
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
                        resource-type="warehouses"
                        :search-fields="['name', 'code', 'address']"
                    />
                </div>
            </div>

            <div class="rounded-md border bg-card shadow-sm border-violet-500">
                <table class="w-full table-fixed min-w-[1200px] sale-table border-separate">
                    <thead class="bg-card sticky top-0 z-10">
                        <tr class="rounded-xl text-muted-foreground font-semibold text-sm text-violet-500">
                            <th class="px-1 py-1 w-5 min-w-5 text-center">#</th>
                            <th class="px-1 py-1 w-40 min-w-64">{{ t('item.item') }}</th>
                            <th class="px-1 py-1 w-32" v-if="localColumns.batch">{{ t('general.batch') }}</th>
                            <th class="px-1 py-1 w-36" v-if="localColumns.expiry">{{ t('general.expire_date') }}</th>
                            <th class="px-1 py-1 w-16">{{ t('general.qty') }}</th>
                            <th class="px-1 py-1 w-24" v-if="localColumns.measure">{{ t('general.unit') }}</th>
                            <th class="px-1 py-1 w-24">{{ t('general.price') }}</th>
                            <th class="px-1 py-1 w-28" v-if="localColumns.size">{{ t('admin.size.size') }}</th>
                            <th class="px-1 py-1 w-28" v-if="localColumns.category">{{ t('admin.category.category') }}</th>
                            <th class="px-1 py-1 w-24" v-if="localColumns.discount">{{ t('general.discount') }}</th>
                            <th class="px-1 py-1 w-16" v-if="localColumns.free">{{ t('general.free') }}</th>
                            <th class="px-1 py-1 w-16">{{ t('general.total') }}</th>
                            <th class="px-1 py-1 w-10">
                                <Trash2 class="w-4 h-4 text-fuchsia-700 inline" />
                            </th>
                        </tr>
                    </thead>
                    <tbody class="p-2">
                        <tr v-for="(item, index) in form.items" :key="index" class="hover:bg-muted/40 transition-colors">
                            <td class="px-1 py-2 align-top w-5 text-center">{{ index + 1 }}</td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
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
                            <td v-if="localColumns.batch" :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                                <NextSelect
                                    :options="item.selected_item?.batches"
                                    v-model="item.selected_batch"
                                    label-key="batch"
                                    :placeholder="t('general.search_or_select')"
                                    :error="form.errors?.[`item_list.${index}.batch`]"
                                    :show-arrow="false"
                                    value-key="batch"
                                    :reduce="batch => batch"
                                    @update:modelValue="value => handleBatchChange(index, value)"
                                />
                            </td>
                            <td v-if="localColumns.expiry" :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                                <NextDate v-model="item.expire_date" popover="top-left" disabled="true" :error="form.errors?.[`item_list.${index}.expire_date`]" />
                            </td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                                <NextInput v-model="item.quantity" :disabled="!item?.selected_item" type="number" step="any" inputmode="decimal" :error="form.errors?.[`item_list.${index}.quantity`]" />
                            </td>
                            <td v-if="localColumns.measure" :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                                <NextSelect
                                    :options="item.available_measures"
                                    v-model="item.selected_measure"
                                    label-key="name"
                                    :error="form.errors?.[`item_list.${index}.unit_measure_id`]"
                                    value-key="id"
                                    :show-arrow="false"
                                    :reduce="unit => unit"
                                    @update:modelValue="(measure) => {
                                        const baseUnit = Number(form.items[index]?.selected_item?.unitMeasure?.unit) || 1
                                        const selectedUnit = Number(measure?.unit) || baseUnit
                                        const baseUnitPrice = Number(form.items[index]?.base_unit_price) || 0
                                        form.items[index].unit_price = (baseUnitPrice * selectedUnit * form.rate) / baseUnit;
                                        notifyIfDuplicate(index)
                                    }"
                                />
                            </td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                                <NextInput v-model="item.unit_price" :disabled="!item?.selected_item" type="number" step="any" inputmode="decimal" :error="form.errors?.[`item_list.${index}.unit_price`]" />
                            </td>
                            <td v-if="localColumns.size" :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                                <NextSelect
                                    :options="sizes.data"
                                    v-model="item.selected_size"
                                    label-key="name"
                                    value-key="id"
                                    :show-arrow="false"
                                    :reduce="size => size"
                                    :error="form.errors?.[`item_list.${index}.size_id`]"
                                />
                            </td>
                            <td v-if="localColumns.category" :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                                <NextSelect
                                    :options="categories.data"
                                    v-model="item.selected_category"
                                    label-key="name"
                                    value-key="id"
                                    :show-arrow="false"
                                    :reduce="category => category"
                                    :error="form.errors?.[`item_list.${index}.category_id`]"
                                />
                            </td>
                            <td v-if="localColumns.discount" :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                                <NextInput v-model="item.discount" :disabled="!item?.selected_item" type="number" step="any" inputmode="decimal" :error="form.errors?.[`item_list.${index}.discount`]" />
                            </td>
                            <td v-if="localColumns.free" :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                                <NextInput v-model="item.free" :disabled="!item?.selected_item" type="number" step="any" inputmode="decimal" :error="form.errors?.[`item_list.${index}.free`]" />
                            </td>
                            <td class="text-center">
                                {{ rowTotal(index) }} {{ item?.selected_item ? form.selected_currency?.symbol : '' }}
                            </td>
                            <td class="w-10 text-center">
                                <Trash2 class="w-4 h-4 cursor-pointer text-fuchsia-500 inline" @click="deleteRow(index)" />
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="sticky bottom-0 bg-card">
                        <tr class="bg-violet-500/10 hover:bg-violet-500/30 transition-colors">
                            <td></td>
                            <td class="text-center">{{ totalRows }}</td>
                            <td v-if="localColumns.batch"></td>
                            <td v-if="localColumns.expiry"></td>
                            <td class="text-center">{{ totalQuantity || 0 }}</td>
                            <td v-if="localColumns.measure"></td>
                            <td class="text-center">{{ goodsTotal || 0 }}</td>
                            <td v-if="localColumns.size"></td>
                            <td v-if="localColumns.category"></td>
                            <td class="text-center" v-if="localColumns.discount">{{ totalItemDiscount || 0 }}</td>
                            <td class="text-center" v-if="localColumns.free">{{ totalFree || 0 }}</td>
                            <td class="text-center">{{ totalRowTotal || 0 }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="mt-5 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-2 items-start">
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-sm font-semibold mb-3 text-violet-500">{{ t('general.discount_summary') }}</div>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">{{ t('general.total_item_discount') }}</span>
                            <span class="tabular-nums text-sm">{{ fmtMoney(totalItemDiscount) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">{{ t('general.bill_disc') }}</span>
                            <span class="tabular-nums text-sm">{{ fmtMoney(billDiscountCurrency) }}</span>
                        </div>
                        <div class="flex items-center justify-between font-semibold pt-1">
                            <span class="text-sm">{{ t('general.total_discount') }}</span>
                            <span class="tabular-nums text-sm">{{ fmtMoney(totalDiscount) }}</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-xs font-medium uppercase tracking-wide text-muted-foreground mb-1">{{ t('general.bill_discount') }} ({{ form.discount_type === 'percentage' ? '%' : form.selected_currency?.symbol }})</div>
                    <DiscountField v-model="form.discount" v-model:discount-type="form.discount_type" label=" " :error="form.errors?.discount" />
                </div>

                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-sm font-semibold mb-3 text-violet-500">{{ t('sale_order.order_total') }}</div>
                    <div class="text-2xl font-bold text-violet-600 dark:text-violet-400">{{ fmtMoney(totalRowTotal) }}</div>
                </div>
            </div>

            <div class="mt-4">
                <NextTextarea v-model="form.note" :error="form.errors?.note" :label="t('general.note')" />
            </div>

            <SubmitButtons module="sale_orders"
                :create-label="t('general.create')"
                :create-and-new-label="t('general.create_and_new')"
                :cancel-label="t('general.cancel')"
                :creating-label="t('general.creating', { name: t('sale_order.sale_order') })"
                :create-loading="createLoading"
                :create-and-new-loading="createAndNewLoading"
                :disabled="hasDuplicateRows"
                @create-and-new="handleSubmitAction('create_and_new')"
                @cancel="() => $inertia.visit(route('sale-orders.index'))"
            />
        </form>
    </AppLayout>
</template>

<style scoped>
.sale-table thead {
    border: 2px solid hsl(var(--border));
    border-radius: 8px;
}

.sale-table thead th {
    border-bottom: 1px solid hsl(var(--border));
    padding: 0.5rem;
    white-space: nowrap;
    overflow: hidden;
}
</style>
