<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { h, ref } from 'vue';
import { Button } from '@/Components/ui/button';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from '@/Pages/Administration/UnitMeasures/CreateEditModal.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    unitMeasures: Object,
});
const isDialogOpen = ref(false)
const editingBranch = ref(null)


const columns = ref([
    {
        key: 'quantity.quantity',
        label: 'metric',
        sortable: true,
        render: (row) => row.quantity?.quantity ?? '-',
    },
    {
        key: 'quantity.unit',
        label: 'Base Unit',
        sortable: true,
        render: (row) => row.quantity?.unit ?? '-',
    },
    { key: 'name', label: 'Name' },
    { key: 'unit', label: 'Unit' },
    { key: 'symbol', label: 'Symbol' },
    { key: 'actions', label: 'Actions' },
]);

const editItem = (item) => {
    editingBranch.value = item
    isDialogOpen.value = true
}
const { deleteResource } = useDeleteResource()
const deleteItem = (id) => {
    deleteResource('unit-measures.destroy', id, {
        title: 'Delete Branch',
        description: 'This will permanently delete this branch.',
        successMessage: 'Branch deleted successfully.',
    })

};

</script>

<template>
    <AppLayout title="Unit Measure">
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
                    @update:isDialogOpen="isDialogOpen = $event"
                    @saved="() => $inertia.reload()"
                />

            </div>
        </div>
        <DataTable
            :items="unitMeasures"
            :columns="columns"
            @edit="editItem"
            @delete="deleteItem"
            :title="`Unit Measures`"
            :url="`unit-measures.index`"
        />
    </AppLayout>
</template>
