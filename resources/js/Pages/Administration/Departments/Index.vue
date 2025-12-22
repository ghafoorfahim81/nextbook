<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from '@/Pages/Administration/Departments/CreateEditModal.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    items: Object,
})

const isDialogOpen = ref(false)
const editingDepartment = ref(null)
const { t } = useI18n()

const columns = computed(() => ([
    { key: 'id', label: 'ID' },
    { key: 'name', label: t('general.name'), sortable: true },
    { key: 'code', label: t('general.code'), sortable: true },
    {
        key: 'parent.name',
        label: t('admin.department.parent'),
        sortable: true,
        render: (row) => row.parent?.name ?? '-',
    },
    { key: 'actions', label: t('general.action') },
]))

const editItem = (item) => {
    editingDepartment.value = item
    isDialogOpen.value = true
}

const { deleteResource } = useDeleteResource()
const deleteItem = (id) => {
    deleteResource('departments.destroy', id, {
        title: t('general.delete', { name: t('admin.department.department') }),
        description: t('general.delete_description', { name: t('admin.department.department') }),
        successMessage: t('general.delete_success', { name: t('admin.department.department') }),
    })
}
</script>

<template>
    <AppLayout :title="t('admin.department.departments')">
        <CreateEditModal
            :isDialogOpen="isDialogOpen"
            :editingItem="editingDepartment"
            :departments="items"
            @update:isDialogOpen="(value) => {
                isDialogOpen = value;
                if (!value) editingDepartment = null;
            }"
            @saved="() => { editingDepartment = null }"
        />
        <DataTable
            :items="items"
            :columns="columns"
            @edit="editItem"
            @delete="deleteItem"
            @add="isDialogOpen = true"
            :title="t('admin.department.departments')"
            :url="`departments.index`"
            :showAddButton="true"
            :addTitle="t('admin.department.department')"
            :addAction="'modal'"
        />
    </AppLayout>
</template>
