<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { h, ref } from 'vue';
import { Button } from '@/Components/ui/button';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from '@/Pages/Administration/Branches/CreateEditModal.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    branches: Object,
});
const isDialogOpen = ref(false)
const editingBranch = ref(null)


const columns = ref([
    { key: 'id', label: 'ID' },
    { key: 'name', label: 'Name',sortable: true },
    {
        key: 'parent.name',
        label: 'Parent',
        sortable: true,
        render: (row) => row.parent?.name ?? '-',
    },
    { key: 'location', label: 'Location' },
    { key: 'sub_domain', label: 'Sub Domain' },
    { key: 'remark', label: 'Remark' },
    { key: 'actions', label: 'Actions' },
]);

const editItem = (item) => {
    editingBranch.value = item
    isDialogOpen.value = true
}
const { deleteResource } = useDeleteResource()
const deleteItem = (id) => {
    deleteResource('branches.destroy', id, {
        title: 'Delete Branch',
        description: 'This will permanently delete this branch.',
        successMessage: 'Branch deleted successfully.',
    })

};

</script>

<template>
    <AppLayout title="Branches">
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
                    :editingItem="editingBranch"
                    :branches="branches"
                    @update:isDialogOpen="(value) => {
                        isDialogOpen = value;
                        if (!value) editingBranch = null;
                    }"
                    @saved="() => { editingBranch = null }"
                />

            </div>
        </div>
        <DataTable
            :items="branches"
            :columns="columns"
            @edit="editItem"
            @delete="deleteItem"
            :title="`Branches`"
            :url="`branches.index`"
        />
    </AppLayout>
</template>
