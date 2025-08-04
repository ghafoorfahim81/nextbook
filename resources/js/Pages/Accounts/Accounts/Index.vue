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
    { key: 'number', label: 'Number' },
    { key: 'account_type.name', label: 'Account Type' },
    { key: 'opening_amount', label:"Opening" }, 
    { key: 'branch.name', label: 'Branch' },
    { key: 'remark', label: 'Remark' },
])
</script>

<template>
    <AppLayout title="Designations">
        <div class="flex gap-2 items-center">
            <div class="ml-auto gap-3">
                <Link :href="route('chart-of-accounts.create')">
                    <Button  variant="outline" class="bg-gray-100
                    hover:bg-gray-200 dark:border-gray-50 dark:text-green-300">Add New</Button>
                </Link>
                <CreateEditModal
                    :isDialogOpen="isDialogOpen"
                    :categories="items"
                    @update:isDialogOpen="isDialogOpen = $event"
                />
            </div>
        </div>
        <DataTable :items="items" :columns="columns" :title="`Chart of Accounts`" :url="`chart-of-accounts.index`" />

    </AppLayout>
</template>
