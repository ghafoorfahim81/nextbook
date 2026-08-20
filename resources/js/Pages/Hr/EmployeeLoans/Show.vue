<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import ModalDialog from '@/Components/next/Dialog.vue';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextDatePicker from '@/Components/next/NextDatePicker.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import { Send, CheckCircle2, XCircle, HandCoins, Banknote, FileX } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { useAuth } from '@/composables/useAuth';

const props = defineProps({
    employeeLoan: Object,
    filterOptions: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const { can } = useAuth();

const loan = computed(() => props.employeeLoan?.data ?? props.employeeLoan);
const repayments = computed(() => loan.value?.repayments ?? []);
const bankAccounts = computed(() => props.filterOptions?.bankAccounts || []);

const statusTone = computed(() => ({
    draft: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    pending_approval: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    approved: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200',
    active: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-200',
    settled: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200',
    written_off: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-200',
    cancelled: 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
}[loan.value?.status] ?? 'bg-slate-100 text-slate-700'));

const money = (value) => Number(value ?? 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const repaidPercent = computed(() => {
    const principal = Number(loan.value?.principal_amount || 0);
    if (!principal) return 0;

    return Math.min(100, Math.round((Number(loan.value?.repaid_amount || 0) / principal) * 100));
});

const processing = ref(false);

const patchTo = (routeName, data = {}) => {
    processing.value = true;
    router.patch(route(routeName, loan.value.id), data, {
        preserveScroll: true,
        onFinish: () => { processing.value = false; },
    });
};

// Approval and disbursement are separate on purpose: the person who
// authorises a staff loan is usually not the one who opens the safe.
const canSubmit = computed(() => loan.value?.status === 'draft' && can('loans.update'));
const canApprove = computed(() => ['draft', 'pending_approval'].includes(loan.value?.status) && can('loans.approve'));
const canDisburse = computed(() => loan.value?.status === 'approved' && can('loans.approve'));
const canRepay = computed(() => loan.value?.is_disbursed && Number(loan.value?.outstanding_amount) > 0 && can('loans.update'));
const canWriteOff = computed(() => loan.value?.is_disbursed && Number(loan.value?.outstanding_amount) > 0 && can('loans.approve'));

// --- Disburse ---
const disburseOpen = ref(false);
const disburseForm = useForm({ bank_account_id: null, selected_account: null });

const submitDisburse = () => {
    router.patch(route('employee-loans.disburse', loan.value.id), {
        bank_account_id: disburseForm.bank_account_id,
    }, {
        preserveScroll: true,
        onFinish: () => { disburseOpen.value = false; },
    });
};

// --- Repay in cash ---
const repayOpen = ref(false);
const repayForm = useForm({
    date: '',
    amount: null,
    bank_account_id: null,
    remark: '',
    selected_account: null,
});

const submitRepay = () => {
    repayForm.transform((data) => Object.fromEntries(
        Object.entries(data).filter(([k]) => !k.startsWith('selected_'))
    ));

    repayForm.post(route('employee-loans.repay', loan.value.id), {
        preserveScroll: true,
        onSuccess: () => { repayOpen.value = false; repayForm.reset(); },
    });
};

// --- Write off ---
const writeOffOpen = ref(false);
const writeOffForm = useForm({ date: '', reason: '' });

const submitWriteOff = () => {
    writeOffForm.patch(route('employee-loans.write-off', loan.value.id), {
        preserveScroll: true,
        onSuccess: () => { writeOffOpen.value = false; },
    });
};

const rejectOpen = ref(false);
const rejectReason = ref('');
</script>

<template>
    <AppLayout :title="`${t('hr.employee_loan')} #${loan.number}`">
        <div class="mx-auto max-w-4xl space-y-5">
            <!-- Header -->
            <div class="flex flex-wrap items-start justify-between gap-4 rounded-xl border border-border bg-card p-5">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-semibold">{{ t('hr.employee_loan') }} #{{ loan.number }}</h1>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusTone">
                            {{ loan.status_label }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ loan.employee_name }} · {{ loan.loan_type_label }} · {{ loan.issue_date }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button v-if="canSubmit" variant="outline" :disabled="processing" @click="patchTo('employee-loans.submit')">
                        <Send class="mr-1.5 h-4 w-4" /> {{ t('hr.submit_for_approval') }}
                    </Button>
                    <Button v-if="canApprove" :disabled="processing" @click="patchTo('employee-loans.approve')">
                        <CheckCircle2 class="mr-1.5 h-4 w-4" /> {{ t('hr.approve') }}
                    </Button>
                    <Button v-if="canApprove" variant="outline" :disabled="processing" @click="rejectOpen = true">
                        <XCircle class="mr-1.5 h-4 w-4" /> {{ t('hr.reject') }}
                    </Button>
                    <Button v-if="canDisburse" :disabled="processing" @click="disburseOpen = true">
                        <HandCoins class="mr-1.5 h-4 w-4" /> {{ t('hr.disburse') }}
                    </Button>
                    <Button v-if="canRepay" variant="outline" :disabled="processing" @click="repayOpen = true">
                        <Banknote class="mr-1.5 h-4 w-4" /> {{ t('hr.record_repayment') }}
                    </Button>
                    <Button v-if="canWriteOff" variant="destructive" :disabled="processing" @click="writeOffOpen = true">
                        <FileX class="mr-1.5 h-4 w-4" /> {{ t('hr.write_off') }}
                    </Button>
                </div>
            </div>

            <!-- Balance -->
            <div class="rounded-xl border border-border bg-card p-5">
                <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <div>
                        <p class="text-xs text-muted-foreground">{{ t('hr.principal') }}</p>
                        <p class="mt-1 text-lg font-semibold">{{ money(loan.principal_amount) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">{{ t('hr.repaid') }}</p>
                        <p class="mt-1 text-lg font-semibold">{{ money(loan.repaid_amount) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">{{ t('hr.outstanding') }}</p>
                        <p class="mt-1 text-lg font-semibold text-primary">{{ money(loan.outstanding_amount) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">{{ t('hr.installment') }}</p>
                        <p class="mt-1 text-lg font-semibold">
                            {{ money(loan.installment_amount) }}
                            <span class="text-xs font-normal text-muted-foreground">× {{ loan.installments_count }}</span>
                        </p>
                    </div>
                </div>

                <div class="mt-4 h-2 overflow-hidden rounded-full bg-muted">
                    <div class="h-full rounded-full bg-primary transition-all" :style="{ width: `${repaidPercent}%` }"></div>
                </div>
                <p class="mt-1 text-xs text-muted-foreground">{{ repaidPercent }}% {{ t('hr.repaid') }}</p>

                <p v-if="!loan.deduct_from_payroll" class="mt-3 text-sm text-muted-foreground">
                    {{ t('hr.not_deducted_from_payroll') }}
                </p>
            </div>

            <!-- Repayments -->
            <div class="rounded-xl border border-border bg-card">
                <div class="border-b border-border px-5 py-3">
                    <h2 class="text-base font-semibold">{{ t('hr.repayments') }}</h2>
                </div>

                <div v-if="!repayments.length" class="px-5 py-8 text-center text-sm text-muted-foreground">
                    {{ t('hr.no_repayments_yet') }}
                </div>

                <table v-else class="w-full text-sm">
                    <thead class="bg-muted/50 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 text-start">{{ t('general.date') }}</th>
                            <th class="px-4 py-2 text-start">{{ t('hr.source') }}</th>
                            <th class="px-4 py-2 text-end">{{ t('general.amount') }}</th>
                            <th class="px-4 py-2 text-start">{{ t('admin.shared.remark') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="repayment in repayments" :key="repayment.id" class="border-t border-border">
                            <td class="px-4 py-2">{{ repayment.date }}</td>
                            <td class="px-4 py-2">{{ repayment.source_label }}</td>
                            <td class="px-4 py-2 text-end font-medium">{{ money(repayment.amount) }}</td>
                            <td class="px-4 py-2 text-muted-foreground">{{ repayment.remark ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Disburse -->
        <ModalDialog
            :open="disburseOpen"
            :title="t('hr.disburse')"
            :confirmText="t('general.confirm')"
            :cancel-text="t('general.close')"
            @update:open="disburseOpen = $event"
            @confirm="submitDisburse"
            @cancel="disburseOpen = false"
        >
            <p class="mb-3 text-sm text-muted-foreground">{{ t('hr.disburse_hint') }}</p>
            <NextSelect
                :options="bankAccounts" v-model="disburseForm.selected_account"
                @update:modelValue="(v) => { disburseForm.bank_account_id = v?.id ?? null }"
                label-key="name" value-key="id" :reduce="(x) => x"
                :floating-text="t('hr.paid_from')"
                append-in-dialog
            />
        </ModalDialog>

        <!-- Repay in cash -->
        <ModalDialog
            :open="repayOpen"
            :title="t('hr.record_repayment')"
            :confirmText="t('general.save')"
            :cancel-text="t('general.close')"
            @update:open="repayOpen = $event"
            @confirm="submitRepay"
            @cancel="repayOpen = false"
            :submitting="repayForm.processing"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <NextDatePicker :label="t('general.date')" v-model="repayForm.date" :error="repayForm.errors?.date" />
                <NextInput :label="t('general.amount')" type="number" step="any" v-model="repayForm.amount" :error="repayForm.errors?.amount" />
                <div class="md:col-span-2">
                    <NextSelect
                        :options="bankAccounts" v-model="repayForm.selected_account"
                        @update:modelValue="(v) => { repayForm.bank_account_id = v?.id ?? null }"
                        label-key="name" value-key="id" :reduce="(x) => x"
                        :floating-text="t('hr.received_into')" :error="repayForm.errors?.bank_account_id"
                        append-in-dialog
                    />
                </div>
                <div class="md:col-span-2">
                    <NextTextarea :label="t('admin.shared.remark')" v-model="repayForm.remark" />
                </div>
            </div>
            <p class="mt-2 text-xs text-muted-foreground">
                {{ t('hr.outstanding') }}: {{ money(loan.outstanding_amount) }}
            </p>
        </ModalDialog>

        <!-- Write off -->
        <ModalDialog
            :open="writeOffOpen"
            :title="t('hr.write_off')"
            :confirmText="t('general.confirm')"
            :cancel-text="t('general.close')"
            @update:open="writeOffOpen = $event"
            @confirm="submitWriteOff"
            @cancel="writeOffOpen = false"
            :submitting="writeOffForm.processing"
        >
            <p class="mb-3 text-sm text-muted-foreground">{{ t('hr.write_off_hint') }}</p>
            <div class="space-y-4">
                <NextDatePicker :label="t('general.date')" v-model="writeOffForm.date" :error="writeOffForm.errors?.date" />
                <NextTextarea :label="t('hr.reason')" v-model="writeOffForm.reason" />
            </div>
        </ModalDialog>

        <!-- Reject -->
        <ModalDialog
            :open="rejectOpen"
            :title="t('hr.reject')"
            :confirmText="t('general.confirm')"
            :cancel-text="t('general.close')"
            @update:open="rejectOpen = $event"
            @confirm="() => { patchTo('employee-loans.reject', { reason: rejectReason }); rejectOpen = false; }"
            @cancel="rejectOpen = false"
        >
            <NextTextarea :label="t('hr.reason')" v-model="rejectReason" />
        </ModalDialog>
    </AppLayout>
</template>
