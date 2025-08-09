<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { reactive } from 'vue';
import { Button } from '@/Components/ui/button';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import {Separator} from "@/Components/ui/separator/index.js";
import NextTextarea from "@/Components/next/NextTextarea.vue";
import {useForm} from "@inertiajs/vue3";
import {Label} from "@/Components/ui/label/index.js";

const { branches, stores, unitMeasures, categories, companies } = defineProps({

    branches: {
        type: Array,
        required: true,
    },
    stores: {
        type: Array,
        required: true,
    },
    unitMeasures: {
        type: Array,
        required: true,
    },
    categories: {
        type: Array,
        required: true,
    },
    companies: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    name: '',
    code: '',
    generic_name: '',
    packing: '',
    remark: '',
    branch_id: null,
    store_id: null,
    colors: '',
    size: '',
    barcode: '',
    unit_measure_id: null,
    minimum_stock: '',
    maximum_stock: '',
    purchase_price: '',
    company_id: null,
    category_id: null,
    branch_id: null,
    cost: '',
    mrp_rate: '',
    rate_a: '',
    rate_b: '',
    rate_c: '',
    rack_no: '',
    photo: '',
    opening:{
        amount: '',
        batch: '',
        expire_date: '',
        store_id: null
    }
})

const transactionType = ['Credit','Debit'];

function handleSubmit() {
    console.log('Submitting form:', form);
    form.post('/items', {
        onSuccess: () => {
            emit('saved')
            form.reset();
            closeModal()
        },
    })
}
</script>

<template>
    <AppLayout title="Chart of Accounts">
        <form @submit.prevent="handleSubmit">
            <div class="mb-5 grid grid-cols-3 mb-3 gap-x-2 gap-y-5">
                <NextInput placeholder="Name" :error="form.errors?.name" type="text" v-model="form.name" label="Name" />
                <NextInput placeholder="code" :error="form.errors?.code" type="text" v-model="form.code" label="Code" />
                <NextInput placeholder="Generic Name" :error="form.errors?.generic_name" type="text" v-model="form.generic_name" label="Generic Name" />
                <NextInput placeholder="Packing" :error="form.errors?.packing" type="text" v-model="form.packing" label="Packing" />
                <NextInput placeholder="Colors" :error="form.errors?.colors" type="text" v-model="form.colors" label="Colors" />
                <NextInput placeholder="Size" :error="form.errors?.size" type="text" v-model="form.size" label="Size" />
                <NextInput placeholder="Photo" :error="form.errors?.photo" type="text" v-model="form.photo" label="Photo" />
                <div class="m-2">
                    <Label for="parent_id" class="text-nowrap">Unit Measure</Label>
                    <v-select
                        :options="unitMeasures.data"
                        v-model="form.unit_measure_id"
                        :reduce="store => store.id"
                        label="name"
                        class="col-span-3"
                    />
                </div>
                <div class="m-2">
                    <Label for="parent_id" class="text-nowrap">Category</Label>
                    <v-select
                        :options="categories.data"
                        v-model="form.category_id"
                        :reduce="store => store.id"
                        label="name"
                        class="col-span-3"
                    />
                </div>
                <div class="m-2">
                    <Label for="parent_id" class="text-nowrap">Company</Label>
                    <v-select
                        :options="companies.data"
                        v-model="form.company_id"
                        :reduce="company => company.id"
                        label="name"
                        class="col-span-3"
                    />
                </div>
                <NextInput placeholder="Minimum Stock" :error="form.errors?.minimum_stock" type="number" v-model="form.minimum_stock" label="Minimum Stock" />
                <NextInput placeholder="Maximum Stock" :error="form.errors?.maximum_stock" type="number" v-model="form.maximum_stock" label="Maximum Stock" />
                <NextInput placeholder="Purchase Price" :error="form.errors?.purchase_price" type="number" v-model="form.purchase_price" label="Purchase Price" />
                <NextInput placeholder="Cost" :error="form.errors?.cost" type="number" v-model="form.cost" label="Cost" />
                <NextInput placeholder="MRP Rate" :error="form.errors?.mrp_rate" type="number" v-model="form.mrp_rate" label="MRP Rate" />
                <NextInput placeholder="Rate A" :error="form.errors?.rate_a" type="number" v-model="form.rate_a" label="Rate A" />
                <NextInput placeholder="Rate B" :error="form.errors?.rate_b" type="number" v-model="form.rate_b" label="Rate B" />
                <NextInput placeholder="Rate C" :error="form.errors?.rate_c" type="number" v-model="form.rate_c" label="Rate C" />
                <NextInput placeholder="Barcode" :error="form.errors?.barcode" type="text" v-model="form.barcode" label="Barcode" />
                <NextInput placeholder="Rack No" :error="form.errors?.rack_no" type="text" v-model="form.rack_no" label="Rack No" />
                <NextInput placeholder="Fast Search" :error="form.errors?.fast_search" type="text" v-model="form.fast_search" label="Fast Search" />
                <div>
                    <Label for="parent_id" class="text-nowrap">Branch</Label>
                    <v-select
                        :options="branches.data"
                        v-model="form.branch_id"
                        :reduce="brand => brand.id"
                        label="name"
                        class="col-span-3"
                    />
                </div>
                <NextInput placeholder="Description" :error="form.errors?.description" type="text" v-model="form.description" label="Description" />

            </div>
            <span class="font-bold">Opening</span>
            <div class="mt-3 grid grid-cols-4 mb-3 gap-x-2 gap-y-5">
                <NextInput placeholder="Batch" :error="form.errors?.batch" type="text" v-model="form.opening.batch" label="Batch" />
                <NextInput placeholder="Expire Date" :error="form.errors?.amount" type="number" v-model="form.amount" label="Expire Date" />
                <NextInput placeholder="Amount" :error="form.errors?.amount" type="number" v-model="form.amount" label="Amount" />

                <div class="m-2">
                    <Label for="parent_id" class="text-nowrap">Store</Label>
                    <v-select
                        :options="stores.data"
                        v-model="form.opening.store_id"
                        :reduce="store => store.id"
                        label="name"
                        class="col-span-3"
                    />
                </div>
            </div>
        </form>
        <div>
            <Button type="submit" @click="handleSubmit" class="bg-blue-500 text-white">Submit</Button>
        </div>
    </AppLayout>
</template>
