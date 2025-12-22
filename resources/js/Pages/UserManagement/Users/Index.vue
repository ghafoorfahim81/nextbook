<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    users: Object,
});

const { t } = useI18n();

const columns = computed(() => ([
    { key: 'name', label: t('general.name'), sortable: true },
    { key: 'email', label: 'Email', sortable: true },
    {
        key: 'roles',
        label: 'Roles',
        render: (row) => row.roles?.map(r => r.name).join(', ') ?? '-',
    },
    { key: 'actions', label: t('general.action') },
]));

const { deleteResource } = useDeleteResource();

const deleteItem = (id) => {
    deleteResource('users.destroy', id, {
        title: t('general.delete', { name: 'User' }),
        description: t('general.delete_description', { name: 'User' }),
        successMessage: t('general.delete_success', { name: 'User' }),
    });
};

const editItem = (item) => {
    window.location.href = `/users/${item.id}/edit`;
};
</script>

<template>
    <AppLayout :title="'Users'">
        <DataTable
            :items="users"
            :columns="columns"
            @delete="deleteItem"
            @edit="editItem"
            :title="'Users'"
            :url="`users.index`"
            :showAddButton="true"
            :addTitle="'User'"
            :addAction="'redirect'"
            :addRoute="'users.create'"
            :hasEdit="true"
        />
    </AppLayout>
</template>

