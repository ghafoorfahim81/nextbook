<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from '@/Pages/UserManagement/Users/CreateEditModal.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    users: Object,
});

const isDialogOpen = ref(false);
const editingUser = ref(null);
const { t } = useI18n();

const columns = ref([
    { key: 'name', label: t('general.name'), sortable: true },
    { key: 'email', label: 'Email', sortable: true },
    {
        key: 'roles',
        label: 'Roles',
        render: (row) => row.roles?.map(r => r.name).join(', ') ?? '-',
    },
    { key: 'actions', label: t('general.action') },
]);

const editItem = (item) => {
    editingUser.value = item;
    isDialogOpen.value = true;
};

const { deleteResource } = useDeleteResource();

const deleteItem = (id) => {
    deleteResource('users.destroy', id, {
        title: t('general.delete', { name: 'User' }),
        description: t('general.delete_description', { name: 'User' }),
        successMessage: t('general.delete_success', { name: 'User' }),
    });
};
</script>

<template>
    <AppLayout :title="'Users'">
        <CreateEditModal
            :isDialogOpen="isDialogOpen"
            :editingItem="editingUser"
            @update:isDialogOpen="(value) => {
                isDialogOpen = value;
                if (!value) editingUser = null;
            }"
            @saved="() => { editingUser = null }"
        />
        <DataTable
            :items="users"
            :columns="columns"
            @edit="editItem"
            @delete="deleteItem"
            @add="isDialogOpen = true"
            :title="'Users'"
            :url="`users.index`"
            :showAddButton="true"
            :addTitle="'User'"
            :addAction="'modal'"
        />
    </AppLayout>
</template>

