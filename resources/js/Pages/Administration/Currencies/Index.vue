<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { h, ref } from 'vue';
import { Button } from '@/Components/ui/button';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from '@/Pages/Administration/Currencies/CreateEditModal.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    currencies: Object,
    branches: {
        type: Array,
        required: true,
    },
});
const isDialogOpen = ref(false)
const editingCurrency = ref(null)

console.log('props.currencies.data', props.currencies.data);
const columns = ref([
    { key: 'id', label: 'ID' },
    { key: 'name', label: 'Name',sortable: true },
    { key: 'code', label: 'Code', sortable: true },
    { key: 'exchange_rate', label: 'Exchange Rate',sortable: true },
    { key: 'symbol', label: 'Symbol' },
    { key: 'format', label: 'Format' },
    { key: 'actions', label: 'Actions' },
]);

const editItem = (item) => {
    editingCurrency.value = item
    isDialogOpen.value = true
}
const { deleteResource } = useDeleteResource()
const deleteItem = (id) => {
    deleteResource('currencies.destroy', id, {
        title: 'Delete Currency',
        description: 'This will permanently delete this branch.',
        successMessage: 'Currency deleted successfully.',
    })

};

</script>

<template>
    <AppLayout title="Currencies">
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
                    :editingItem="editingCurrency"
                    :currencies="currencies"
                    :branches="branches"
                    @update:isDialogOpen="isDialogOpen = $event"
                    @saved="() => { editingCurrency = null }"
                />

            </div>
        </div>
        <DataTable
            :items="currencies"
            :columns="columns"
            @edit="editItem"
            @delete="deleteItem"
            :title="`Currencies`"
            :url="`currencies.index`"
        />
    </AppLayout>
</template>
