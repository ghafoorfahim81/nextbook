<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { h, ref } from 'vue';
import { Button } from '@/Components/ui/button';

const showFilter = () => {
    showFilter.value = true;
}

const props = defineProps({
    purchases: Object,
})

const columns = ref([
    { key: 'number', label: 'Number' },
    { key: 'supplier', label: 'Supplier' },
    { key: 'date', label:"Date" }, 
    { key: 'discount', label: 'Discount' },
    { key: 'discount_type', label: 'Discount Type' },
    { key: 'type', label: 'Type' },
    { key: 'description', label: 'Description' },
    { key: 'status', label: 'Status' },
])
</script>

<template>
    <AppLayout title="Designations">
        <div class="flex gap-2 items-center">
            <div class="ml-auto gap-3">
                <Link :href="route('purchases.create')">
                    <Button  variant="outline" class="bg-gray-100
                    hover:bg-gray-200 dark:border-gray-50 dark:text-green-300">Add New</Button>
                </Link>

            </div>
        </div>
        <DataTable :items="purchases" :columns="columns" :title="`Purchases`" :url="`purchases.index`" />

    </AppLayout>
</template>
