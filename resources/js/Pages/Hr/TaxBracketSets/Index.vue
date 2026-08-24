<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from './CreateEditModal.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    taxBracketSets: Object,
    filterOptions: { type: Object, default: () => ({}) },
    defaultBrackets: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const isDialogOpen = ref(false);
const editingItem = ref(null);

const columns = computed(() => ([
    { key: 'name', label: t('general.name'), sortable: true },
    { key: 'period_label', label: t('hr.tax_period') },
    { key: 'effective_from', label: t('hr.effective_from'), sortable: true },
    { key: 'effective_to', label: t('hr.effective_to'), render: (row) => row.effective_to ?? '—' },
    { key: 'brackets', label: t('hr.bands'), render: (row) => (row.brackets?.length ?? 0) },
    { key: 'jurisdiction', label: t('hr.jurisdiction') },
    { key: 'is_active', label: t('general.status'), render: (row) => (row.is_active ? t('general.active') : t('general.inactive')) },
    { key: 'actions', label: t('general.action') },
]));

const filterFields = computed(() => ([
    { key: 'period', label: t('hr.tax_period'), type: 'select', options: props.filterOptions?.periods || [] },
]));

const editItem = (item) => {
    editingItem.value = item;
    isDialogOpen.value = true;
};

const { deleteResource } = useDeleteResource();
const deleteItem = (id) => {
    deleteResource('tax-bracket-sets.destroy', id, {
        title: t('general.delete', { name: t('hr.tax_bracket_set') }),
        description: t('general.delete_description', { name: t('hr.tax_bracket_set') }),
        successMessage: t('general.delete_success', { name: t('hr.tax_bracket_set') }),
    });
};
</script>

<template>
    <AppLayout :title="t('hr.tax_bracket_sets')">
        <CreateEditModal
            :isDialogOpen="isDialogOpen"
            :editingItem="editingItem"
            :filterOptions="filterOptions"
            :defaultBrackets="defaultBrackets"
            @update:isDialogOpen="(v) => { isDialogOpen = v; if (!v) editingItem = null; }"
            @saved="() => { editingItem = null }"
        />

        <div class="mb-4 rounded-lg border border-border bg-muted/40 px-4 py-3 text-sm text-muted-foreground">
            {{ t('hr.tax_table_intro') }}
        </div>

        <DataTable
            can="tax_bracket_sets"
            :items="taxBracketSets"
            :columns="columns"
            :filters="filters"
            :filterFields="filterFields"
            :hasShow="true"
            @edit="editItem"
            @delete="deleteItem"
            @show="(id) => router.get(route('tax-bracket-sets.show', id))"
            @add="isDialogOpen = true"
            :title="t('hr.tax_bracket_sets')"
            :url="`tax-bracket-sets.index`"
            :showAddButton="true"
            :addTitle="t('hr.tax_bracket_set')"
            :addAction="'modal'"
        />
    </AppLayout>
</template>
