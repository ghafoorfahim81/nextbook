<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import AddNewButton from '@/Components/next/AddNewButton.vue';
import { h, ref } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from '@/Pages/Administration/Categories/CreateEditModal.vue';
import { useI18n } from 'vue-i18n';
const props = defineProps({
    categories: Object,
});
const isDialogOpen = ref(false)
const editingCategory = ref(null)
const { t } = useI18n()
const columns = ref([
    { key: 'name', label: t('general.name'),sortable: true },
    {
        key: 'parent.name',
        label: t('admin.category.parent'),
        sortable: true,
        render: (row) => row.parent?.name,
    },
    { key: 'remark', label: t('general.remarks') },
    { key: 'actions', label: t('general.action') },
]);

const editItem = (item) => {
    editingCategory.value = item
    isDialogOpen.value = true
}
const { deleteResource } = useDeleteResource()
const deleteItem = (id) => {
    deleteResource('categories.destroy', id, {
        title: t('general.delete', { name: t('admin.category.category') }),
        description: t('general.delete_description', { name: t('admin.category.category') }),
        successMessage: t('general.delete_success', { name: t('admin.category.category') }),
    })

};

</script>

<template>
    <AppLayout :title="t('admin.category.categories')">
        <div class="flex gap-2 items-center mb-4">
            <div class="ml-auto gap-3">
                <AddNewButton
                    action="modal"
                    @modal-open="isDialogOpen = true"
                    variant="default"
                    :title="t('admin.category.category')"
                    class="bg-primary text-white"
                />
                <CreateEditModal
                    :isDialogOpen="isDialogOpen"
                    :editingItem="editingCategory"
                    :categories="categories"
                    @update:isDialogOpen="(value) => {
                        isDialogOpen = value;
                        if (!value) editingCategory = null;
                    }"
                    @saved="() => { editingCategory = null }"

                />
            </div>
        </div>
        <DataTable
            :items="categories"
            :columns="columns"
            @edit="editItem"
            @delete="deleteItem"
            :title="t('admin.category.categories')"
            :url="`categories.index`"
        />
    </AppLayout>
</template>
