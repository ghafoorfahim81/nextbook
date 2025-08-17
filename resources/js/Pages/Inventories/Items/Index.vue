<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { h, ref } from 'vue';
import { Button } from '@/Components/ui/button';
import {useDeleteResource} from "@/composables/useDeleteResource.js";
const showFilter = () => {
    showFilter.value = true;
}

const props = defineProps({
    items: Object,
})

console.log('this is items', props.items.data);

const columns = ref([
    { key: 'name', label: 'Name' },
    { key: 'code', label: 'Code' },
    { key: 'category', label: 'Category' },
    { key: 'measure', label: 'Unit' },
    { key: 'company', label: 'Company' },
    { key: 'cost', label: 'Cost' },
    { key: 'quantity', label: 'Quantity' },
    { key: 'mrp_rate', label: 'Rate' },
    { key: 'actions', label: 'Actions' },

])

const editItem = (item) => {
    window.location.href = `/items/${item.id}/edit`;
}

const { deleteResource } = useDeleteResource()
const deleteItem = (id) => {
    deleteResource('items.destroy', id, {
        title: 'Delete Item',
        description: 'This will permanently delete this Item.',
        successMessage: 'Item deleted successfully.',
    })

};

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
        <DataTable :items="items" :columns="columns"
                   @delete="deleteItem"
                   @edit="editItem"
                   :title="`Items`" :url="`items.index`" />

    </AppLayout>
</template>
