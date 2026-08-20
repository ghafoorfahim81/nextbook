<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import ModalDialog from '@/Components/next/Dialog.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import { Calculator, FileText, Send, CheckCircle2, BookCheck, Undo2, XCircle } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { useAuth } from '@/composables/useAuth';

const props = defineProps({ payroll: Object });

const { t } = useI18n();
const { can } = useAuth();

const run = computed(() => props.payroll?.data ?? props.payroll);
const lines = computed(() => run.value?.lines ?? []);

const statusTone = computed(() => ({
    draft: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    calculated: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-200',
    pending_approval: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    approved: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200',
    posted: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200',
    paid: 'bg-emerald-200 text-emerald-900 dark:bg-emerald-900 dark:text-emerald-100',
    cancelled: 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    reversed: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-200',
}[run.value?.status] ?? 'bg-slate-100 text-slate-700'));

/**
 * The buttons come from allowed_transitions, which the server derives from
 * PayrollStatus. A state machine change therefore never leaves a dead button
 * behind, and never hides one that should be there.
 */
const TRANSITIONS = {
    pending_approval: { label: 'hr.submit_for_approval', icon: Send, variant: 'default', permission: 'payrolls.update' },
    approved: { label: 'hr.approve', icon: CheckCircle2, variant: 'default', permission: 'payrolls.approve' },
    posted: { label: 'hr.post_to_ledger', icon: BookCheck, variant: 'default', permission: 'payrolls.approve' },
    reversed: { label: 'hr.reverse', icon: Undo2, variant: 'destructive', permission: 'payrolls.approve', needsReason: true },
    cancelled: { label: 'hr.cancel_run', icon: XCircle, variant: 'outline', permission: 'payrolls.approve', needsReason: true },
    draft: null,
    calculated: null,
    paid: null,
};

const availableTransitions = computed(() => (run.value?.allowed_transitions ?? [])
    .map((status) => ({ status, ...(TRANSITIONS[status] ?? {}) }))
    .filter((item) => item.label && can(item.permission)));

const reasonOpen = ref(false);
const pendingStatus = ref(null);
const reason = ref('');
const processing = ref(false);

const startTransition = (item) => {
    if (item.needsReason) {
        pendingStatus.value = item.status;
        reason.value = '';
        reasonOpen.value = true;
        return;
    }

    submitTransition(item.status);
};

const submitTransition = (status, withReason = null) => {
    processing.value = true;

    router.patch(route('payrolls.transition', run.value.id), {
        status,
        reason: withReason,
    }, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
            reasonOpen.value = false;
        },
    });
};

const calculate = () => {
    processing.value = true;
    router.patch(route('payrolls.calculate', run.value.id), {}, {
        preserveScroll: true,
        onFinish: () => { processing.value = false; },
    });
};

const openPayslip = (line) => {
    router.get(route('payrolls.payslip', { payroll: run.value.id, line: line.id }));
};

const money = (value) => Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const paymentTone = (status) => ({
    unpaid: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-200',
    partial: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    paid: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200',
}[status] ?? 'bg-slate-100 text-slate-700');
</script>

