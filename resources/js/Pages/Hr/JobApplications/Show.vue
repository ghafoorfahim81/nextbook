<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import ModalDialog from '@/Components/next/Dialog.vue';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import NextDatePicker from '@/Components/next/NextDatePicker.vue';
import { ArrowLeft, CalendarPlus, UserCheck } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { useAuth } from '@/composables/useAuth';

const props = defineProps({
    jobApplication: Object,
    filterOptions: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const { can } = useAuth();

const application = computed(() => props.jobApplication?.data ?? props.jobApplication);
const interviews = computed(() => application.value?.interviews ?? []);

const statusTone = computed(() => ({
    applied: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    shortlisted: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-200',
    interviewing: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200',
    offered: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    hired: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200',
    rejected: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-200',
    withdrawn: 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
}[application.value?.status] ?? 'bg-slate-100 text-slate-700'));

const STATUS_LABELS = {
    shortlisted: 'hr.shortlist',
    interviewing: 'hr.mark_interviewing',
    offered: 'hr.make_offer',
    rejected: 'hr.reject',
    withdrawn: 'hr.mark_withdrawn',
};

// `hired` is excluded on purpose: it creates an Employee, so it has its own
// dialog rather than being one more status button.
const pipelineActions = computed(() => (application.value?.allowed_transitions ?? [])
    .filter((status) => status !== 'hired' && STATUS_LABELS[status])
    .map((status) => ({ status, label: STATUS_LABELS[status] })));

const canHire = computed(() => (application.value?.allowed_transitions ?? []).includes('hired')
    && can('job_applications.update'));

const processing = ref(false);

// --- Status changes ---
const statusOpen = ref(false);
const pendingStatus = ref(null);
const statusForm = useForm({ rejection_reason: '', offered_salary: null, offered_date: '' });

const startTransition = (status) => {
    // An offer needs terms, and a rejection needs a reason worth keeping.
    if (['offered', 'rejected'].includes(status)) {
        pendingStatus.value = status;
        statusOpen.value = true;
        return;
    }

    submitTransition(status);
};

const submitTransition = (status, extra = {}) => {
    processing.value = true;

    router.patch(route('job-applications.transition', application.value.id), { status, ...extra }, {
        preserveScroll: true,
        onFinish: () => { processing.value = false; statusOpen.value = false; },
    });
};

// --- Schedule an interview ---
const interviewOpen = ref(false);
const interviewForm = useForm({
    job_application_id: null,
    interview_type: 'in_person',
    scheduled_at: '',
    duration_minutes: 60,
    location: '',
    meeting_link: '',
    panelists: [],
    selected_type: null,
    selected_panelist: null,
});

const interviewTypes = [
    { id: 'phone_screen', name: 'phone_screen' },
    { id: 'in_person', name: 'in_person' },
    { id: 'video', name: 'video' },
    { id: 'technical', name: 'technical' },
    { id: 'panel', name: 'panel' },
    { id: 'written_test', name: 'written_test' },
    { id: 'final', name: 'final' },
].map((item) => ({ id: item.id, name: t(`enums.interview_type.${item.id}`) }));

const isRemoteInterview = computed(() => ['video', 'phone_screen'].includes(interviewForm.interview_type));

const addPanelist = (employee) => {
    if (!employee?.id) return;
    if (interviewForm.panelists.some((p) => p.employee_id === employee.id)) return;

    interviewForm.panelists.push({
        employee_id: employee.id,
        name: employee.name,
        is_lead: interviewForm.panelists.length === 0,
    });
    interviewForm.selected_panelist = null;
};

const removePanelist = (index) => interviewForm.panelists.splice(index, 1);

const submitInterview = () => {
    interviewForm.job_application_id = application.value.id;

    interviewForm.transform((data) => ({
        ...Object.fromEntries(Object.entries(data).filter(([k]) => !k.startsWith('selected_'))),
        panelists: data.panelists.map(({ employee_id, is_lead }) => ({ employee_id, is_lead })),
    }));

    interviewForm.post(route('interviews.store'), {
        preserveScroll: true,
        onSuccess: () => { interviewOpen.value = false; interviewForm.reset(); },
    });
};

// --- Hire ---
const hireOpen = ref(false);
const hireForm = useForm({
    joining_date: '',
    probation_end_date: '',
    basic_salary: null,
});

const submitHire = () => {
    hireForm.basic_salary = hireForm.basic_salary ?? application.value.offered_salary;
    hireForm.post(route('job-applications.hire', application.value.id));
};

const interviewTone = (status) => ({
    scheduled: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-200',
    completed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200',
    cancelled: 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    rescheduled: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    no_show: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-200',
}[status] ?? 'bg-slate-100 text-slate-700');
</script>

<template>
    <AppLayout :title="application.full_name">
        <div class="mx-auto max-w-5xl space-y-5">
            <!-- Header -->
            <div class="flex flex-wrap items-start justify-between gap-4 rounded-xl border border-border bg-card p-5">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-semibold">{{ application.full_name }}</h1>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusTone">
                            {{ application.status_label }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ application.application_number }}
                        <template v-if="application.job_opening_title"> · {{ application.job_opening_title }}</template>
                        · {{ application.source_label }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" @click="$inertia.get(route('job-applications.index'))">
                        <ArrowLeft class="mr-1.5 h-4 w-4" /> {{ t('general.back') }}
                    </Button>

                    <Button
                        v-if="application.can_be_interviewed && can('interviews.create')"
                        variant="outline"
                        @click="interviewOpen = true"
                    >
                        <CalendarPlus class="mr-1.5 h-4 w-4" /> {{ t('hr.schedule_interview') }}
                    </Button>

                    <Button
                        v-for="action in pipelineActions"
                        :key="action.status"
                        :variant="action.status === 'rejected' ? 'destructive' : 'outline'"
                        :disabled="processing"
                        @click="startTransition(action.status)"
                    >
                        {{ t(action.label) }}
                    </Button>

                    <Button v-if="canHire" :disabled="processing" @click="hireOpen = true">
                        <UserCheck class="mr-1.5 h-4 w-4" /> {{ t('hr.hire') }}
                    </Button>
                </div>
            </div>

            <div v-if="application.hired_employee_id" class="rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
                {{ t('hr.hired_as', { name: application.hired_employee_name }) }}
            </div>

            <div v-if="application.rejection_reason" class="rounded-xl border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-200">
                <strong>{{ t('hr.reason') }}:</strong> {{ application.rejection_reason }}
            </div>

            <!-- Candidate detail -->
            <div class="grid grid-cols-2 gap-4 rounded-xl border border-border bg-card p-5 md:grid-cols-4">
                <div>
                    <p class="text-xs text-muted-foreground">{{ t('general.phone') }}</p>
                    <p class="font-medium">{{ application.phone_number ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">{{ t('general.email') }}</p>
                    <p class="font-medium">{{ application.email ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">{{ t('hr.experience') }}</p>
                    <p class="font-medium">{{ application.years_of_experience ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">{{ t('hr.highest_education') }}</p>
                    <p class="font-medium">{{ application.highest_education ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">{{ t('hr.current_employer') }}</p>
                    <p class="font-medium">{{ application.current_employer ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">{{ t('hr.expected_salary') }}</p>
                    <p class="font-medium">{{ application.expected_salary ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">{{ t('hr.offered_salary') }}</p>
                    <p class="font-medium">{{ application.offered_salary ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">{{ t('hr.score') }}</p>
                    <p class="font-medium">{{ application.score ?? '—' }}</p>
                </div>
            </div>

            <!-- Interviews -->
            <div class="rounded-xl border border-border bg-card">
                <div class="border-b border-border px-5 py-3">
                    <h2 class="text-base font-semibold">{{ t('hr.interviews') }}</h2>
                </div>

                <div v-if="!interviews.length" class="px-5 py-8 text-center text-sm text-muted-foreground">
                    {{ t('hr.no_interviews_yet') }}
                </div>

                <div v-else class="divide-y divide-border">
                    <div v-for="interview in interviews" :key="interview.id" class="px-5 py-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-medium">
                                    {{ t('hr.round') }} {{ interview.round }} — {{ interview.interview_type_label }}
                                </span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="interviewTone(interview.status)">
                                    {{ interview.status_label }}
                                </span>
                            </div>
                            <div class="text-sm text-muted-foreground">
                                {{ interview.scheduled_at }}
                                <template v-if="interview.score !== null"> · {{ t('hr.score') }} {{ interview.score }}</template>
                            </div>
                        </div>

                        <!-- Per-panelist verdicts. Shown individually because
                             one strong objection is the thing an average hides. -->
                        <div v-if="interview.panelists?.length" class="mt-3 space-y-1">
                            <div
                                v-for="panelist in interview.panelists"
                                :key="panelist.id"
                                class="flex flex-wrap items-center gap-2 text-sm"
                            >
                                <span class="font-medium">{{ panelist.employee_name ?? panelist.user_name ?? '—' }}</span>
                                <span v-if="panelist.is_lead" class="rounded bg-muted px-1.5 py-0.5 text-xs">{{ t('hr.lead') }}</span>
                                <span v-if="panelist.recommendation_label" class="text-muted-foreground">
                                    · {{ panelist.recommendation_label }}
                                </span>
                                <span v-if="panelist.score !== null" class="text-muted-foreground">· {{ panelist.score }}/10</span>
                                <span v-if="!panelist.has_submitted" class="text-xs text-amber-600">
                                    ({{ t('hr.awaiting_feedback') }})
                                </span>
                            </div>
                        </div>

                        <p v-if="interview.panel_verdict_label" class="mt-2 text-sm">
                            <strong>{{ t('hr.panel_verdict') }}:</strong> {{ interview.panel_verdict_label }}
                        </p>
                        <p v-if="interview.feedback" class="mt-1 whitespace-pre-line text-sm text-muted-foreground">
                            {{ interview.feedback }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Offer / rejection details -->
        <ModalDialog
            :open="statusOpen"
            :title="pendingStatus === 'offered' ? t('hr.make_offer') : t('hr.reject')"
            :confirmText="t('general.confirm')"
            :cancel-text="t('general.close')"
            @update:open="statusOpen = $event"
            @confirm="submitTransition(pendingStatus, {
                rejection_reason: statusForm.rejection_reason,
                offered_salary: statusForm.offered_salary,
                offered_date: statusForm.offered_date,
            })"
            @cancel="statusOpen = false"
        >
            <div v-if="pendingStatus === 'offered'" class="space-y-4">
                <NextInput :label="t('hr.offered_salary')" type="number" step="any" v-model="statusForm.offered_salary" />
                <NextDatePicker :label="t('hr.offered_date')" v-model="statusForm.offered_date" />
            </div>
            <div v-else>
                <p class="mb-3 text-sm text-muted-foreground">{{ t('hr.rejection_reason_hint') }}</p>
                <NextTextarea :label="t('hr.reason')" v-model="statusForm.rejection_reason" />
            </div>
        </ModalDialog>

        <!-- Schedule an interview -->
        <ModalDialog
            :open="interviewOpen"
            :title="t('hr.schedule_interview')"
            :confirmText="t('general.save')"
            :cancel-text="t('general.close')"
            width="w-[95vw] max-w-[95vw] sm:w-[720px] sm:max-w-[720px]"
            @update:open="interviewOpen = $event"
            @confirm="submitInterview"
            @cancel="interviewOpen = false"
            :submitting="interviewForm.processing"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <NextSelect
                    :options="interviewTypes" v-model="interviewForm.selected_type"
                    @update:modelValue="(v) => { interviewForm.interview_type = v?.id ?? null }"
                    label-key="name" value-key="id" :reduce="(x) => x"
                    :floating-text="t('hr.interview_type')" :error="interviewForm.errors?.interview_type"
                    append-in-dialog
                />
                <NextInput
                    :label="t('hr.scheduled_at')" type="datetime-local"
                    v-model="interviewForm.scheduled_at" :error="interviewForm.errors?.scheduled_at"
                />
                <NextInput :label="t('hr.duration_minutes')" type="number" v-model="interviewForm.duration_minutes" />

                <NextInput
                    v-if="isRemoteInterview"
                    :label="t('hr.meeting_link')" v-model="interviewForm.meeting_link"
                    :error="interviewForm.errors?.meeting_link"
                />
                <NextInput v-else :label="t('hr.location')" v-model="interviewForm.location" />

                <div class="md:col-span-2">
                    <NextSelect
                        v-model="interviewForm.selected_panelist"
                        @update:modelValue="addPanelist"
                        resource-type="employees"
                        label-key="name" value-key="id" :reduce="(x) => x"
                        :floating-text="t('hr.add_panelist')"
                        append-in-dialog
                    />

                    <div v-if="interviewForm.panelists.length" class="mt-2 flex flex-wrap gap-2">
                        <span
                            v-for="(panelist, index) in interviewForm.panelists"
                            :key="panelist.employee_id"
                            class="inline-flex items-center gap-2 rounded-full bg-muted px-3 py-1 text-sm"
                        >
                            {{ panelist.name }}
                            <span v-if="panelist.is_lead" class="text-xs text-muted-foreground">({{ t('hr.lead') }})</span>
                            <button type="button" class="text-muted-foreground hover:text-destructive" @click="removePanelist(index)">×</button>
                        </span>
                    </div>
                    <p v-if="interviewForm.errors?.panelists" class="mt-1 text-xs text-destructive">
                        {{ interviewForm.errors.panelists }}
                    </p>
                </div>
            </div>
        </ModalDialog>

        <!-- Hire -->
        <ModalDialog
            :open="hireOpen"
            :title="t('hr.hire')"
            :confirmText="t('general.confirm')"
            :cancel-text="t('general.close')"
            @update:open="hireOpen = $event"
            @confirm="submitHire"
            @cancel="hireOpen = false"
            :submitting="hireForm.processing"
        >
            <p class="mb-3 text-sm text-muted-foreground">{{ t('hr.hire_hint') }}</p>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <NextDatePicker :label="t('hr.joining_date')" v-model="hireForm.joining_date" :error="hireForm.errors?.joining_date" />
                <NextDatePicker :label="t('hr.probation_end_date')" v-model="hireForm.probation_end_date" :error="hireForm.errors?.probation_end_date" />
                <div class="md:col-span-2">
                    <NextInput
                        :label="t('hr.basic_salary')" type="number" step="any"
                        v-model="hireForm.basic_salary"
                        :placeholder="application.offered_salary"
                        :error="hireForm.errors?.basic_salary"
                    />
                </div>
            </div>
        </ModalDialog>
    </AppLayout>
</template>
