<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { h, ref } from 'vue';
import { Button } from '@/Components/ui/button';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from '@/Pages/Accounts/AccountTypes/CreateEditModal.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    accountTypes: Object,
});
const isDialogOpen = ref(false)
const editingAccountType = ref(null)


const columns = ref([
    { key: 'id', label: 'ID' },
    { key: 'name', label: 'Name',sortable: true },
    { key: 'remark', label: 'Remark' },
    { key: 'actions', label: 'Actions' },
]);

const editItem = (item) => {
    editingAccountType.value = item
    isDialogOpen.value = true
}
const { deleteResource } = useDeleteResource()
const deleteItem = (id) => {
    deleteResource('account-types.destroy', id, {
        title: 'Delete Account type',
        description: 'This will permanently delete this account type.',
        successMessage: 'Account type deleted successfully.',
    })

};

</script>

<template>
    <AppLayout title="Account types">
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
                    :editingItem="editingAccountType"
                    @update:isDialogOpen="isDialogOpen = $event"
                    @saved="() => { editingAccountType = null }"
                />
            </div>
        </div>
        <DataTable
            :items="accountTypes"
            :columns="columns"
            @edit="editItem"
            @delete="deleteItem"
            :title="`Account types`"
            :url="`account-types.index`"
        />
    </AppLayout>
</template>
