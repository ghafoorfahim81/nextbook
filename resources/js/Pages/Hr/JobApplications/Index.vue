<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useDeleteResource } from '@/composables/useDeleteResource';
import CreateEditModal from './CreateEditModal.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    jobApplications: Object,
    filterOptions: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const isDialogOpen = ref(false);
const editingItem = ref(null);

const statusTone = (status) => ({
    applied: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    shortlisted: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-200',
    interviewing: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200',
    offered: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    hired: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200',
    rejected: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-200',
    withdrawn: 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
}[status] ?? 'bg-slate-100 text-slate-700');

const columns = computed(() => ([
    { key: 'application_number', label: t('general.number'), sortable: true },
    { key: 'full_name', label: t('hr.candidate'), sortable: true },
    { key: 'job_opening_title', label: t('hr.job_opening'), render: (row) => row.job_opening_title ?? '—' },
    { key: 'phone_number', label: t('general.phone'), render: (row) => row.phone_number ?? '—' },
    { key: 'years_of_experience', label: t('hr.experience'), render: (row) => row.years_of_experience ?? '—' },
    { key: 'source_label', label: t('hr.source') },
    { key: 'score', label: t('hr.score'), render: (row) => row.score ?? '—' },
    { key: 'interview_count', label: t('hr.interviews'), render: (row) => row.interview_count ?? 0 },
    {
        key: 'status_label',
        label: t('general.status'),
        render: (row) => row.status_label,
        badge: (row) => statusTone(row.status),
    },
    { key: 'actions', label: t('general.action') },
]));

const filterFields = computed(() => ([
    { key: 'job_opening_id', label: t('hr.job_opening'), type: 'select', options: props.filterOptions?.jobOpenings || [] },
    { key: 'status', label: t('general.status'), type: 'select', options: props.filterOptions?.statuses || [] },
    { key: 'source', label: t('hr.source'), type: 'select', options: props.filterOptions?.sources || [] },
]));

const editItem = (item) => {
    editingItem.value = item;
    isDialogOpen.value = true;
};

const { deleteResource } = useDeleteResource();
const deleteItem = (id) => {
    deleteResource('job-applications.destroy', id, {
        title: t('general.delete', { name: t('hr.job_application') }),
        description: t('general.delete_description', { name: t('hr.job_application') }),
        successMessage: t('general.delete_success', { name: t('hr.job_application') }),
    });
};
</script>

<template>
    <AppLayout :title="t('hr.job_applications')">
        <CreateEditModal
            :isDialogOpen="isDialogOpen"
            :editingItem="editingItem"
            :filterOptions="filterOptions"
            @update:isDialogOpen="(v) => { isDialogOpen = v; if (!v) editingItem = null; }"
            @saved="() => { editingItem = null }"
        />
        <DataTable
            can="job_applications"
            :items="jobApplications"
            :columns="columns"
            :filters="filters"
            :filterFields="filterFields"
            :hasShow="true"
            @show="(id) => router.get(route('job-applications.show', id))"
            @edit="editItem"
            @delete="deleteItem"
            @add="isDialogOpen = true"
            :title="t('hr.job_applications')"
            :url="`job-applications.index`"
            :showAddButton="true"
            :addTitle="t('hr.job_application')"
            :addAction="'modal'"
        />
    </AppLayout>
</template>
