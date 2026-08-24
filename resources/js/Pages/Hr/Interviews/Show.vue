<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import FormPageToolbar from '@/Components/FormPageToolbar.vue';
import { Button } from '@/Components/ui/button';
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useAuth } from '@/composables/useAuth';
import { CheckCircle2, FileUser } from 'lucide-vue-next';

const { t } = useI18n();
const { can } = useAuth();

const props = defineProps({
    interview: { type: Object, required: true },
});

const iv = computed(() => props.interview?.data ?? props.interview);
const panelists = computed(() => iv.value.panelists?.data ?? iv.value.panelists ?? []);

const statusClasses = computed(() => {
    switch (iv.value.status) {
        case 'completed':
            return 'border-green-500/30 bg-green-500/10 text-green-700 dark:text-green-300';
        case 'scheduled':
            return 'border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-300';
        case 'rescheduled':
            return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300';
        case 'no_show':
            return 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300';
        default:
            return 'border-slate-500/30 bg-slate-500/10 text-slate-700 dark:text-slate-300';
    }
});

const complete = () => router.patch(route('interviews.complete', iv.value.id), {}, { preserveScroll: true });
</script>

<template>
    <AppLayout :title="`${t('hr.interview')} — ${iv.candidate_name || ''}`">
        <FormPageToolbar back-route="interviews.index" module="interviews" />

        <div class="mb-5 rounded-xl border border-border bg-card p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        {{ iv.candidate_name || '—' }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ t('hr.round') }} {{ iv.round }} · {{ iv.interview_type_label }}
                        <span v-if="iv.scheduled_at"> · {{ iv.scheduled_at }}</span>
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full border px-3 py-1 text-xs" :class="statusClasses">
                        {{ iv.status_label }}
                    </span>
                    <span
                        v-if="iv.panel_verdict_label"
                        class="rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs text-primary"
                    >
                        {{ iv.panel_verdict_label }}
                    </span>

                    <Button
                        v-if="iv.job_application_id"
                        size="sm" variant="outline"
                        class="h-9 gap-1"
                        @click="router.visit(route('job-applications.show', iv.job_application_id))"
                    >
                        <FileUser class="h-4 w-4" />
                        {{ t('hr.job_application') }}
                    </Button>

                    <Button
                        v-if="iv.status === 'scheduled' && can('interviews.update')"
                        size="sm"
                        class="h-9 gap-1 bg-green-600 text-white hover:bg-green-700"
                        @click="complete"
                    >
                        <CheckCircle2 class="h-4 w-4" />
                        {{ t('hr.complete_interview') }}
                    </Button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
            <!-- Detail -->
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm lg:col-span-2">
                <dl class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.candidate') }}</dt><dd>{{ iv.candidate_name || '—' }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.interview_type') }}</dt><dd>{{ iv.interview_type_label || '—' }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.round') }}</dt><dd>{{ iv.round }}</dd></div>

                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.scheduled_at') }}</dt><dd>{{ iv.scheduled_at || '—' }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.duration_minutes') }}</dt><dd>{{ iv.duration_minutes }}</dd></div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            {{ iv.is_remote ? t('hr.meeting_link') : t('hr.location') }}
                        </dt>
                        <dd class="break-all">
                            <a
                                v-if="iv.is_remote && iv.meeting_link"
                                :href="iv.meeting_link" target="_blank" rel="noopener"
                                class="text-primary underline"
                            >{{ iv.meeting_link }}</a>
                            <span v-else>{{ (iv.is_remote ? iv.meeting_link : iv.location) || '—' }}</span>
                        </dd>
                    </div>

                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.score') }}</dt><dd>{{ iv.score ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.recommendation') }}</dt><dd>{{ iv.recommendation_label || '—' }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.panel_verdict') }}</dt><dd>{{ iv.panel_verdict_label || '—' }}</dd></div>

                    <div class="md:col-span-3">
                        <dt class="text-xs text-muted-foreground">{{ t('hr.feedback') }}</dt>
                        <dd class="whitespace-pre-line">{{ iv.feedback || '—' }}</dd>
                    </div>
                    <div class="md:col-span-3">
                        <dt class="text-xs text-muted-foreground">{{ t('admin.shared.remark') }}</dt>
                        <dd>{{ iv.remark || '—' }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Panel -->
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-foreground">{{ t('hr.panelists') }}</h2>

                <p v-if="!panelists.length" class="text-sm text-muted-foreground">
                    {{ t('hr.no_panelists') }}
                </p>

                <ul v-else class="space-y-3">
                    <li
                        v-for="panelist in panelists"
                        :key="panelist.id"
                        class="rounded-lg border border-border p-3 text-sm"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-medium">
                                {{ panelist.employee_name ?? panelist.user_name ?? '—' }}
                                <span v-if="panelist.is_lead" class="ms-1 text-xs text-primary">★</span>
                            </span>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs"
                                :class="panelist.has_submitted
                                    ? 'bg-green-500/10 text-green-700 dark:text-green-300'
                                    : 'bg-amber-500/10 text-amber-700 dark:text-amber-300'"
                            >
                                {{ panelist.has_submitted ? t('hr.feedback') : t('hr.pending_days') }}
                            </span>
                        </div>
                        <p v-if="panelist.role" class="text-xs text-muted-foreground">{{ panelist.role }}</p>
                        <dl class="mt-2 space-y-1 text-xs">
                            <div v-if="panelist.score !== null" class="flex justify-between">
                                <dt class="text-muted-foreground">{{ t('hr.score') }}</dt>
                                <dd>{{ panelist.score }}</dd>
                            </div>
                            <div v-if="panelist.recommendation_label" class="flex justify-between">
                                <dt class="text-muted-foreground">{{ t('hr.recommendation') }}</dt>
                                <dd>{{ panelist.recommendation_label }}</dd>
                            </div>
                        </dl>
                        <p v-if="panelist.feedback" class="mt-2 whitespace-pre-line text-xs text-muted-foreground">
                            {{ panelist.feedback }}
                        </p>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
