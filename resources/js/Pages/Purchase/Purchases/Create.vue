<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { h, ref, watch, onMounted, computed } from 'vue';
import axios from 'axios'
import { useForm } from '@inertiajs/vue3';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import DiscountField from '@/Components/next/DiscountField.vue';
import { useI18n } from 'vue-i18n';
import { Trash2, Trash } from 'lucide-vue-next';
import TransactionSummary from '@/Components/next/TransactionSummary.vue';
import DiscountSummary from '@/Components/next/DiscountSummary.vue';
import TaxSummary from '@/Components/next/TaxSummary.vue';
const { t } = useI18n();

const showFilter = () => {
    showFilter.value = true;
}

const props = defineProps({
    ledgers: Object,
    salePurchaseTypes: Object,
    currencies: Object,
    items: Object,
    measurementUnits: Object,
    stores: Object,
    unitMeasures: Object,
})

const form = useForm({
    number: '',
    ledger_id: '',
    date: '',
    currency_id: '',
    rate: '',
    sale_purchase_type_id: '',
    selected_currency: '',
    selected_supplier: '',
    selected_sale_purchase_type: '',
    discount: '',
    discount_type: 'percentage',
    description: '',
    status: '',
    store_id: '',
    selected_store: '',
    items: [
        {
            item_id: '',
            quantity: '',
            unit_measure_id: '',
            batch: '',
            expire_date: '',
            purchase_price: '',
            selected_measure: '',
            discount: '',
            free: '',
            tax: '',
        },
        {
            item_id: '',
            quantity: '',
            unit_measure_id: '',
            batch: '',
            expire_date: '',
            purchase_price: '',
            selected_measure: '',
            discount: '',
            free: '',
            tax: '',
        },
        {
            item_id: '',
            quantity: '',
            unit_measure_id: '',
            batch: '',
            expire_date: '',
            purchase_price: '',
            selected_measure: '',
            discount: '',
            free: '',
            tax: '',
        },
    ],
})

console.log('salePurchaseTypes', props.salePurchaseTypes);


// Set base currency as default
watch(() => props.currencies?.data, (currencies) => {
    if (currencies && !form.currency_id) {
        const baseCurrency = currencies.find(c => c.is_base_currency);
        if (baseCurrency) {
            form.selected_currency = baseCurrency;
            form.rate = baseCurrency.exchange_rate;
        }
    }
}, { immediate: true });

// Watch for currency changes and automatically update rate
watch(() => form.currency_id, (newCurrencyId) => {
    if (newCurrencyId && props.currencies?.data) {
        const selectedCurrency = props.currencies.data.find(currency => currency.id === newCurrencyId);
        if (selectedCurrency && selectedCurrency.exchange_rate) {
            form.rate = selectedCurrency.exchange_rate;
        }
    }
});

watch(() => props.salePurchaseTypes, (salePurchaseTypes) => {
    if (salePurchaseTypes && !form.selected_sale_purchase_type) {
        const baseSalePurchaseType = salePurchaseTypes.find(c => c.id === 'cash');
        if (baseSalePurchaseType) {
            form.selected_sale_purchase_type = baseSalePurchaseType;
        }
    }
}, { immediate: true });

watch(() => props.stores.data, (stores) => {
    if (stores && !form.selected_store) {
        const baseStore = stores.find(c => c.is_main === true);
        if (baseStore) {
            form.selected_store = baseStore;
            form.store_id = baseStore.id;
        }
    }
}, { immediate: true });

const handleSelectChange = (field, value) => {
    if(field === 'currency_id') {
        form.rate = value.exchange_rate;
    }
    form[field] = value;
};


function handleSubmit() {
    form.post('/purchases', {
        onSuccess: () => {
            form.reset();
        }
    })
}

const handleItemChange = async (index, selectedItem) => {
    console.log('sss',selectedItem)
    try {

        const itemId = selectedItem
        const storeId = form.store_id
        if (!itemId || !storeId) return
        const { data } = await axios.get(`/purchase-item-change`, { params: { item_id: itemId, store_id: storeId } })
        const row = form.items[index]
        if (!row) return
        row.on_hand = data.onHand
        row.selected_measure = data.measure
        row.purchase_price = data.purchasePrice
    } catch (e) {
        console.error(e)
    }
}

