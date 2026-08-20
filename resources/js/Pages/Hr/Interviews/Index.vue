<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useDeleteResource } from '@/composables/useDeleteResource';
import ModalDialog from '@/Components/next/Dialog.vue';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import { Button } from '@/Components/ui/button';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    interviews: Object,
    filterOptions: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const { t } = useI18n();

const statusTone = (status) => ({
    scheduled: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-200',
    completed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200',
    cancelled: 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    rescheduled: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    no_show: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-200',
}[status] ?? 'bg-slate-100 text-slate-700');

const columns = computed(() => ([
    { key: 'candidate_name', label: t('hr.candidate') },
    { key: 'round', label: t('hr.round') },
    { key: 'interview_type_label', label: t('hr.interview_type') },
    { key: 'scheduled_at', label: t('hr.scheduled_at'), sortable: true },
    { key: 'score', label: t('hr.score'), render: (row) => row.score ?? '—' },
    { key: 'panel_verdict_label', label: t('hr.panel_verdict'), render: (row) => row.panel_verdict_label ?? '—' },
    {
        key: 'status_label',
        label: t('general.status'),
        html: true,
        render: (row) => `<span class="rounded-full px-2 py-0.5 text-xs font-medium ${statusTone(row.status)}">${row.status_label}</span>`,
    },
    { key: 'actions', label: t('general.action') },
]));

const filterFields = computed(() => ([
    { key: 'status', label: t('general.status'), type: 'select', options: props.filterOptions?.statuses || [] },
    { key: 'interview_type', label: t('hr.interview_type'), type: 'select', options: props.filterOptions?.interviewTypes || [] },
]));

// Feedback is captured per panelist, so the list opens the panel rather than
// a single shared box.
const feedbackOpen = ref(false);
const activeInterview = ref(null);
const activePanelist = ref(null);

const feedbackForm = useForm({ score: null, recommendation: null, feedback: '', selected_recommendation: null });

const openFeedback = (interview) => {
    activeInterview.value = interview;
    activePanelist.value = interview.panelists?.[0] ?? null;
    feedbackForm.reset();
    feedbackOpen.value = true;
};

const submitFeedback = () => {
    if (!activePanelist.value) return;

    feedbackForm.transform((data) => Object.fromEntries(
        Object.entries(data).filter(([k]) => !k.startsWith('selected_'))
    ));

    feedbackForm.patch(route('interviews.feedback', {
        interview: activeInterview.value.id,
        panelist: activePanelist.value.id,
    }), {
        preserveScroll: true,
        onSuccess: () => { feedbackOpen.value = false; },
    });
};

const completeInterview = (interview) => {
    router.patch(route('interviews.complete', interview.id), {}, { preserveScroll: true });
};

const { deleteResource } = useDeleteResource();
const deleteItem = (id) => {
    deleteResource('interviews.destroy', id, {
        title: t('general.delete', { name: t('hr.interview') }),
        description: t('general.delete_description', { name: t('hr.interview') }),
        successMessage: t('general.delete_success', { name: t('hr.interview') }),
    });
};
</script>

<template>
    <AppLayout :title="t('hr.interviews')">
        <DataTable
            can="interviews"
            :items="interviews"
            :columns="columns"
            :filters="filters"
            :filterFields="filterFields"
            @view="(item) => router.get(route('job-applications.show', item.job_application_id))"
            @edit="openFeedback"
            @delete="deleteItem"
            :title="t('hr.interviews')"
            :url="`interviews.index`"
            :showAddButton="false"
        />

        <ModalDialog
            :open="feedbackOpen"
            :title="t('hr.record_feedback')"
            :confirmText="t('general.save')"
            :cancel-text="t('general.close')"
            @update:open="feedbackOpen = $event"
            @confirm="submitFeedback"
            @cancel="feedbackOpen = false"
            :submitting="feedbackForm.processing"
        >
            <div v-if="!activeInterview?.panelists?.length" class="text-sm text-muted-foreground">
                {{ t('hr.no_panelists') }}
            </div>

            <div v-else class="space-y-4">
                <div>
                    <label class="mb-1 block text-xs text-muted-foreground">{{ t('hr.panelist') }}</label>
                    <select
                        v-model="activePanelist"
                        class="w-full rounded border border-border bg-background px-2 py-1.5 text-sm"
                    >
                        <option v-for="panelist in activeInterview.panelists" :key="panelist.id" :value="panelist">
                            {{ panelist.employee_name ?? panelist.user_name }}
                            {{ panelist.has_submitted ? `(${t('hr.submitted')})` : '' }}
                        </option>
                    </select>
                </div>

                <NextInput :label="t('hr.score')" type="number" step="any" v-model="feedbackForm.score" :error="feedbackForm.errors?.score" />

                <NextSelect
                    :options="filterOptions?.recommendations || []" v-model="feedbackForm.selected_recommendation"
                    @update:modelValue="(v) => { feedbackForm.recommendation = v?.id ?? null }"
                    label-key="name" value-key="id" :reduce="(x) => x"
                    :floating-text="t('hr.recommendation')" :error="feedbackForm.errors?.recommendation"
                    append-in-dialog
                />

                <NextTextarea :label="t('hr.feedback')" v-model="feedbackForm.feedback" />

                <div class="flex justify-end">
                    <Button type="button" size="sm" variant="outline" @click="completeInterview(activeInterview)">
                        {{ t('hr.complete_interview') }}
                    </Button>
                </div>
            </div>
        </ModalDialog>
    </AppLayout>
</template>
