<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from '@/Pages/Administration/Stores/CreateEditModal.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    stores: Object,
    branches: {
        type: Array,
        required: true,
    },
});
const isDialogOpen = ref(false)
const editingStore = ref(null)
const { t } = useI18n()

const columns = computed(() => ([ 
    { key: 'name', label: t('general.name'),sortable: true },
    { key: 'address', label: t('admin.shared.address') },
    {
        key: 'is_active',
        label: t('general.status'),
        sortable: true,
        render: (row) => row.is_active ? t('general.active') : t('general.inactive'),
    },
    {
        key: 'created_by.name',
        label: t('general.created_by'),
        render: (row) => row.created_by?.name ?? '-',
    },
    {
        key: 'updated_by.name',
        label: t('general.updated_by'),
        render: (row) => row.updated_by?.name ?? '-',
    },
    { key: 'actions', label: t('general.action') },
]));

const editItem = (item) => {
    editingStore.value = item
    isDialogOpen.value = true
}
const { deleteResource } = useDeleteResource()
const deleteItem = (id) => {
    deleteResource('stores.destroy', id, {
        title: t('general.delete', { name: t('admin.store.store') }),
        description: t('general.delete_description', { name: t('admin.store.store') }),
        successMessage: t('general.delete_success', { name: t('admin.store.store') }),
    })

};

</script>

<template>
    <AppLayout :title="t('admin.store.stores')">
        <CreateEditModal
            :isDialogOpen="isDialogOpen"
            :editingItem="editingStore"
            :stores="stores"
            :branches="branches"
            @update:isDialogOpen="(value) => {
                isDialogOpen = value;
                if (!value) editingStore = null;
            }"
            @saved="() => { editingStore = null }"
        />
        <DataTable
            can="stores"
            :items="stores"
            :columns="columns"
            @edit="editItem"
            @delete="deleteItem"
            @add="isDialogOpen = true"
            :title="t('admin.store.stores')"
            :url="`stores.index`"
            :showAddButton="true"
            :addTitle="t('admin.store.store')"
            :addAction="'modal'"
        />
    </AppLayout>
</template>
