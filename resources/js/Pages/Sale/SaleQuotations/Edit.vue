<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { useFormGuard } from '@/composables/useFormGuard'
import { ref, watch, computed, reactive } from 'vue';
import axios from 'axios'
import { useForm, usePage } from '@inertiajs/vue3';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import DiscountField from '@/Components/next/DiscountField.vue';
import NextDate from '@/Components/next/NextDatePicker.vue'
import { useColors } from '@/composables/useColors'
import SubmitButtons from '@/Components/SubmitButtons.vue';
import FormPageToolbar from '@/Components/FormPageToolbar.vue'
import FormPreferencesPanel from '@/Components/FormPreferencesPanel.vue'
import { Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const { colorOptions } = useColors();
const page = usePage()

const props = defineProps({
    saleQuotation: { type: Object, required: true },
})

const saleQuotationData = computed(() => props.saleQuotation?.data ?? props.saleQuotation ?? {})

const currencies = computed(() => page.props.currencies ?? { data: [] })
const warehouses = computed(() => page.props.warehouses ?? { data: [] })
const unitMeasures = computed(() => page.props.unitMeasures ?? { data: [] })
const sizes = computed(() => page.props.sizes ?? { data: [] })
const categories = computed(() => page.props.categories ?? { data: [] })
const decimalPlaces = Number(page.props.auth?.user?.preferences?.appearance?.decimal_places ?? 2)

const user_preferences = computed(() => page.props.user_preferences?.data ?? page.props.user_preferences ?? {})
const salePrefs = reactive(JSON.parse(JSON.stringify(user_preferences.value?.sale_quotation ?? {})))
if (!salePrefs.general_fields || typeof salePrefs.general_fields !== 'object') salePrefs.general_fields = {}
if (!salePrefs.item_columns || typeof salePrefs.item_columns !== 'object') salePrefs.item_columns = {}
const general_fields = salePrefs.general_fields
const localColumns = salePrefs.item_columns
const showPreferencesPanel = ref(false)

const findById = (list, id) => (list || []).find((entry) => entry.id === id) ?? null

const buildInitialRows = () => (saleQuotationData.value.item_list || []).map((item) => ({
    item_id: item.item_id,
    selected_item: { id: item.item_id, name: item.item_name },
    quantity: item.quantity,
    unit_measure_id: item.unit_measure_id,
    selected_measure: findById(unitMeasures.value?.data, item.unit_measure_id) || { id: item.unit_measure_id, name: '' },
    available_measures: [],
    batch: item.batch || '',
    color: item.color || null,
    expire_date: item.expire_date || '',
    unit_price: item.unit_price,
    base_unit_price: item.unit_price,
    free: item.free || '',
    discount: item.discount || '',
    size_id: item.size_id || '',
    selected_size: findById(sizes.value?.data, item.size_id),
    category_id: item.category_id || '',
    selected_category: findById(categories.value?.data, item.category_id),
}))

const form = useForm({
    number: saleQuotationData.value.number,
    customer_id: saleQuotationData.value.customer_id,
    selected_ledger: saleQuotationData.value.customer ? { id: saleQuotationData.value.customer_id, name: saleQuotationData.value.customer_name } : '',
    date: '',
    valid_until: '',
    currency_id: saleQuotationData.value.currency_id || '',
    selected_currency: findById(currencies.value?.data, saleQuotationData.value.currency_id) || '',
    rate: saleQuotationData.value.rate || '',
    warehouse_id: saleQuotationData.value.warehouse_id || '',
    selected_warehouse: findById(warehouses.value?.data, saleQuotationData.value.warehouse_id) || '',
    discount: saleQuotationData.value.discount || '',
    discount_type: saleQuotationData.value.discount_type || 'percentage',
    note: saleQuotationData.value.note || '',
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
            params: { warehouse_id: warehouseId || undefined, limit: 50 }
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

    const marginPercentage = toNum(selected_item.margin_percentage, 0).toFixed(decimalPlaces);
    row.base_unit_price = selected_item.sale_price ?? selected_item.avg_cost * (1 + marginPercentage / 100) ?? 0
    const baseUnit = Number(selected_item.unitMeasure?.unit) || 1
    row.unit_price = Number(((row.base_unit_price * Number(row.selected_measure.unit) * form.rate) / baseUnit).toFixed(2));
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

const addRow = () => {
    form.items.push({
        item_id: '', selected_item: '', quantity: '', unit_measure_id: '', selected_measure: '',
        available_measures: [], batch: '', color: null, expire_date: '', unit_price: '', base_unit_price: '',
        free: '', discount: '', size_id: '', selected_size: '', category_id: '', selected_category: '',
    })
}

const totalRows = computed(() => form.items.length)
const totalQuantity = computed(() => Number(form.items.reduce((acc, item) => acc + toNum(item.quantity, 0), 0).toFixed(decimalPlaces)))
const totalFree = computed(() => Number(form.items.reduce((acc, item) => acc + toNum(item.free, 0), 0).toFixed(decimalPlaces)))
const totalItemDiscount = computed(() => form.items.reduce((acc, item) => acc + toNum(item.discount, 0), 0))
const goodsTotal = computed(() => form.items.reduce((acc, item) => acc + (toNum(item.unit_price, 0) * toNum(item.quantity, 0)), 0))
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
            color: item.color || null,
            size_id: item.selected_size?.id ?? null,
            category_id: item.selected_category?.id ?? null,
        }));

    form.put(route('sale-quotations.update', saleQuotationData.value.id))
}

