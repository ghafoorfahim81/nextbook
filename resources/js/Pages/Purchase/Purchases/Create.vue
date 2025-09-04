<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { h, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/vue3';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
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
    supplier_id: '',
    date: '',
    sale_purchase_type_id: '',
    discount: '',
    discount_type: 'percentage',
    description: '',
    status: '',
    items: [],
    measurement_units: [],
    stores: [],
})


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
                    v-model="form.supplier_id"
                    :reduce="ledger => ledger.id"
                    floating-text="Supplier"
                    :error="form.errors?.supplier_id"
                    :searchable="true"
                    resource-type="ledgers"
                    :search-fields="['name', 'email', 'phone_no']"
                    :search-options="{ type: 'supplier' }"
                />
                <NextInput placeholder="Number" :error="form.errors?.number" type="text" v-model="form.number" label="Number" />
                <NextInput placeholder="Date" :error="form.errors?.date" type="date" v-model="form.date" label="Date" />
                <NextSelect
                    :options="currencies.data"
                    v-model="form.currency_id"
                    :reduce="currency => currency.id"
                    floating-text="Currency"
                    :error="form.errors?.currency_id"
                    :searchable="true"
                    resource-type="currencies"
                    :search-fields="['name', 'code', 'symbol']"
                />
                <NextSelect
                    :options="salePurchaseTypes"
                    v-model="form.sale_purchase_type_id"
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
