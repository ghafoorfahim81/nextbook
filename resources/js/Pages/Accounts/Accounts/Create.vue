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
import FloatingLabel from "@/Components/next/FloatingLabel.vue";

// const form = reactive({
//     name: '',
//     number: '',
//     remark: '',
//     account_type_id: null,
//     currency_id: null,
//     transaction_type: '',
//     opening_amount: '',
// });

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

console.log('this is data',accountTypes)
const form = useForm({
    name: '',
    number: '',
    remark: '',
    account_type_id: '',
    currency_id: null,
    transaction_type: '',
    opening_amount: '',
    branch_id: '',
})

const transactionType = ['Credit','Debit'];

function handleSubmit() {
    console.log('Submitting form:', form);
    form.post('/chart-of-accounts', {
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
                    <NextInput placeholder="Number" :error="form.errors?.number" type="text" v-model="form.number" label="Number" />
                    <div class="relative z-100 w-full group dark:bg-slate-50 dark:text-slate-500">
                        <div>
                            <v-select
                                :options="accountTypes.data"
                                v-model="form.account_type_id"
                                :reduce="type => type.id"
                                label="name"
                                class="col-span-3"
                            />
                            <FloatingLabel :id="'type'" :label="`Account Type`"/>
                        </div>
                        <span v-if="form.errors?.[`openings.${index}.store_id`]" class="text-red-500 text-sm">
                          {{ form.errors?.[`openings.${index}.store_id`] }}
                        </span>
                    </div>
                        <NextTextarea
                            v-model="form.remark"
                            label="Description"
                            placeholder="Enter product description"
                        />
                </div>
                <span class="font-bold">Opening</span>
                <div class="mt-3 grid grid-cols-3 mb-3 gap-x-2 gap-y-5">
                    <NextInput placeholder="Amount" :error="form.errors?.name" type="number" v-model="form.opening_amount" label="Amount" />
                    <div class="relative z-100 w-full group dark:bg-slate-50 dark:text-slate-500">
                        <div>
                            <v-select
                                :options="transactionType"
                                v-model="form.transaction_type"
                                :reduce="type => type.id"
                                @input="form.transaction_type = $event"
                                class="col-span-3"
                            />
                            <FloatingLabel :id="'type'" :label="`Transaction Type`"/>
                        </div>
                        <span v-if="form.errors?.[`openings.${index}.store_id`]" class="text-red-500 text-sm">
                          {{ form.errors?.[`openings.${index}.store_id`] }}
                        </span>
                    </div>
                    <div class="relative z-100 w-full group dark:bg-slate-50 dark:text-slate-500">
                        <div>
                            <v-select
                                :options="currencies.data"
                                v-model="form.currency_id"
                                :reduce="currency => currency.id"
                                label="name"
                                class="col-span-3"
                            />
                            <FloatingLabel :id="'type'" :label="`Currency`"/>
                        </div>
                        <span v-if="form.errors?.[`openings.${index}.store_id`]" class="text-red-500 text-sm">
                          {{ form.errors?.[`openings.${index}.store_id`] }}
                        </span>
                    </div>
                </div>
            </form>
        <div>
            <Button type="submit" @click="handleSubmit" class="bg-blue-500 text-white">Submit</Button>
        </div>
    </AppLayout>
</template>
