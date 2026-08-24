<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useDeleteResource } from '@/composables/useDeleteResource';
import { useI18n } from 'vue-i18n';

defineProps({
    salaryStructures: Object,
    filterOptions: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const { t } = useI18n();

const columns = computed(() => ([
    { key: 'name', label: t('general.name'), sortable: true },
    {
        key: 'applies_to',
        label: t('hr.applies_to'),
        // A structure targets an employee, a designation or a department —
        // one column rather than three mostly-empty ones.
        render: (row) => row.employee_name || row.designation_name || row.department_name || '—',
    },
    { key: 'basic_salary', label: t('hr.basic_salary') },
    { key: 'currency_code', label: t('general.currency') },
    { key: 'pay_frequency_label', label: t('hr.pay_frequency') },
    { key: 'effective_from', label: t('hr.effective_from'), sortable: true },
    { key: 'effective_to', label: t('hr.effective_to'), render: (row) => row.effective_to ?? '—' },
    { key: 'is_active', label: t('general.status'), render: (row) => (row.is_active ? t('general.active') : t('general.inactive')) },
    { key: 'actions', label: t('general.action') },
]));

const { deleteResource } = useDeleteResource();

const deleteItem = (id) => {
    deleteResource('salary-structures.destroy', id, {
        title: t('general.delete', { name: t('hr.salary_structure') }),
        description: t('general.delete_description', { name: t('hr.salary_structure') }),
        successMessage: t('general.delete_success', { name: t('hr.salary_structure') }),
    });
};
</script>

<template>
    <AppLayout :title="t('hr.salary_structures')">
        <div class="mb-4 rounded-lg border border-border bg-muted/40 px-4 py-3 text-sm text-muted-foreground">
            {{ t('hr.structure_intro') }}
        </div>

        <DataTable
            can="salary_structures"
            :items="salaryStructures"
            :columns="columns"
            :filters="filters"
            :hasShow="true"
            @show="(id) => router.get(route('salary-structures.show', id))"
            @edit="(item) => router.get(route('salary-structures.edit', item.id))"
            @delete="deleteItem"
            @add="router.get(route('salary-structures.create'))"
            :title="t('hr.salary_structures')"
            :url="`salary-structures.index`"
            :showAddButton="true"
            :addTitle="t('hr.salary_structure')"
        />
    </AppLayout>
</template>
