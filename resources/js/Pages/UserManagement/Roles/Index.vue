<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3'
const props = defineProps({
    roles: Object,
});

const { t } = useI18n();

const columns = computed(() => ([
    { key: 'name', label: t('general.name'), sortable: true },
    {
        key: 'permissions',
        label: t('user_mangements.permissions'),
        render: (row) => {
            const perms = row.permissions || [];
            if (perms.length === 0) return '-';
            if (perms.length <= 3) return perms.map(p => p.name).join(', ');
            return `${perms.slice(0, 3).map(p => p.name).join(', ')} +${perms.length - 3} more`;
        },
    },
    { key: 'actions', label: t('general.action') },
]));

const { deleteResource } = useDeleteResource();

const deleteItem = (id) => {
    deleteResource('roles.destroy', id, {
            title: t('general.delete', { name: t('user_mangements.role') }),
        description: t('general.delete_description', { name: t('user_mangements.role') }),
        successMessage: t('general.delete_success', { name: t('user_mangements.role') }),
    });
};

const editItem = (item) => {
    router.visit(route('roles.edit', item.id));
};
</script>

<template>
    <AppLayout :title="t('user_mangements.roles')">
        <DataTable
            can="roles"
            :items="roles"
            :columns="columns"
            @delete="deleteItem"
            @edit="editItem"
            :title="t('user_mangements.roles')"
            :url="`roles.index`"
            :showAddButton="true"
            :addTitle="t('user_mangements.role')"
            :addAction="'redirect'"
            :addRoute="'roles.create'"
            :hasEdit="true"
        />
    </AppLayout>
</template>

