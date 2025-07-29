<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { h, ref } from 'vue';
import { Button } from '@/Components/ui/button';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from '@/Pages/Administration/Stores/CreateEditModal.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    stores: Object,
    branches: {
        type: Array,
        required: true,
    },
});
const isDialogOpen = ref(false)
const editingStore = ref(null)

const columns = ref([
    { key: 'id', label: 'ID' },
    { key: 'name', label: 'Name',sortable: true },
    { key: 'address', label: 'Address' },
    { key: 'actions', label: 'Actions' },
]);

const editItem = (item) => {
    editingStore.value = item
    isDialogOpen.value = true
}
const { deleteResource } = useDeleteResource()
const deleteItem = (id) => {
    deleteResource('stores.destroy', id, {
        title: 'Delete Store',
        description: 'This will permanently delete this category.',
        successMessage: 'Store deleted successfully.',
    })

};

</script>

<template>
    <AppLayout title="Categories">
        <div class="flex gap-2 items-center mb-4">
            <div class="ml-auto gap-3">
                <Button
                    @click="isDialogOpen = true"
                    variant="outline"
                    class="bg-gray-100 hover:bg-gray-200 dark:border-gray-50 dark:text-green-300"
                >
                    Add New
                </Button>
                <CreateEditModal
                    :isDialogOpen="isDialogOpen"
                    :editingItem="editingStore"
                    :stores="stores"
                    :branches="branches"
                    @update:isDialogOpen="isDialogOpen = $event"
                    @saved="() => { editingStore = null }"

                />
            </div>
        </div>
        <DataTable
            :items="stores"
            :columns="columns"
            @edit="editItem"
            @delete="deleteItem"
            :title="`Stores`"
            :url="`stores.index`"
        />
    </AppLayout>
</template>
