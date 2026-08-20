<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextDatePicker from '@/Components/next/NextDatePicker.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import { Button } from '@/Components/ui/button';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t } = useI18n();

const props = defineProps({
    employeeLoan: { type: Object, default: null },
    filterOptions: { type: Object, default: () => ({}) },
    nextNumber: { type: [String, Number], default: null },
});

const isEditing = computed(() => !!props.employeeLoan?.id);

const loanTypes = computed(() => props.filterOptions?.loanTypes || []);
const currencies = computed(() => props.filterOptions?.currencies || []);
const bankAccounts = computed(() => props.filterOptions?.bankAccounts || []);

const form = useForm({
    employee_id: props.employeeLoan?.employee_id ?? null,
    loan_type: props.employeeLoan?.loan_type ?? 'advance',
    currency_id: props.employeeLoan?.currency_id ?? null,
    rate: props.employeeLoan?.rate ?? 1,
    principal_amount: props.employeeLoan?.principal_amount ?? null,
    installment_amount: props.employeeLoan?.installment_amount ?? null,
    installments_count: props.employeeLoan?.installments_count ?? 1,
    deduct_from_payroll: props.employeeLoan?.deduct_from_payroll ?? true,
    issue_date: props.employeeLoan?.issue_date ?? '',
    first_deduction_period: props.employeeLoan?.first_deduction_period ?? null,
    interest_rate: props.employeeLoan?.interest_rate ?? 0,
    bank_account_id: props.employeeLoan?.bank_account_id ?? null,
    remark: props.employeeLoan?.remark ?? '',

    selected_employee: props.employeeLoan
        ? { id: props.employeeLoan.employee_id, name: props.employeeLoan.employee_name }
        : null,
    selected_type: null,
    selected_currency: null,
    selected_account: null,
});

watch([loanTypes, currencies, bankAccounts], () => {
    form.selected_type = loanTypes.value.find((x) => x.id === form.loan_type) ?? null;
    form.selected_currency = currencies.value.find((x) => x.id === form.currency_id) ?? null;
    form.selected_account = bankAccounts.value.find((x) => x.id === form.bank_account_id) ?? null;
}, { immediate: true });

// Shown live, because an instalment schedule that does not repay the loan is
// rejected on save and it is better to see that while typing.
const scheduleTotal = computed(() => Number(form.installment_amount || 0) * Number(form.installments_count || 0));
const scheduleShortfall = computed(() => Number(form.principal_amount || 0) - scheduleTotal.value);

const suggestInstalment = () => {
    const count = Number(form.installments_count || 0);
    if (!count || !form.principal_amount) return;

    form.installment_amount = Math.ceil(Number(form.principal_amount) / count);
};

const submit = () => {
    form.transform((data) => Object.fromEntries(
        Object.entries(data).filter(([k]) => !k.startsWith('selected_'))
    ));

    const options = { onError: () => toast.error(t('general.error')) };

    if (isEditing.value) form.put(route('employee-loans.update', props.employeeLoan.id), options);
    else form.post(route('employee-loans.store'), options);
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-5">
        <div class="rounded-xl border border-border bg-card p-5">
            <div class="mb-4 flex items-baseline justify-between">
                <h2 class="text-base font-semibold">{{ t('hr.loan_details') }}</h2>
                <span v-if="!isEditing && nextNumber" class="text-sm text-muted-foreground">#{{ nextNumber }}</span>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <NextSelect
                    v-model="form.selected_employee"
                    @update:modelValue="(v) => { form.employee_id = v?.id ?? null }"
                    resource-type="employees"
                    label-key="name" value-key="id" :reduce="(x) => x"
                    :floating-text="t('hr.employee')" :error="form.errors?.employee_id"
                />

                <NextSelect
                    :options="loanTypes" v-model="form.selected_type"
                    @update:modelValue="(v) => { form.loan_type = v?.id ?? null }"
                    label-key="name" value-key="id" :reduce="(x) => x"
                    :floating-text="t('hr.loan_type')" :error="form.errors?.loan_type"
                />

                <NextSelect
                    :options="currencies" v-model="form.selected_currency"
                    @update:modelValue="(v) => { form.currency_id = v?.id ?? null }"
                    label-key="code" value-key="id" :reduce="(x) => x"
                    :floating-text="t('general.currency')" :error="form.errors?.currency_id"
                />

                <NextInput :label="t('hr.principal')" type="number" step="any" v-model="form.principal_amount" :error="form.errors?.principal_amount" />
                <NextDatePicker :label="t('hr.issue_date')" v-model="form.issue_date" :error="form.errors?.issue_date" />
                <NextInput :label="t('general.rate')" type="number" step="any" v-model="form.rate" :error="form.errors?.rate" />
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-5">
            <h2 class="mb-4 text-base font-semibold">{{ t('hr.repayment_schedule') }}</h2>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <NextInput :label="t('hr.installments_count')" type="number" v-model="form.installments_count" :error="form.errors?.installments_count" />

                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <NextInput :label="t('hr.installment')" type="number" step="any" v-model="form.installment_amount" :error="form.errors?.installment_amount" />
                    </div>
                    <Button type="button" size="sm" variant="outline" class="mb-1" @click="suggestInstalment">
                        {{ t('hr.suggest') }}
                    </Button>
                </div>

                <NextDatePicker :label="t('hr.first_deduction_period')" v-model="form.first_deduction_period" :error="form.errors?.first_deduction_period" />
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-6">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" v-model="form.deduct_from_payroll" class="h-4 w-4 rounded border-border text-primary" />
                    {{ t('hr.deduct_from_payroll') }}
                </label>

                <p v-if="scheduleShortfall > 0.0001" class="text-sm text-destructive">
                    {{ t('hr.instalments_do_not_cover_loan') }}
                </p>
                <p v-else-if="form.principal_amount" class="text-sm text-muted-foreground">
                    {{ t('hr.schedule_covers_loan') }}
                </p>
            </div>

            <div class="mt-4">
                <NextTextarea :label="t('admin.shared.remark')" v-model="form.remark" :error="form.errors?.remark" />
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <Button type="button" variant="outline" @click="$inertia.get(route('employee-loans.index'))">
                {{ t('general.cancel') }}
            </Button>
            <Button type="submit" :disabled="form.processing">
                {{ isEditing ? t('general.update') : t('general.create') }}
            </Button>
        </div>
    </form>
</template>
