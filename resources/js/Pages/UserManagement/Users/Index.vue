<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import { useToggleStatus } from '@/composables/useToggleStatus';
import ShowDialog from '@/Pages/UserManagement/Users/ShowDialog.vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3'
const props = defineProps({
    users: Object,
    filters: Object,
    filterOptions: Object,
});

const { t } = useI18n();

const filterFields = computed(() => ([
    {
        key: 'role_id',
        label: t('user_mangements.roles'),
        type: 'select',
        options: (props.filterOptions?.roles || []).map((r) => ({ id: r.id, name: r.name })),
    },
]));
const showDialog = ref(false);
const selectedUserId = ref(null);

const columns = computed(() => ([
    { key: 'name', label: t('general.name'), sortable: true },
    { key: 'email', label: t('general.email'), sortable: true },
    {
        key: 'roles',
        label: t('user_mangements.roles'),
        render: (row) => row.roles?.map(r => r.name).join(', ') ?? '-',
    },
    // There was no way to see, let alone change, whether an account still works.
    {
        key: 'status',
        label: t('general.status'),
        sortable: true,
        render: (row) => (userIsActive(row) ? t('general.active') : t('general.inactive')),
    },
    { key: 'actions', label: t('general.action') },
]));

// A user is active unless the enum says otherwise; `blocked` is a separate
// state that the toggle deliberately leaves alone.
const userIsActive = (row) => row?.is_active !== false && row?.status === 'active';

const { deleteResource } = useDeleteResource();

const deleteItem = (id) => {
    deleteResource('users.destroy', id, {
        title: t('general.delete', { name: t('user_mangements.users') }),
        name: t('user_mangements.users'),
        successMessage: t('general.delete_success', { name: t('user_mangements.users') }),
    });
};

const editItem = (item) => {
    router.visit(route('users.edit', item.id));
};

const showItem = (id) => {
    selectedUserId.value = id;
    showDialog.value = true;
};

const { toggleStatus } = useToggleStatus();
const toggleItemStatus = (item) => {
    toggleStatus('users.toggle-status', item.id, {
        isActive: userIsActive(item),
        name: item.name || t('user_mangements.user'),
    });
};
</script>

<template>
    <AppLayout :title="t('user_mangements.users')">
        <DataTable
            can="users"
            :items="users"
            :columns="columns"
            :filters="filters"
            :filterFields="filterFields"
            @delete="deleteItem"
            @edit="editItem"
            @toggle-status="toggleItemStatus"
            :has-status-toggle="true"
            :is-item-active="userIsActive"
            :title="t('user_mangements.users')"
            :url="`users.index`"
            :showAddButton="true"
            :addTitle="t('user_mangements.user')"
            :addAction="'redirect'"
            :addRoute="'users.create'"
            :hasEdit="true"
            :hasShow="true"
            @show="showItem"
        />
        <ShowDialog
            :open="showDialog"
            :user-id="selectedUserId"
            @update:open="showDialog = $event"
        />
    </AppLayout>
</template>
