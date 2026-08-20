<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { ArrowLeft, Megaphone, Lock, XCircle } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { useAuth } from '@/composables/useAuth';

const props = defineProps({
    jobOpening: Object,
    applications: { type: [Array, Object], default: () => [] },
});

const { t } = useI18n();
const { can } = useAuth();

const opening = computed(() => props.jobOpening?.data ?? props.jobOpening);
const candidates = computed(() => props.applications?.data ?? props.applications ?? []);

const statusTone = computed(() => ({
    draft: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    published: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200',
    closed: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    filled: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-200',
    cancelled: 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
}[opening.value?.status] ?? 'bg-slate-100 text-slate-700'));

const TRANSITIONS = {
    published: { label: 'hr.publish', icon: Megaphone, variant: 'default' },
    closed: { label: 'hr.close_opening', icon: Lock, variant: 'outline' },
    filled: { label: 'hr.mark_filled', icon: Lock, variant: 'outline' },
    cancelled: { label: 'hr.cancel_opening', icon: XCircle, variant: 'destructive' },
};

// Same discipline as payroll: the buttons come from allowed_transitions, so
// the UI and the state machine cannot drift apart.
const availableTransitions = computed(() => (opening.value?.allowed_transitions ?? [])
    .map((status) => ({ status, ...(TRANSITIONS[status] ?? {}) }))
    .filter((item) => item.label && can('job_openings.update')));

const processing = ref(false);

const transition = (status) => {
    processing.value = true;
    router.patch(route('job-openings.transition', opening.value.id), { status }, {
        preserveScroll: true,
        onFinish: () => { processing.value = false; },
    });
};

const applicationTone = (status) => ({
    applied: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    shortlisted: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-200',
    interviewing: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200',
    offered: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    hired: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200',
    rejected: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-200',
    withdrawn: 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
}[status] ?? 'bg-slate-100 text-slate-700');
</script>

<template>
    <AppLayout :title="opening.title">
        <div class="mx-auto max-w-5xl space-y-5">
            <div class="flex flex-wrap items-start justify-between gap-4 rounded-xl border border-border bg-card p-5">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-semibold">{{ opening.title }}</h1>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusTone">
                            {{ opening.status_label }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ opening.code }}
                        <template v-if="opening.department_name"> · {{ opening.department_name }}</template>
                        <template v-if="opening.location"> · {{ opening.location }}</template>
                        · {{ opening.employment_type_label }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" @click="$inertia.get(route('job-openings.index'))">
                        <ArrowLeft class="mr-1.5 h-4 w-4" /> {{ t('general.back') }}
                    </Button>
                    <Button
                        v-for="item in availableTransitions"
                        :key="item.status"
                        :variant="item.variant"
                        :disabled="processing"
                        @click="transition(item.status)"
                    >
                        <component :is="item.icon" class="mr-1.5 h-4 w-4" />
                        {{ t(item.label) }}
                    </Button>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <div class="rounded-xl border border-border bg-card p-4">
                    <p class="text-xs text-muted-foreground">{{ t('hr.vacancies') }}</p>
                    <p class="mt-1 text-lg font-semibold">{{ opening.vacancies }}</p>
                </div>
                <div class="rounded-xl border border-border bg-card p-4">
                    <p class="text-xs text-muted-foreground">{{ t('hr.remaining') }}</p>
                    <p class="mt-1 text-lg font-semibold">{{ opening.remaining_vacancies }}</p>
                </div>
                <div class="rounded-xl border border-border bg-card p-4">
                    <p class="text-xs text-muted-foreground">{{ t('hr.applicants') }}</p>
                    <p class="mt-1 text-lg font-semibold">{{ opening.application_count ?? candidates.length }}</p>
                </div>
                <div class="rounded-xl border border-border bg-card p-4">
                    <p class="text-xs text-muted-foreground">{{ t('hr.closing_date') }}</p>
                    <p class="mt-1 font-medium">{{ opening.closing_date ?? '—' }}</p>
                </div>
            </div>

            <div v-if="opening.description || opening.requirements || opening.responsibilities" class="space-y-4 rounded-xl border border-border bg-card p-5">
                <div v-if="opening.description">
                    <h3 class="mb-1 text-sm font-semibold">{{ t('hr.description') }}</h3>
                    <p class="whitespace-pre-line text-sm text-muted-foreground">{{ opening.description }}</p>
                </div>
                <div v-if="opening.responsibilities">
                    <h3 class="mb-1 text-sm font-semibold">{{ t('hr.responsibilities') }}</h3>
                    <p class="whitespace-pre-line text-sm text-muted-foreground">{{ opening.responsibilities }}</p>
                </div>
                <div v-if="opening.requirements">
                    <h3 class="mb-1 text-sm font-semibold">{{ t('hr.requirements') }}</h3>
                    <p class="whitespace-pre-line text-sm text-muted-foreground">{{ opening.requirements }}</p>
                </div>
            </div>

            <!-- The pipeline, best score first. -->
            <div class="rounded-xl border border-border bg-card">
                <div class="border-b border-border px-5 py-3">
                    <h2 class="text-base font-semibold">{{ t('hr.candidates') }}</h2>
                </div>

                <div v-if="!candidates.length" class="px-5 py-8 text-center text-sm text-muted-foreground">
                    {{ t('hr.no_candidates_yet') }}
                </div>

                <table v-else class="w-full text-sm">
                    <thead class="bg-muted/50 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 text-start">{{ t('hr.candidate') }}</th>
                            <th class="px-4 py-2 text-start">{{ t('general.phone') }}</th>
                            <th class="px-4 py-2 text-start">{{ t('hr.source') }}</th>
                            <th class="px-4 py-2 text-end">{{ t('hr.score') }}</th>
                            <th class="px-4 py-2 text-end">{{ t('hr.interviews') }}</th>
                            <th class="px-4 py-2 text-center">{{ t('general.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="candidate in candidates"
                            :key="candidate.id"
                            class="cursor-pointer border-t border-border hover:bg-muted/30"
                            @click="router.get(route('job-applications.show', candidate.id))"
                        >
                            <td class="px-4 py-2">
                                <div class="font-medium">{{ candidate.full_name }}</div>
                                <div class="text-xs text-muted-foreground">{{ candidate.application_number }}</div>
                            </td>
                            <td class="px-4 py-2">{{ candidate.phone_number ?? '—' }}</td>
                            <td class="px-4 py-2">{{ candidate.source_label }}</td>
                            <td class="px-4 py-2 text-end">{{ candidate.score ?? '—' }}</td>
                            <td class="px-4 py-2 text-end">{{ candidate.interview_count ?? 0 }}</td>
                            <td class="px-4 py-2 text-center">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="applicationTone(candidate.status)">
                                    {{ candidate.status_label }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
