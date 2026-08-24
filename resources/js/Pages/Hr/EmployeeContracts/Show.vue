<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import FormPageToolbar from '@/Components/FormPageToolbar.vue';
import AttachmentList from '@/Components/AttachmentList.vue';
import { Button } from '@/Components/ui/button';
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useAuth } from '@/composables/useAuth';
import { Pencil, User } from 'lucide-vue-next';

const { t } = useI18n();
const { can } = useAuth();

const props = defineProps({
    contract: { type: Object, required: true },
});

const c = computed(() => props.contract?.data ?? props.contract);
const attachments = computed(() => c.value.attachments?.data ?? c.value.attachments ?? []);

const statusClasses = computed(() => {
    switch (c.value.status) {
        case 'active':
            return 'border-green-500/30 bg-green-500/10 text-green-700 dark:text-green-300';
        case 'draft':
            return 'border-slate-500/30 bg-slate-500/10 text-slate-700 dark:text-slate-300';
        case 'expired':
        case 'terminated':
            return 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300';
        default:
            return 'border-border bg-muted text-foreground';
    }
});

// Red once lapsed, amber inside the last month — the same reading the list
// gives the end-date column.
const expiryClasses = computed(() => {
    const d = c.value.days_until_expiry;
    if (d === null || d === undefined) return 'text-foreground';
    if (d < 0) return 'text-red-600 dark:text-red-400 font-medium';
    if (d <= 30) return 'text-amber-600 dark:text-amber-400 font-medium';
    return 'text-foreground';
});

const expiryNote = computed(() => {
    const d = c.value.days_until_expiry;
    if (!c.value.end_date || d === null || d === undefined) return '';
    if (d < 0) return t('hr.expired');
    if (d <= 30) return t('hr.expires_in_days', { days: d });
    return '';
});

// The contract is edited through the modal on the list, so "edit" walks back
// there rather than duplicating the form on this page.
const goToList = () => router.visit(route('employee-contracts.index'));
</script>

<template>
    <AppLayout :title="`${t('hr.contract')} ${c.contract_number || ''}`">
        <FormPageToolbar back-route="employee-contracts.index" module="employee_contracts" />

        <!-- Header -->
        <div class="mb-5 rounded-xl border border-border bg-card p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        {{ c.contract_number || '—' }} — {{ c.employee_name }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ c.contract_type_label }}
                        <span v-if="c.start_date"> · {{ c.start_date }} → {{ c.end_date || '—' }}</span>
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span
                        v-if="c.is_current"
                        class="rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs text-primary"
                    >
                        {{ t('hr.is_current') }}
                    </span>
                    <span class="rounded-full border px-3 py-1 text-xs" :class="statusClasses">
                        {{ c.status_label }}
                    </span>

                    <Button
                        v-if="c.employee_id"
                        size="sm" variant="outline"
                        class="h-9 gap-1"
                        @click="router.visit(route('employees.show', c.employee_id))"
                    >
                        <User class="h-4 w-4" />
                        {{ t('hr.employee') }}
                    </Button>

                    <Button
                        v-if="can('employee_contracts.update')"
                        size="sm" variant="outline"
                        class="h-9 gap-1"
                        @click="goToList"
                    >
                        <Pencil class="h-4 w-4" />
                        {{ t('general.edit', { name: '' }) }}
                    </Button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
            <!-- Detail -->
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm lg:col-span-2">
                <dl class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <dt class="text-xs text-muted-foreground">{{ t('hr.employee') }}</dt>
                        <dd>{{ c.employee_name || '—' }}<span v-if="c.employee_code"> ({{ c.employee_code }})</span></dd>
                    </div>
                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.contract_number') }}</dt><dd>{{ c.contract_number || '—' }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.contract_type') }}</dt><dd>{{ c.contract_type_label || '—' }}</dd></div>

                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.start_date') }}</dt><dd>{{ c.start_date || '—' }}</dd></div>
                    <div>
                        <dt class="text-xs text-muted-foreground">{{ t('hr.end_date') }}</dt>
                        <dd :class="expiryClasses">
                            {{ c.end_date || '—' }}
                            <span v-if="expiryNote"> ({{ expiryNote }})</span>
                        </dd>
                    </div>
                    <div><dt class="text-xs text-muted-foreground">{{ t('general.status') }}</dt><dd>{{ c.status_label || '—' }}</dd></div>

                    <div>
                        <dt class="text-xs text-muted-foreground">{{ t('hr.basic_salary') }}</dt>
                        <dd>{{ c.basic_salary }} {{ c.currency_code || '' }}</dd>
                    </div>
                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.probation_months') }}</dt><dd>{{ c.probation_months ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.notice_period_days') }}</dt><dd>{{ c.notice_period_days ?? '—' }}</dd></div>

                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.working_hours_per_day') }}</dt><dd>{{ c.working_hours_per_day ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.working_days_per_week') }}</dt><dd>{{ c.working_days_per_week ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">{{ t('hr.annual_leave_entitlement') }}</dt><dd>{{ c.annual_leave_entitlement ?? '—' }}</dd></div>

                    <div v-if="c.renewed_from_number">
                        <dt class="text-xs text-muted-foreground">{{ t('hr.contract') }}</dt>
                        <dd>{{ c.renewed_from_number }}</dd>
                    </div>
                    <div v-if="c.terminated_on"><dt class="text-xs text-muted-foreground">{{ t('hr.terminated_on') }}</dt><dd>{{ c.terminated_on }}</dd></div>
                    <div v-if="c.termination_reason" class="md:col-span-3">
                        <dt class="text-xs text-muted-foreground">{{ t('hr.termination_reason') }}</dt>
                        <dd class="text-red-600 dark:text-red-400">{{ c.termination_reason }}</dd>
                    </div>

                    <div class="md:col-span-3">
                        <dt class="text-xs text-muted-foreground">{{ t('admin.shared.remark') }}</dt>
                        <dd>{{ c.remark || '—' }}</dd>
                    </div>
                </dl>

                <div class="mt-4">
                    <AttachmentList :items="attachments" :label="t('general.attachments')" />
                </div>
            </div>

            <!-- Meta -->
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-foreground">{{ t('general.info') }}</h2>

                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">{{ t('hr.reminder_days_before') }}</dt>
                        <dd>{{ c.reminder_days_before ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">{{ t('hr.is_current') }}</dt>
                        <dd>{{ c.is_current ? t('general.yes') : t('general.no') }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-border pt-2">
                        <dt class="text-muted-foreground">{{ t('general.created_by') }}</dt>
                        <dd>{{ c.created_by || '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">{{ t('general.updated_by') }}</dt>
                        <dd>{{ c.updated_by || '—' }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </AppLayout>
</template>
