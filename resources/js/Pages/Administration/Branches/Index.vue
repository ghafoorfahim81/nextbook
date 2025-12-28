<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from '@/Pages/Administration/Branches/CreateEditModal.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    branches: Object,
});
const isDialogOpen = ref(false)
const editingBranch = ref(null)
const { t } = useI18n()

const columns = computed(() => ([
    { key: 'name', label: t('general.name'), sortable: true },
    {
        key: 'parent.name',
        label: t('admin.shared.parent'),
        sortable: true,
        render: (row) => row.parent?.name ?? '-',
    },
    { key: 'location', label: t('admin.branch.location') },
    { key: 'sub_domain', label: t('admin.branch.sub_domain') },
    { key: 'remark', label: t('admin.shared.remark') },
    { key: 'actions', label: t('general.action') },
]));

const editItem = (item) => {
    editingBranch.value = item
    isDialogOpen.value = true
}
const { deleteResource } = useDeleteResource()
const deleteItem = (id) => {
    deleteResource('branches.destroy', id, {
        title: t('general.delete', { name: t('admin.branch.branch') }),
        description: t('general.delete_description', { name: t('admin.branch.branch') }),
        successMessage: t('general.delete_success', { name: t('admin.branch.branch') }),
    })

};

</script>

<template>
    <AppLayout :title="t('admin.branch.branches')">
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
        <DataTable
            can="branches"
            :items="branches"
            :columns="columns"
            @edit="editItem"
            @delete="deleteItem"
            @add="isDialogOpen = true"
            :title="t('admin.branch.branches')"
            :url="`branches.index`"
            :showAddButton="true"
            :addTitle="t('admin.branch.branch')"
            :addAction="'modal'"
        />
    </AppLayout>
</template>
