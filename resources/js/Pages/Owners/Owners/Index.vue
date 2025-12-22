<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import OwnerShowDialog from './ShowDialog.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    owners: Object,
    currencies: Object,
});

const isDialogOpen = ref(false);
const showDialog = ref(false);
const selectedOwnerId = ref(null);
const { t } = useI18n();

const columns = computed(() => ([
    { key: 'name', label: t('general.name'), sortable: true },
    { key: 'father_name', label: t('owner.father_name') },
    { key: 'nic', label: t('owner.nic') },
    { key: 'phone_number', label: t('owner.phone_number') },
    { key: 'ownership_percentage', label: t('owner.ownership_percentage') },
    { key: 'is_active', label: t('general.status') },
    { key: 'actions', label: t('general.action') },
]));

const { deleteResource } = useDeleteResource();
const deleteItem = (id) => {
    deleteResource('owners.destroy', id, {
        title: t('general.delete', { name: 'Owner' }),
        description: t('general.delete_description', { name: 'Owner' }),
        successMessage: t('general.delete_success', { name: 'Owner' }),
    });
};

const editItem = (item) => {
    window.location.href = `/owners/${item.id}/edit`;
};

const showItem = (id) => {
    selectedOwnerId.value = id;
    showDialog.value = true;
};
</script>

<template>
    <AppLayout :title="t('owner.owners')">
        <DataTable
            :items="owners"
            :columns="columns"
            :hasShow="true"
            @edit="editItem"
            @delete="deleteItem"
            @show="showItem"
            :title="t('owner.owners')"
            :url="`owners.index`"
            :showAddButton="true"
            :addTitle="t('owner.owner')"
            :addAction="'redirect'"
            :addRoute="'owners.create'"
        />
        <OwnerShowDialog
            :open="showDialog"
            :owner-id="selectedOwnerId"
            @update:open="showDialog = $event"
        />
    </AppLayout>
    </template>


