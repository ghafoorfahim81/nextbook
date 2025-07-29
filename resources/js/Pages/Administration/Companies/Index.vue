<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref } from 'vue';
import { Button } from '@/Components/ui/button';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from './CreateEditModal.vue';

const props = defineProps({
    companies: Object,
    branches: {
        type: Array,
        required: true,
    },
});

const isDialogOpen = ref(false);
const editingItem = ref(null);

const columns = ref([
    { key: 'name', label: 'Name', sortable: true },
    { key: 'legal_name', label: 'Legal Name' },
    { key: 'registration_number', label: 'Reg. Number' },
    { key: 'industry', label: 'Industry' },
    { key: 'type', label: 'Type' },
    { key: 'city', label: 'City' },
    { key: 'country', label: 'Country' },
    { key: 'actions', label: 'Actions' },
]);

const { deleteResource } = useDeleteResource();
const deleteItem = (id) => {
    deleteResource('companies.destroy', id, {
        title: 'Delete Company',
        description: 'This will permanently delete this company.',
        successMessage: 'Company deleted successfully.',
    });
};

const editItem = (item) => {
    editingItem.value = item;
    isDialogOpen.value = true;
};
</script>

<template>
    <AppLayout title="Companies">
        <div class="flex gap-2 items-center mb-4">
            <div class="ml-auto">
                <Button
                    @click="isDialogOpen = true"
                    variant="outline"
                    class="bg-gray-100 hover:bg-gray-200"
                >
                    Add New
                </Button>
            </div>
        </div>

        <DataTable
            :items="companies"
            :columns="columns"
            :title="`Companies`"
            :url="`companies.index`"
            @edit="editItem"
            @delete="deleteItem"
        />

        <CreateEditModal
            :isDialogOpen="isDialogOpen"
            :editingItem="editingItem"
            :branches="branches"
            @update:isDialogOpen="isDialogOpen = $event"
            @saved="() => { editingItem = null }"

        />
    </AppLayout>
</template>
