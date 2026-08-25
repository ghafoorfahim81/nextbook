<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from './CreateEditModal.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    jobOpenings: Object,
    filterOptions: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const isDialogOpen = ref(false);
const editingItem = ref(null);

const statusTone = (status) => ({
    draft: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    published: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200',
    closed: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    filled: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-200',
    cancelled: 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
}[status] ?? 'bg-slate-100 text-slate-700');

const columns = computed(() => ([
    { key: 'code', label: t('general.code'), sortable: true },
    { key: 'title', label: t('hr.job_title'), sortable: true },
    { key: 'department_name', label: t('hr.department'), render: (row) => row.department_name ?? '—' },
    { key: 'employment_type_label', label: t('hr.employment_type') },
    {
        key: 'vacancies',
        label: t('hr.vacancies'),
        // Remaining against advertised, because "2 vacancies" on a filled post
        // is misleading.
        render: (row) => `${row.remaining_vacancies} / ${row.vacancies}`,
    },
    { key: 'application_count', label: t('hr.applicants'), render: (row) => row.application_count ?? 0 },
    { key: 'closing_date', label: t('hr.closing_date'), render: (row) => row.closing_date ?? '—' },
    {
        key: 'status_label',
        label: t('general.status'),
        render: (row) => row.status_label,
        badge: (row) => statusTone(row.status),
    },
    { key: 'actions', label: t('general.action') },
]));

const filterFields = computed(() => ([
    { key: 'status', label: t('general.status'), type: 'select', options: props.filterOptions?.statuses || [] },
    { key: 'department_id', label: t('hr.department'), type: 'select', options: props.filterOptions?.departments || [] },
]));

const editItem = (item) => {
    editingItem.value = item;
    isDialogOpen.value = true;
};

const { deleteResource } = useDeleteResource();
const deleteItem = (id) => {
    deleteResource('job-openings.destroy', id, {
        title: t('general.delete', { name: t('hr.job_opening') }),
        description: t('general.delete_description', { name: t('hr.job_opening') }),
        successMessage: t('general.delete_success', { name: t('hr.job_opening') }),
    });
};
</script>

<template>
    <AppLayout :title="t('hr.job_openings')">
        <CreateEditModal
            :isDialogOpen="isDialogOpen"
            :editingItem="editingItem"
            :filterOptions="filterOptions"
            @update:isDialogOpen="(v) => { isDialogOpen = v; if (!v) editingItem = null; }"
            @saved="() => { editingItem = null }"
        />
        <DataTable
            can="job_openings"
            :items="jobOpenings"
            :columns="columns"
            :filters="filters"
            :filterFields="filterFields"
            :hasShow="true"
            @show="(id) => router.get(route('job-openings.show', id))"
            @edit="editItem"
            @delete="deleteItem"
            @add="isDialogOpen = true"
            :title="t('hr.job_openings')"
            :url="`job-openings.index`"
            :showAddButton="true"
            :addTitle="t('hr.job_opening')"
            :addAction="'modal'"
        />
    </AppLayout>
</template>
