<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { h, ref } from 'vue';
import { Button } from '@/Components/ui/button';

import CreateEditModal from '@/Pages/Administration/Departments/CreateEditModal.vue';
import {useDeleteResource} from "@/composables/useDeleteResource.js";

const isDialogOpen = ref(false);


const showFilter = () => {
    showFilter.value = true;
}

const props = defineProps({
    items: Object,
})

const editItem = (item) => {
    window.location.href = `/chart-of-accounts/${item.id}/edit`;
}

const { deleteResource } = useDeleteResource()
const deleteItem = (id) => {
    deleteResource('chart-of-accounts.destroy', id, {
        title: 'Delete Account?',
        description: 'This will permanently delete this Item and may has some transactions, this action cannot be undone.',
        successMessage: 'Item deleted successfully.',
    })

};

const columns = ref([
    { key: 'name', label: 'Name' },
    { key: 'number', label: 'Number' },
    { key: 'account_type.name', label: 'Account Type' },
    { key: 'balance', label:"Balance" },
    { key: 'branch.name', label: 'Branch' },
    { key: 'actions', label: 'Actions' },
])
</script>

<template>
    <AppLayout title="Designations">
        <div class="flex gap-2 items-center">
            <div class="ml-auto gap-3">
                
                <CreateEditModal
                    :isDialogOpen="isDialogOpen"
                    :categories="items"
                    @update:isDialogOpen="isDialogOpen = $event"
                />
            </div>
        </div>
        <DataTable :items="items" :columns="columns"
                   @delete="deleteItem"
                   @edit="editItem"
                   :title="`Chart of Accounts`" :url="`chart-of-accounts.index`" />

    </AppLayout>
</template>
