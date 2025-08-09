<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { h, ref } from 'vue';
import { Button } from '@/Components/ui/button';

import CreateEditModal from '@/Pages/Administration/Departments/CreateEditModal.vue';

const isDialogOpen = ref(false);


const showFilter = () => {
    showFilter.value = true;
}

const props = defineProps({
    items: Object,
})


console.log('accountssssss',props.items.data);

const columns = ref([
    { key: 'name', label: 'Name' },
    { key: 'code', label: 'Code' },
    { key: 'category', label: 'Category' },
    { key: 'measure', label: 'Unit' },
    { key: 'company', label: 'Company' },
    { key: 'purchase_price', label: 'Purchase Price' },
    { key: 'mrp_rate', label: 'MRP Rate' },
])
</script>

<template>
    <AppLayout title="Designations">
        <div class="flex gap-2 items-center">
            <div class="ml-auto gap-3">
                <Link :href="route('items.create')">
                    <Button  variant="outline" class="bg-gray-100
                    hover:bg-gray-200 dark:border-gray-50 dark:text-green-300">Add New</Button>
                </Link>

            </div>
        </div>
        <DataTable :items="items" :columns="columns" :title="`Items`" :url="`items.index`" />

    </AppLayout>
</template>
