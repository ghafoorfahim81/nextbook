<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useDeleteResource } from '@/composables/useDeleteResource';
import { Button } from '@/Components/ui/button';
import { useI18n } from 'vue-i18n';
import { CalendarPlus } from 'lucide-vue-next';

const props = defineProps({
    attendances: Object,
    filterOptions: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const { t } = useI18n();

const columns = computed(() => ([
    { key: 'date', label: t('hr.date'), sortable: true },
    { key: 'employee_name', label: t('hr.employee') },
    { key: 'department_name', label: t('hr.department'), render: (row) => row.department_name ?? '—' },
    { key: 'status_label', label: t('general.status'), sortable: true },
    { key: 'check_in', label: t('hr.check_in'), render: (row) => row.check_in ?? '—' },
    { key: 'check_out', label: t('hr.check_out'), render: (row) => row.check_out ?? '—' },
    { key: 'worked_hours', label: t('hr.worked_hours'), sortable: true },
    { key: 'overtime_hours', label: t('hr.overtime_hours') },
    {
        key: 'late_minutes',
        label: t('hr.late_minutes'),
        sortable: true,
        render: (row) => (row.late_minutes > 0 ? row.late_minutes : '—'),
    },
    {
        key: 'flags',
        label: '',
        // Two states worth surfacing in a list: a day nobody can edit any more,
        // and a day the pairer could not resolve.
        render: (row) => [
            row.is_locked ? t('hr.locked') : null,
            row.needs_review ? t('hr.needs_review') : null,
        ].filter(Boolean).join(' · ') || '—',
    },
    { key: 'actions', label: t('general.action') },
]));

const filterFields = computed(() => ([
    { key: 'employee_id', label: t('hr.employee'), type: 'text' },
    { key: 'status', label: t('general.status'), type: 'select', options: props.filterOptions?.statuses || [] },
    { key: 'source', label: t('hr.source'), type: 'select', options: props.filterOptions?.sources || [] },
    { key: 'shift_id', label: t('hr.shift'), type: 'select', options: props.filterOptions?.shifts || [] },
    { key: 'date', label: t('hr.date'), type: 'daterange' },
]));

const { deleteResource } = useDeleteResource();
const deleteItem = (id) => {
    deleteResource('attendances.destroy', id, {
        title: t('general.delete', { name: t('hr.attendance') }),
        description: t('general.delete_description', { name: t('hr.attendance') }),
        successMessage: t('general.delete_success', { name: t('hr.attendance') }),
    });
};
</script>

<template>
    <AppLayout :title="t('sidebar.attendance.register')">
        <div class="mb-3 flex justify-end">
            <Button
                size="sm"
                class="gap-2 bg-primary text-white hover:bg-primary/90"
                @click="router.visit(route('attendances.roster'))"
            >
                <CalendarPlus class="h-4 w-4" />
                {{ t('hr.roster') }}
            </Button>
        </div>

        <DataTable
            can="attendances"
            :items="attendances"
            :columns="columns"
            :filters="filters"
            :filterFields="filterFields"
            :hasEdit="false"
            @delete="deleteItem"
            :title="t('sidebar.attendance.register')"
            :url="`attendances.index`"
            :showAddButton="false"
        />
    </AppLayout>
</template>
