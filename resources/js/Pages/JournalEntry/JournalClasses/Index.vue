<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from './CreateEditModal.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    journalClasses: Object,
});

const isDialogOpen = ref(false);
const editingJournalClass = ref(null);
const { t } = useI18n();

const columns = computed(() => [
    { key: 'name', label: t('general.name'), sortable: true },
    { key: 'code', label: t('general.code'), sortable: true },
    { key: 'description', label: t('general.description'), sortable: true },
    { key: 'actions', label: t('general.action') },
]);

const editItem = (item) => {
    editingJournalClass.value = item;
    isDialogOpen.value = true;
};

const { deleteResource } = useDeleteResource();

const deleteItem = (id) => {
    deleteResource('journal-classes.destroy', id, {
        title: t('general.delete', { name: t('sidebar.journal_entry.journal_class') }),
        description: t('general.delete_description', { name: t('sidebar.journal_entry.journal_class') }),
        successMessage: t('general.delete_success', { name: t('sidebar.journal_entry.journal_class') }),
    });
};
</script>

<template>
    <AppLayout :title="t('sidebar.journal_entry.journal_classes')">
        <CreateEditModal
            :isDialogOpen="isDialogOpen"
            :editingItem="editingJournalClass"
            @update:isDialogOpen="(value) => {
                isDialogOpen = value;
                if (!value) editingJournalClass = null;
            }"
            @saved="() => { editingJournalClass = null }"
        />
        <DataTable
            can="journal_classes"
            :items="journalClasses"
            :columns="columns"
            @edit="editItem"
            @delete="deleteItem"
            @add="isDialogOpen = true"
            :title="t('sidebar.journal_entry.journal_classes')"
            :url="`journal-classes.index`"
            :showAddButton="true"
            :addTitle="t('sidebar.journal_entry.journal_class')"
            :addAction="'modal'"
        />
    </AppLayout>
</template>

