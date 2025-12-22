<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from './CreateEditModal.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    companies: Object,
    branches: {
        type: Array,
        required: true,
    },
});

const isDialogOpen = ref(false);
const editingItem = ref(null);
const { t } = useI18n();

const columns = computed(() => ([
    { key: 'name', label: t('general.name'), sortable: true },
    { key: 'legal_name', label: t('admin.company.legal_name') },
    { key: 'registration_number', label: t('admin.company.registration_number') },
    { key: 'industry', label: t('admin.company.industry') },
    { key: 'type', label: t('general.type') },
    { key: 'city', label: t('general.city') },
    { key: 'country', label: t('general.country') },
    { key: 'actions', label: t('general.action') },
]));

const { deleteResource } = useDeleteResource();
const deleteItem = (id) => {
    deleteResource('companies.destroy', id, {
        title: t('general.delete', { name: t('admin.company.company') }),
        description: t('general.delete_description', { name: t('admin.company.company') }),
        successMessage: t('general.delete_success', { name: t('admin.company.company') }),
    });
};

const editItem = (item) => {
    editingItem.value = item;
    isDialogOpen.value = true;
};
</script>

<template>
    <AppLayout :title="t('admin.company.companies')">
        <CreateEditModal
            :isDialogOpen="isDialogOpen"
            :editingItem="editingItem"
            :branches="branches"
            @update:isDialogOpen="(value) => {
                isDialogOpen = value;
                if (!value) editingItem = null;
            }"
            @saved="() => { editingItem = null }"
        />
        <DataTable
            :items="companies"
            :columns="columns"
            @edit="editItem"
            @delete="deleteItem"
            @add="isDialogOpen = true"
            :title="t('admin.company.companies')"
            :url="`companies.index`"
            :showAddButton="true"
            :addTitle="t('admin.company.company')"
            :addAction="'modal'"
        />
    </AppLayout>
</template>
