<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3'
const props = defineProps({
    users: Object,
});

const { t } = useI18n();

const columns = computed(() => ([
    { key: 'name', label: t('general.name'), sortable: true },
    { key: 'email', label: t('general.email'), sortable: true },
    {
        key: 'roles',
        label: t('user_mangements.roles'),
        render: (row) => row.roles?.map(r => r.name).join(', ') ?? '-',
    },
    { key: 'actions', label: t('general.action') },
]));

const { deleteResource } = useDeleteResource();

const deleteItem = (id) => {
    deleteResource('users.destroy', id, {
        title: t('general.delete', { name: t('user_mangements.users') }),
        description: t('general.delete_description', { name: t('user_mangements.users') }),
        successMessage: t('general.delete_success', { name: t('user_mangements.users') }),
    });
};

const editItem = (item) => {
    router.visit(route('users.edit', item.id));
};
</script>

<template>
    <AppLayout :title="t('user_mangements.users')">
        <DataTable
            can="users"
            :items="users"
            :columns="columns"
            @delete="deleteItem"
            @edit="editItem"
            :title="t('user_mangements.users')"
            :url="`users.index`"
            :showAddButton="true"
            :addTitle="t('user_mangements.user')"
            :addAction="'redirect'"
            :addRoute="'users.create'"
            :hasEdit="true"
        />
    </AppLayout>
</template>

