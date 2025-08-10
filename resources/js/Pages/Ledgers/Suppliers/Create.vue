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


const { currencies, accountTypes,branches } = defineProps({
    accountTypes: {
        type: Array,
        required: true,
    },
    currencies: {
        type: Array,
        required: true,
    },
    branches: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    name: '',
    code: '',
    phone_no: '',
    contact_person: '',
    email: '',
    address: '',
    currency_id: null,
    opening_currency_id: null,
    transaction_type: '',
    opening_amount: '',
    branch_id: '',
})

const transactionType = ['Credit','Debit'];

function handleSubmit() {
    form.post('/suppliers', {
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
                <NextInput placeholder="Enter Name" :error="form.errors?.name" type="text" v-model="form.name" label="Name" />
                <NextInput placeholder="Enter Code" :error="form.errors?.code" type="text" v-model="form.code" label="Code" />
                <NextInput placeholder="Enter Phone Number" :error="form.errors?.phone_no" type="text" v-model="form.phone_no" label="Phone Number" />
                <NextInput placeholder="Enter Contact Person" :error="form.errors?.contact_person" type="text" v-model="form.contact_person" label="Contact Person" />
                <NextInput placeholder="Enter Email" :error="form.errors?.email" type="text" v-model="form.email" label="Email" />
                <NextInput placeholder="Enter Address" :error="form.errors?.address" type="text" v-model="form.address" label="Address" />
                <div class="m-2">
                    <Label for="parent_id" class="text-nowrap">Currency</Label>
                    <v-select
                        :options="currencies.data"
                        v-model="form.currency_id"
                        :reduce="type => type.id"
                        label="name"
                        class="col-span-3"
                    />
                </div>
                <div class="m-2">
                    <Label for="parent_id" class="text-nowrap">Branch</Label>
                    <v-select
                        :options="branches.data"
                        v-model="form.branch_id"
                        :reduce="branch => branch.id"
                        label="name"
                        class="col-span-3"
                    />
                </div>
            </div>
            <span class="font-bold">Opening</span>
            <div class="mt-3 grid grid-cols-3 mb-3 gap-x-2 gap-y-5">
                <NextInput placeholder="Amount" :error="form.errors?.opening_amount" type="number" v-model="form.opening_amount" label="Amount" />
                <div class="m-2">
                    <Label for="parent_id" class="text-nowrap">Currency</Label>
                    <v-select
                        :options="transactionType"
                        v-model="form.transaction_type"
                        :reduce="trType => trType.id"
                        class="col-span-3"
                    />
                </div>
                <div class="m-2">
                    <Label for="parent_id" class="text-nowrap">Currency</Label>
                    <v-select
                        :options="currencies.data"
                        v-model="form.opening_currency_id"
                        :reduce="currency => currency.id"
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
