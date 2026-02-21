<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from './CreateEditModal.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    journalTypes: Object,
});

const isDialogOpen = ref(false);
const editingJournalType = ref(null);
const { t } = useI18n();

const columns = computed(() => [
    { key: 'name', label: t('general.name'), sortable: true },
    { key: 'remarks', label: t('general.remarks') }, 
    { key: 'actions', label: t('general.action') },
]);

const editItem = (item) => {
    editingJournalType.value = item;
    isDialogOpen.value = true;
};

const { deleteResource } = useDeleteResource();

const deleteItem = (id) => {
    deleteResource('journal-types.destroy', id, {
        title: t('general.delete', { name: t('sidebar.journal_entry.journal_type') }),
        description: t('general.delete_description', { name: t('sidebar.journal_entry.journal_type') }),
        successMessage: t('general.delete_success', { name: t('sidebar.journal_entry.journal_type') }),
    });
};
</script>

<template>
    <AppLayout :title="t('sidebar.journal_entry.journal_types')">
        <CreateEditModal
            :isDialogOpen="isDialogOpen"
            :editingItem="editingJournalType"
            @update:isDialogOpen="(value) => {
                isDialogOpen = value;
                if (!value) editingJournalType = null;
            }"
            @saved="() => { editingJournalType = null }"
        />
        <DataTable
            can="journal_types"
            :items="journalTypes"
            :columns="columns"
            @edit="editItem"
            @delete="deleteItem"
            @add="isDialogOpen = true"
            :title="t('sidebar.journal_entry.journal_types')"
            :url="`journal-types.index`"
            :showAddButton="true"
            :addTitle="t('sidebar.journal_entry.journal_type')"
            :addAction="'modal'"
        />
    </AppLayout>
</template>

