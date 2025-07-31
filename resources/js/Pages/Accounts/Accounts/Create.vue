<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { reactive } from 'vue';
import { Button } from '@/Components/ui/button';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import {Separator} from "@/Components/ui/separator/index.js";
import NextTextarea from "@/Components/next/NextTextarea.vue";

const form = reactive({
    name: '',
    number: '',
    remark: '',
    account_type_id: null,
    parent_id: null,
});

const { accounts, accountTypes } = defineProps({
    accounts: {
        type: Array,
        required: true,
    },
    accountTypes: {
        type: Array,
        required: true,
    },
});

const transactionType = ['Credit','Debit'];

function handleSubmit() {
    console.log(form)
}
</script>

<template>
    <AppLayout title="Chart of Accounts">
            <form @submit.prevent="handleSubmit">
                <div class="mb-5 grid grid-cols-3 mb-3 gap-x-2 gap-y-5">
                    <NextInput placeholder="Name" :error="form.errors?.name" type="text" v-model="form.name" label="Name" />
                    <NextInput placeholder="Number" :error="form.errors?.number" type="text" v-model="form.number" label="Number" />
                    <NextSelect :options="accountTypes.data" v-model="form.account_type_id" labelText="Account Type" @input="form.account_type_id = $event" />
                    <Textarea placeholder="Remark" :error="form.errors?.remark" type="text" v-model="form.remark" label="Remark" />
                 </div>
                <span class="font-bold">Opening</span>
                <div class="mt-3 grid grid-cols-3 mb-3 gap-x-2 gap-y-5">
                    <NextInput placeholder="Amount" :error="form.errors?.name" type="number" v-model="form.name" label="Amount" />
                    <NextSelect :options="transactionType" v-model="form.account_type_id" labelText="Type" @input="form.account_type_id = $event" />
                </div>
            </form>
        <div>
            <Button type="submit" @click="handleSubmit" class="bg-blue-500 text-white">Submit</Button>
        </div>
    </AppLayout>
</template>