useFormGuard(form)
</script>

<template>
    <AppLayout :title="t('general.edit', { name: t('sale_quotation.sale_quotation') })">
        <FormPageToolbar
            back-route="sale-quotations.index"
            module="sale_quotations"
            :show-preferences="true"
            @preferences="showPreferencesPanel = true"
        />
        <FormPreferencesPanel module="sale_quotation"
            v-model:open="showPreferencesPanel"
            pref-group="sale_quotation"
            :prefs="salePrefs"
            :title="t('sale_quotation.sale_quotation')"
        />
        <form @submit.prevent="handleSubmit">
            <div class="mb-5 rounded-xl border border-violet-500 p-4 shadow-sm relative">
                <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
                    {{ t('general.edit', { name: t('sale_quotation.sale_quotation') }) }} #{{ saleQuotationData.number }}
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                    <NextSelect
                        :options="[]"
                        v-model="form.selected_ledger"
                        @update:modelValue="(value) => handleSelectChange('customer_id', value)"
                        label-key="name"
                        value-key="id"
                        :reduce="ledger => ledger"
                        :floating-text="t('ledger.customer.customer')"
                        is-required
                        :error="form.errors?.customer_id"
                        :searchable="true"
                        resource-type="ledgers"
                        :search-fields="['name', 'email', 'phone_no']"
                        :search-options="{ type: 'customer' }"
                    />
                    <NextInput is-required v-if="general_fields.number" :error="form.errors?.number" type="number" v-model="form.number" :label="t('general.bill_number')" />
                    <NextDate v-if="general_fields.date" v-model="form.date" :current-date="true" :error="form.errors?.date" :placeholder="t('general.enter', { text: t('general.date') })" :label="t('general.date')" />
                    <NextDate v-model="form.valid_until" :error="form.errors?.valid_until" :placeholder="t('general.enter', { text: t('sale_quotation.valid_until') })" :label="t('sale_quotation.valid_until')" />

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
                </div>
            </div>

            <div class="rounded-md border bg-card shadow-sm border-violet-500">
                <table class="w-full table-fixed min-w-[1200px] border-separate">
                    <thead class="bg-card sticky top-0 z-10">
                        <tr class="rounded-xl text-muted-foreground font-semibold text-sm text-violet-500">
                            <th class="px-1 py-1 w-5 text-center">#</th>
                            <th class="px-1 py-1 w-40 min-w-64">{{ t('item.item') }} <span class="text-red-500">*</span></th>
                            <th class="px-1 py-1 w-32" v-if="localColumns.batch">{{ t('general.batch') }}</th>
                            <th class="px-1 py-1 w-36" v-if="localColumns.expiry">{{ t('general.expire_date') }}</th>
                            <th class="px-1 py-1 w-16">{{ t('general.qty') }} <span class="text-red-500">*</span></th>
                            <th class="px-1 py-1 w-24" v-if="localColumns.measure">{{ t('general.unit') }}</th>
                            <th class="px-1 py-1 w-24">{{ t('general.price') }} <span class="text-red-500">*</span></th>
                            <th class="px-1 py-1 w-28" v-if="localColumns.size">{{ t('admin.size.size') }}</th>
                            <th class="px-1 py-1 w-32">{{ t('item.color') }}</th>
                            <th class="px-1 py-1 w-28" v-if="localColumns.category">{{ t('admin.category.category') }}</th>
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
                            <td>
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
                            <td v-if="localColumns.batch">
                                <NextInput v-model="item.batch" :disabled="!item?.selected_item" :error="form.errors?.[`item_list.${index}.batch`]" />
                            </td>
                            <td v-if="localColumns.expiry">
                                <NextDate v-model="item.expire_date" popover="top-left" :error="form.errors?.[`item_list.${index}.expire_date`]" />
                            </td>
                            <td>
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
                            <td>
                                <NextInput v-model="item.unit_price" :disabled="!item?.selected_item" type="number" step="any" inputmode="decimal" :error="form.errors?.[`item_list.${index}.unit_price`]" />
                            </td>
                            <td v-if="localColumns.size">
                                <NextSelect :options="sizes.data" v-model="item.selected_size" label-key="name" value-key="id" :show-arrow="false" :reduce="size => size" />
                            </td>
                            <td>
                                <NextSelect v-model="item.color" :options="colorOptions" label-key="name" value-key="id" :reduce="o => o.id" :disabled="!item?.selected_item" :id="`quotation_color_${index}`" :placeholder="t('general.select')" :show-arrow="false" :append-to-body="true" :error="form.errors?.[`item_list.${index}.color`]">
                                    <template #option="{ name, hex }"><span class="flex items-center gap-2"><span class="h-3.5 w-3.5 shrink-0 rounded-full border border-muted-foreground/40" :style="{ backgroundColor: hex }" /><span>{{ name }}</span></span></template>
                                    <template #selected-option="{ name, hex }"><span class="flex items-center gap-1.5"><span class="h-3 w-3 shrink-0 rounded-full border border-muted-foreground/40" :style="{ backgroundColor: hex }" /><span>{{ name }}</span></span></template>
                                </NextSelect>
                            </td>
                            <td v-if="localColumns.category">
                                <NextSelect :options="categories.data" v-model="item.selected_category" label-key="name" value-key="id" :show-arrow="false" :reduce="category => category" />
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
                            <td v-if="localColumns.batch"></td>
                            <td v-if="localColumns.expiry"></td>
                            <td class="text-center">{{ totalQuantity || 0 }}</td>
                            <td v-if="localColumns.measure"></td>
                            <td class="text-center">{{ goodsTotal || 0 }}</td>
                            <td v-if="localColumns.size"></td>
                            <td></td>
                            <td v-if="localColumns.category"></td>
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
                    <div class="text-sm font-semibold mb-3 text-violet-500">{{ t('sale_quotation.quotation_total') }}</div>
                    <div class="text-2xl font-bold text-violet-600 dark:text-violet-400">{{ fmtMoney(totalRowTotal) }}</div>
                </div>
            </div>

            <div class="mt-4">
                <NextTextarea v-model="form.note" :error="form.errors?.note" :label="t('general.note')" />
            </div>

            <SubmitButtons
                class="mt-4"
                :create-label="t('general.save')"
                :cancel-label="t('general.cancel')"
                :creating-label="t('general.saving')"
                :create-loading="form.processing"
                :show-create-and-new="false"
                module="sale_quotations"
                @cancel="$inertia.visit(route('sale-quotations.index'))"
            />
        </form>
    </AppLayout>
</template>
