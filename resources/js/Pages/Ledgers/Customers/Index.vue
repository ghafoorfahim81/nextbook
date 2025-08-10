<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { h, ref } from 'vue';
import { Button } from '@/Components/ui/button';

const isDialogOpen = ref(false);

const showFilter = () => {
    showFilter.value = true;
}

const props = defineProps({
    customers: Object,
})

const columns = ref([
    { key: 'name', label: 'Name' },
    { key: 'code', label: 'Code' },
    { key: 'contact_person', label:"Contact Person" },
    { key: 'phone_no', label: 'Phone Number' },
    { key: 'email', label: 'Email' },
])
</script>

<template>
    <AppLayout title="Designations">
        <div class="flex gap-2 items-center">
            <div class="ml-auto gap-3">
                <Link :href="route('customers.create')">
                    <Button  variant="outline" class="bg-gray-100
                    hover:bg-gray-200 dark:border-gray-50 dark:text-green-300">Add New</Button>
                </Link>

            </div>
        </div>
        <DataTable :items="customers" :columns="columns" :title="`Customers`" :url="`customers.index`" />

    </AppLayout>
</template>
