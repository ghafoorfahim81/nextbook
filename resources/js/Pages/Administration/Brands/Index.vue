<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref } from 'vue';
import { Button } from '@/Components/ui/button';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from './CreateEditModal.vue';

const props = defineProps({
    brands: Object,
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
    deleteResource('brands.destroy', id, {
        title: 'Delete Brand',
        description: 'This will permanently delete this brand.',
        successMessage: 'Brand deleted successfully.',
    });
};

const editItem = (item) => {
    editingItem.value = item;
    isDialogOpen.value = true;
};
</script>

<template>
    <AppLayout title="Brands">
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
            :items="brands"
            :columns="columns"
            :title="`Brands`"
            :url="`brands.index`"
            @edit="editItem"
            @delete="deleteItem"
        />

        <CreateEditModal
            :isDialogOpen="isDialogOpen"
            :editingItem="editingItem"
            :branches="branches"
            @update:isDialogOpen="(value) => {
                isDialogOpen = value;
                if (!value) editingItem = null;
            }"
            @saved="() => { editingItem = null }"

        />
    </AppLayout>
</template>
