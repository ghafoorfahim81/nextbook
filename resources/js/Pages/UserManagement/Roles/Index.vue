<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    roles: Object,
});

const { t } = useI18n();

const columns = ref([
    { key: 'name', label: t('general.name'), sortable: true },
    {
        key: 'permissions',
        label: 'Permissions',
        render: (row) => {
            const perms = row.permissions || [];
            if (perms.length === 0) return '-';
            if (perms.length <= 3) return perms.map(p => p.name).join(', ');
            return `${perms.slice(0, 3).map(p => p.name).join(', ')} +${perms.length - 3} more`;
        },
    },
    { key: 'actions', label: t('general.action') },
]);

const { deleteResource } = useDeleteResource();

const deleteItem = (id) => {
    deleteResource('roles.destroy', id, {
        title: t('general.delete', { name: 'Role' }),
        description: t('general.delete_description', { name: 'Role' }),
        successMessage: t('general.delete_success', { name: 'Role' }),
    });
};

const editItem = (item) => {
    window.location.href = `/roles/${item.id}/edit`;
};
</script>

<template>
    <AppLayout :title="'Roles'">
        <DataTable
            :items="roles"
            :columns="columns"
            @delete="deleteItem"
            @edit="editItem"
            :title="'Roles'"
            :url="`roles.index`"
            :showAddButton="true"
            :addTitle="'Role'"
            :addAction="'redirect'"
            :addRoute="'roles.create'"
            :hasEdit="true"
        />
    </AppLayout>
</template>