console.log('items', props.items);
</script>

<template>
    <AppLayout :title="t('general.create', { name: t('purchase.purchase') })">
         <form @submit.prevent="handleSubmit">
            <div class="mb-5 rounded-xl border bg-card p-4 shadow-sm relative ">
            <div class="absolute -top-3 left-3 bg-card px-2 text-sm font-semibold text-muted-foreground">{{ t('general.create', { name: t('purchase.purchase') }) }}</div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                <NextSelect
                    :options="ledgers.data"
                    v-model="form.selected_supplier"
                    @update:modelValue="(value) => handleSelectChange('ledger_id', value)"
                    label-key="name"
                    value-key="id"
                    :reduce="ledger => ledger.id"
                    floating-text="t('general.supplier')"
                    :error="form.errors?.ledger_id"
                    :searchable="true"
                    resource-type="ledgers"
                    :search-fields="['name', 'email', 'phone_no']"
                    :search-options="{ type: 'supplier' }"
                />
                <NextInput placeholder="Number" :error="form.errors?.number" type="number" v-model="form.number" :label="t('general.number')" />
                <NextInput placeholder="Date" :error="form.errors?.date" type="date" v-model="form.date" :label="t('general.date')" />
                <div class="grid grid-cols-2 gap-2">
                    <NextSelect
                    :options="currencies.data"
                    v-model="form.selected_currency"
                    label-key="code"
                    value-key="id"
                    @update:modelValue="(value) => handleSelectChange('currency_id', value)"
                    :reduce="currency => currency.id"
                   :floating-text="t('admin.currency.currency')"
                    :error="form.errors?.currency_id"
                    :searchable="true"
                    resource-type="currencies"
                    :search-fields="['name', 'code', 'symbol']"
                />
                <NextInput placeholder="Rate" :error="form.errors?.rate" type="number" v-model="form.rate" :label="t('general.rate')"/>
                </div>

                <NextSelect
                    :options="salePurchaseTypes"
                    v-model="form.selected_sale_purchase_type"
                    @update:modelValue="(value) => handleSelectChange('sale_purchase_type_id', value)"
                    label-key="name"
                    value-key="id"
                    :reduce="salePurchaseType => salePurchaseType.id"
                    :floating-text="t('general.type')"
                    :error="form.errors?.sale_purchase_type_id"
                />
                <NextSelect
                    :options="stores.data"
                    v-model="form.selected_store"
                    @update:modelValue="(value) => handleSelectChange('store_id', value)"
                    label-key="name"
                    value-key="id"
                    :reduce="store => store.id"
                    :floating-text="t('admin.store.store')"
                    :error="form.errors?.store_id"
                />
            </div>
            </div>
            <div class="rounded-xl border bg-card p-2 shadow-sm overflow-x-auto">
                <table class="w-full table-fixed min-w-[1000px] purchase-table border-separate border-spacing-y-2">
                    <thead>
                        <tr>
                            <th class="sticky top-0 backdrop-blur px-1 py-1 w-5 min-w-5">#</th>
                            <th class="sticky top-0 backdrop-blur px-1 py-1 w-40 min-w-64">{{ t('item.item') }}</th>
                            <th class="sticky top-0 backdrop-blur px-1 py-1 w-32">{{ t('general.batch') }}</th>
                            <th class="sticky top-0 backdrop-blur px-1 py-1 w-24">{{ t('general.expire_date') }}</th>
                            <th class="sticky top-0 backdrop-blur px-1 py-1 w-16">{{ t('general.qty') }}</th>
                            <th class="sticky top-0 backdrop-blur px-1 py-1 w-24">{{ t('general.on_hand') }}</th>
                            <th class="sticky top-0 backdrop-blur px-1 py-1 w-24">{{ t('general.unit') }}</th>
                            <th class="sticky top-0 backdrop-blur px-1 py-1 w-24">{{ t('general.price') }}</th>
                            <th class="sticky top-0 backdrop-blur px-1 py-1 w-24">{{ t('general.discount') }}</th>
                            <th class="sticky top-0 backdrop-blur px-1 py-1 w-16">{{ t('general.free') }}</th>
                            <th class="sticky top-0 backdrop-blur px-1 py-1 w-16">{{ t('general.tax') }}</th>
                            <th class="sticky top-0 backdrop-blur px-1 py-1 w-16">{{ t('general.total') }}</th>
                            <th class="sticky top-0 backdrop-blur px-1 py-1 w-10 min-w-10 text-center">
                                <Trash2 class="w-4 h-4 cursor-pointer text-red-500 inline" />
                            </th>
                        </tr>
                    </thead>
                    <tbody class="p-2">
                        <tr v-for="(item, index) in form.items" :key="item.id" class="hover:bg-muted/40 transition-colors">
                            <td class="px-1 py-2 align-top w-5">{{ index + 1 }}</td>
                            <td>
                                <NextSelect
                                    :options="items.data"
                                    v-model="item.item_id"
                                    @update:modelValue="(value) => handleItemChange(index, value)"
                                    label-key="name"
                                    value-key="id"
                                    :reduce="item => item.id"
                                />
                            </td>
                            <td>
                                <NextInput
                                    v-model="item.batch"
                                    type="text"
                                    :error="form.errors?.batch"
                                />
                            </td>
                            <td>
                                <NextInput
                                    v-model="item.expire_date"
                                    type="date"
                                    :error="form.errors?.expire_date"
                                />
                            </td>
                            <td>
                                <NextInput
                                    v-model="item.quantity"
                                    type="number"
                                    inputmode="decimal"
                                    :error="form.errors?.quantity"
                                />
                            </td>
                            <td class="text-center">
                                {{ item.on_hand }}
                                <!-- onhand  -->
                            </td>
                            <td>
                                <NextSelect
                                    :options="unitMeasures.data"
                                    v-model="item.selected_measure"
                                    label-key="name"
                                    value-key="id"
                                    :reduce="unit => unit.id"
                                />
                            </td>
                            <td>
                                <NextInput
                                    v-model="item.purchase_price"
                                    type="number"
                                    inputmode="decimal"
                                    :error="form.errors?.purchase_price"
                                />
                            </td>
                            <td>
                                <NextInput
                                    v-model="item.discount"
                                    type="number"
                                    inputmode="decimal"
                                    :error="form.errors?.discount"
                                />
                            </td>
                            <td>
                                <NextInput
                                    v-model="item.free"
                                    type="number"
                                    inputmode="decimal"
                                    :error="form.errors?.free"
                                />
                            </td>
                            <td>
                                <NextInput
                                    v-model="item.tax"
                                    type="number"
                                    inputmode="decimal"
                                    :error="form.errors?.tax"
                                />
                            </td>
                            <td>
                                <NextInput
                                    v-model="item.total"
                                    type="number"
                                    inputmode="decimal"
                                    :error="form.errors?.total"
                                />
                            </td>
                            <td class="w-10 text-center">
                                <Trash class="w-4 h-4 cursor-pointer text-red-500 inline" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-5 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-2 items-start">
                <DiscountSummary :summary="form.summary" />
                <TaxSummary :summary="form.summary" />
                <div class="rounded-xl p-4">
                    <div class="text-lg font-semibold mb-3">Bill Disc</div>
                    <DiscountField
                        v-model="form.discount"
                        v-model:discount-type="form.discount_type"
                        :error="form.errors?.discount"
                    />
                </div>
                <TransactionSummary :summary="form.summary" />
            </div>

         </form>

    </AppLayout>
</template>

<style scoped>
.purchase-table thead th {
    font-weight: 200;
    font-size: 15px; text-align: left;
    background-color: hsl(var(--primary) / 0.06);
    border-bottom: 1px solid hsl(var(--border));
    padding: 0.5rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;

}
</style>