<template>
    <AppLayout :title="`${t('hr.payroll')} #${run.number}`">
        <div class="space-y-5">
            <!-- Header -->
            <div class="flex flex-wrap items-start justify-between gap-4 rounded-xl border border-border bg-card p-5">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-semibold">{{ t('hr.payroll') }} #{{ run.number }}</h1>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusTone">
                            {{ run.status_label }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ run.period_label || '' }} · {{ run.period_start }} — {{ run.period_end }}
                        <template v-if="run.department_name"> · {{ run.department_name }}</template>
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="run.is_recalculable && can('payrolls.update')"
                        variant="outline"
                        :disabled="processing"
                        @click="calculate"
                    >
                        <Calculator class="mr-1.5 h-4 w-4" />
                        {{ lines.length ? t('hr.recalculate') : t('hr.calculate') }}
                    </Button>

                    <Button
                        v-for="item in availableTransitions"
                        :key="item.status"
                        :variant="item.variant"
                        :disabled="processing"
                        @click="startTransition(item)"
                    >
                        <component :is="item.icon" class="mr-1.5 h-4 w-4" />
                        {{ t(item.label) }}
                    </Button>
                </div>
            </div>

            <!-- Totals -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
                <div class="rounded-xl border border-border bg-card p-4">
                    <p class="text-xs text-muted-foreground">{{ t('hr.employees') }}</p>
                    <p class="mt-1 text-lg font-semibold">{{ run.employee_count }}</p>
                </div>
                <div class="rounded-xl border border-border bg-card p-4">
                    <p class="text-xs text-muted-foreground">{{ t('hr.gross') }}</p>
                    <p class="mt-1 text-lg font-semibold">{{ money(run.total_gross) }}</p>
                </div>
                <div class="rounded-xl border border-border bg-card p-4">
                    <p class="text-xs text-muted-foreground">{{ t('hr.deductions') }}</p>
                    <p class="mt-1 text-lg font-semibold">{{ money(run.total_deductions) }}</p>
                </div>
                <div class="rounded-xl border border-border bg-card p-4">
                    <p class="text-xs text-muted-foreground">{{ t('hr.tax') }}</p>
                    <p class="mt-1 text-lg font-semibold">{{ money(run.total_tax) }}</p>
                </div>
                <div class="rounded-xl border border-border bg-card p-4">
                    <p class="text-xs text-muted-foreground">{{ t('hr.net_payable') }}</p>
                    <p class="mt-1 text-lg font-semibold text-primary">{{ money(run.total_net) }}</p>
                </div>
            </div>

            <div v-if="run.cancellation_reason" class="rounded-xl border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-200">
                <strong>{{ t('hr.reason') }}:</strong> {{ run.cancellation_reason }}
            </div>

            <!-- Payslips -->
            <div class="rounded-xl border border-border bg-card">
                <div class="border-b border-border px-5 py-3">
                    <h2 class="text-base font-semibold">{{ t('hr.payslips') }}</h2>
                </div>

                <div v-if="!lines.length" class="px-5 py-10 text-center text-sm text-muted-foreground">
                    {{ t('hr.no_payslips_yet') }}
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[1000px] text-sm">
                        <thead class="bg-muted/50 text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2 text-start">{{ t('hr.employee') }}</th>
                                <th class="px-4 py-2 text-end">{{ t('hr.present_days') }}</th>
                                <th class="px-4 py-2 text-end">{{ t('hr.absent_days') }}</th>
                                <th class="px-4 py-2 text-end">{{ t('hr.overtime_hours') }}</th>
                                <th class="px-4 py-2 text-end">{{ t('hr.gross') }}</th>
                                <th class="px-4 py-2 text-end">{{ t('hr.deductions') }}</th>
                                <th class="px-4 py-2 text-end">{{ t('hr.tax') }}</th>
                                <th class="px-4 py-2 text-end">{{ t('hr.net_payable') }}</th>
                                <th class="px-4 py-2 text-end">{{ t('hr.paid') }}</th>
                                <th class="px-4 py-2 text-center">{{ t('general.status') }}</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="line in lines" :key="line.id" class="border-t border-border hover:bg-muted/30">
                                <td class="px-4 py-2">
                                    <div class="font-medium">{{ line.employee_name }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ line.employee_code }}
                                        <template v-if="line.currency_code"> · {{ line.currency_code }}</template>
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-end">{{ line.present_days }}</td>
                                <td class="px-4 py-2 text-end">{{ line.absent_days }}</td>
                                <td class="px-4 py-2 text-end">{{ line.overtime_hours }}</td>
                                <td class="px-4 py-2 text-end">{{ money(line.gross_earnings) }}</td>
                                <td class="px-4 py-2 text-end">{{ money(line.total_deductions) }}</td>
                                <td class="px-4 py-2 text-end">{{ money(line.tax_amount) }}</td>
                                <td class="px-4 py-2 text-end font-medium">{{ money(line.net_payable) }}</td>
                                <td class="px-4 py-2 text-end">{{ money(line.paid_amount) }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="paymentTone(line.payment_status)">
                                        {{ line.payment_status_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-end">
                                    <button
                                        v-if="run.is_posted"
                                        type="button"
                                        class="text-muted-foreground transition hover:text-primary"
                                        :title="t('hr.view_payslip')"
                                        @click="openPayslip(line)"
                                    >
                                        <FileText class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Reversal and cancellation both need a reason on the record. -->
        <ModalDialog
            :open="reasonOpen"
            :title="t('hr.reason_required')"
            :confirmText="t('general.confirm')"
            :cancel-text="t('general.close')"
            @update:open="reasonOpen = $event"
            @confirm="submitTransition(pendingStatus, reason)"
            @cancel="reasonOpen = false"
            :submitting="processing"
        >
            <p class="mb-3 text-sm text-muted-foreground">{{ t('hr.reason_hint') }}</p>
            <NextTextarea :label="t('hr.reason')" v-model="reason" />
        </ModalDialog>
    </AppLayout>
</template>
