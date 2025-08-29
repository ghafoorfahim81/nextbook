<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { h, ref } from 'vue';
import { Button } from '@/Components/ui/button';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from '@/Pages/Administration/Categories/CreateEditModal.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    categories: Object,
});
const isDialogOpen = ref(false)
const editingCategory = ref(null)


const columns = ref([
    { key: 'id', label: 'ID' },
    { key: 'name', label: 'Name',sortable: true },
    {
        key: 'parent.name',
        label: 'Parent',
        sortable: true,
        render: (row) => row.parent?.name ?? '-',
    },
    { key: 'remark', label: 'Remark' },
    { key: 'actions', label: 'Actions' },
]);

const editItem = (item) => {
    editingCategory.value = item
    isDialogOpen.value = true
}
const { deleteResource } = useDeleteResource()
const deleteItem = (id) => {
    deleteResource('categories.destroy', id, {
        title: 'Delete Category',
        description: 'This will permanently delete this category.',
        successMessage: 'Category deleted successfully.',
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
                    class="bg-primary text-white"
                >
                    Add New
                </Button>
                <CreateEditModal
                    :isDialogOpen="isDialogOpen"
                    :editingItem="editingCategory"
                    :categories="categories"
                    @update:isDialogOpen="isDialogOpen = $event"
                    @saved="() => { editingCategory = null }"

                />
            </div>
        </div>
        <DataTable
            :items="categories"
            :columns="columns"
            @edit="editItem"
            @delete="deleteItem"
            :title="`Categories`"
            :url="`categories.index`"
        />
    </AppLayout>
</template>
