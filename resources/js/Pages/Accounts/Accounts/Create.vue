<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { reactive } from 'vue';
import { Button } from '@/Components/ui/button';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';

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

function handleSubmit() {
    console.log(form)
}
</script>

<template>
    <AppLayout title="Chart of Accounts">
        <div className="mb-5">
            <form @submit.prevent="handleSubmit" class="grid grid-cols-3 mb-3 gap-x-2 gap-y-5">
                <NextInput placeholder="Name" type="text" v-model="form.name" label="Name" />
                <NextInput placeholder="Number" type="text" v-model="form.number" label="Number" />
                <NextSelect :options="accounts.data" v-model="form.parent_id" labelText="Parent"  />
                <NextSelect :options="accountTypes.data" v-model="form.account_type_id" labelText="Account Type" @input="form.account_type_id = $event" />
                <NextInput placeholder="Remark" type="text" v-model="form.remark" label="Remark" />
            </form>
        </div> 
        <div>
            <Button type="submit" class="bg-blue-500 text-white">Submit</Button>
        </div>
    </AppLayout>
</template>
