<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDeleteResource } from '@/composables/useDeleteResource';
import JournalEntryShowDialog from '@/Pages/JournalEntries/ShowDialog.vue'
import { router } from '@inertiajs/vue3'
const { t } = useI18n();

const props = defineProps({
    journalEntries: Object,
})

const { deleteResource } = useDeleteResource()
const showDialog = ref(false)
const selectedJournalEntryId = ref(null)

const editItem = (item) => {
    router.visit(route('payments.edit', item.id));
}
const deleteItem = (id) => {
    deleteResource('payments.destroy', id, {
        title: t('general.delete', { name: t('payment.payment') }),
        name: t('payment.payment'),
    })
}
const showItem = (id) => {
    selectedPaymentId.value = id
    showDialog.value = true
}

const columns = computed(() => ([
    { key: 'number', label: t('general.number'), sortable: true },
    { key: 'description', label: t('general.description'), sortable: true },
    { key: 'amount', label: t('general.amount'), sortable: true },
    { key: 'date', label: t('general.date'), sortable: true },
    { key: 'status', label: t('general.status'), sortable: true },
    { key: 'actions', label: t('general.actions') },
]))
</script>

<template>
    <AppLayout :title="t('sidebar.journal_entry.journal_entries')">
        <DataTable
            can="journalEntries"
            :items="journalEntries"
            :columns="columns"
            :title="t('sidebar.journal_entry.journal_entries')"
            :url="`journal-entries.index`"
            :showAddButton="true"
            :hasShow="true"
            @edit="editItem"
            @delete="deleteItem"
            @show="showItem"
            :addTitle="t('sidebar.journal_entry.journal_entry')"
            :addAction="'redirect'"
            :addRoute="'journalEntries.create'"
        />
        <JournalEntryShowDialog
            :open="showDialog"
            :journalEntry-id="selectedJournalEntryId"
            @update:open="showDialog = $event"
        />
    </AppLayout>
</template>


