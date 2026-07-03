<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDeleteResource } from '@/composables/useDeleteResource';
import { router } from '@inertiajs/vue3'
const { t } = useI18n();

const props = defineProps({
    journalEntries: Object,
    filters: Object,
    filterOptions: Object,
})

const { deleteResource } = useDeleteResource()

const editItem = (item) => {
    router.visit(route('journal-entries.edit', item.id));
}
const deleteItem = (id) => {
    deleteResource('journal-entries.destroy', id, {
        title: t('general.delete', { name: t('journal_entry.journal_entry') }),
        name: t('journal_entry.journal_entry'),
    })
}
const showItem = (id) => {
    router.visit(route('journal-entries.show', id))
}

const columns = computed(() => ([
    { key: 'number', label: t('general.number'), sortable: true },
    { key: 'remark', label: t('general.remark'), sortable: true },
    { key: 'amount', label: t('general.amount'), sortable: true },
    { key: 'date', label: t('general.date'), sortable: true },
    { key: 'status', label: t('general.status'), sortable: true },
    { key: 'actions', label: t('general.actions') },
]))

const filterFields = computed(() => ([
    {
        key: 'transaction.currency_id',
        label: t('admin.currency.currency'),
        type: 'select',
        options: (props.filterOptions?.currencies || []).map((c) => ({ id: c.id, name: c.code })),
    },
    {
        key: 'transaction.lines.journal_class_id',
        label: t('sidebar.journal_entry.journal_class'),
        type: 'select',
        options: (props.filterOptions?.journalClasses || []).map((c) => ({ id: c.id, name: c.name })),
    },
    {
        key: 'transaction.lines.ledger_id',
        label: t('general.ledger'),
        type: 'select',
        options: (props.filterOptions?.ledgers || []).map((l) => ({ id: l.id, name: l.name })),
    },
    { key: 'date', label: t('general.date'), type: 'daterange' },
    {
        key: 'created_by',
        label: t('general.created_by'),
        type: 'select',
        options: (props.filterOptions?.users || []).map((u) => ({ id: u.id, name: u.name })),
    },
]))
</script>

<template>
    <AppLayout :title="t('sidebar.journal_entry.journal_entries')">
        <DataTable
            can="journal_entries"
            :items="journalEntries"
            :columns="columns"
            :filters="filters"
            :filterFields="filterFields"
            :title="t('sidebar.journal_entry.journal_entries')"
            :url="`journal-entries.index`"
            :showAddButton="true"
            :hasShow="true"
            @edit="editItem"
            @delete="deleteItem"
            @show="showItem"
            :addTitle="t('sidebar.journal_entry.journal_entry')"
            :addAction="'redirect'"
            :addRoute="'journal-entries.create'"
            exportRoute="journal-entries.export"
        />
    </AppLayout>
</template>


