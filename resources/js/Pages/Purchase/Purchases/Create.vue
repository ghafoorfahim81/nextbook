<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { h, ref, watch, onMounted, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/vue3';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import DiscountField from '@/Components/next/DiscountField.vue';

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
    items: [],
    measurement_units: [],
    stores: [],
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

const handleSelectChange = (field, value) => {
    if(field === 'currency_id') {
        form.rate = value.exchange_rate;
    }
    form[field] = value.id;
};


function handleSubmit() {
    form.post('/purchases', {
        onSuccess: () => {
            form.reset();
        }
    })
}
</script>

<template>
    <AppLayout title="Designations">
         <form @submit.prevent="handleSubmit">
            <div class="mb-5 grid grid-cols-3 mb-3 gap-x-2 gap-y-5">
                <NextSelect
                    :options="ledgers.data"
                    v-model="form.selected_supplier"
                    @update:modelValue="(value) => handleSelectChange('ledger_id', value)"
                    label-key="name"
                    value-key="id"
                    :reduce="ledger => ledger.id"
                    floating-text="Supplier"
                    :error="form.errors?.ledger_id"
                    :searchable="true"
                    resource-type="ledgers"
                    :search-fields="['name', 'email', 'phone_no']"
                    :search-options="{ type: 'supplier' }"
                />
                <NextInput placeholder="Number" :error="form.errors?.number" type="text" v-model="form.number" label="Number" />
                <NextInput placeholder="Date" :error="form.errors?.date" type="date" v-model="form.date" label="Date" />
                <div class="grid grid-cols-2 gap-2">
                    <NextSelect
                    :options="currencies.data"
                    v-model="form.selected_currency"
                    label-key="name"
                    value-key="id"
                    @update:modelValue="(value) => handleSelectChange('currency_id', value)"
                    :reduce="currency => currency.id"
                    floating-text="Currency"
                    :error="form.errors?.currency_id"
                    :searchable="true"
                    resource-type="currencies"
                    :search-fields="['name', 'code', 'symbol']"
                />
                <NextInput placeholder="Rate" :error="form.errors?.rate" type="number" v-model="form.rate" label="Rate"/>
                </div>

                <NextSelect
                    :options="salePurchaseTypes"
                    v-model="form.selected_sale_purchase_type"
                    @update:modelValue="(value) => handleSelectChange('sale_purchase_type_id', value)"
                    label-key="name"
                    value-key="id"
                    :reduce="salePurchaseType => salePurchaseType.id"
                    floating-text="Type"
                    :error="form.errors?.sale_purchase_type_id"
                />
                <DiscountField
                    v-model="form.discount"
                    v-model:discount-type="form.discount_type"
                    label="Bill Disc"
                    :error="form.errors?.discount"
                />

                <NextTextarea placeholder="Description" :error="form.errors?.description" v-model="form.description" label="Description" />

            </div>
         </form>

    </AppLayout>
</template>
