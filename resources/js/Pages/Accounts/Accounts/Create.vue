<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { reactive } from 'vue';
import { Button } from '@/Components/ui/button';
import InputField from '@/Components/next/InputField.vue';

const form = reactive({
    name: '',
    number: '',
    remark: '',
});

function handleSubmit() {
    console.log(form)
}
</script>

<template>
    <AppLayout title="Chart of Accounts">
        <div className="mb-5">
            <div className="grid grid-cols-3 mb-3 gap-x-2 gap-y-5">
                <InputField placeholder="Name" v-model="form.name" />
                <InputField placeholder="Number" v-model="form.number" />
                <InputField placeholder="Remark" v-model="form.remark" />
            </div>
        </div>
        <div>
            <Button type="submit" class="bg-blue-500 text-white">Submit</Button>
        </div>
    </AppLayout>
</template>
