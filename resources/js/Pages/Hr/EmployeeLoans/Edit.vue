<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import FormPageToolbar from '@/Components/FormPageToolbar.vue';
import SubmitButtons from '@/Components/SubmitButtons.vue';
import EmployeeLoanFormFields from './Partials/EmployeeLoanFormFields.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t } = useI18n();

const props = defineProps({
    employeeLoan: { type: Object, required: true },
    filterOptions: { type: Object, default: () => ({}) },
});

const loan = computed(() => props.employeeLoan?.data ?? props.employeeLoan);

const form = useForm({
    employee_id: loan.value.employee_id ?? null, selected_employee: null,
    loan_type: loan.value.loan_type ?? 'advance', selected_type: null,
    currency_id: loan.value.currency_id ?? null, selected_currency: null,
    rate: loan.value.rate ?? 1,
    principal_amount: loan.value.principal_amount ?? null,
    installment_amount: loan.value.installment_amount ?? null,
    installments_count: loan.value.installments_count ?? 1,
    deduct_from_payroll: loan.value.deduct_from_payroll ?? true,
    issue_date: loan.value.issue_date ?? '',
    first_deduction_period: loan.value.first_deduction_period ?? null,
    interest_rate: loan.value.interest_rate ?? 0,
    bank_account_id: loan.value.bank_account_id ?? null, selected_account: null,
    remark: loan.value.remark ?? '',
});

const updating = ref(false);

// Rehydrate the comboboxes so an edit shows the current selection rather than
// appearing blank. The employee box searches remotely, so it is seeded from
// the record itself rather than from an option list.
const byId = (list, id) => (props.filterOptions?.[list] || []).find((x) => x.id === id) ?? null;

onMounted(() => {
    if (loan.value.employee_id) {
        form.selected_employee = { id: loan.value.employee_id, name: loan.value.employee_name };
    }

    form.selected_type = byId('loanTypes', form.loan_type);
    form.selected_currency = byId('currencies', form.currency_id);
    form.selected_account = byId('bankAccounts', form.bank_account_id);
});

const stripUiOnlyFields = (data) => Object.fromEntries(
    Object.entries(data).filter(([key]) => !key.startsWith('selected_'))
);

const submit = () => {
    updating.value = true;

    form.transform(stripUiOnlyFields).put(route('employee-loans.update', loan.value.id), {
        preserveScroll: true,
        onError: () => toast.error(t('general.error')),
        onFinish: () => { updating.value = false; },
    });
};
</script>

<template>
    <AppLayout :title="t('general.edit', { name: t('hr.employee_loan') })">
        <FormPageToolbar back-route="employee-loans.show" :back-route-params="loan.id" module="employee_loans" />

        <form @submit.prevent="submit">
            <EmployeeLoanFormFields :form="form" :filter-options="filterOptions" :number="loan.number" />

            <SubmitButtons
                :create-label="t('general.update')"
                :create-and-new-label="t('general.create_and_new')"
                :cancel-label="t('general.cancel')"
                :creating-label="t('general.creating', { name: t('hr.employee_loan') })"
                :create-loading="updating"
                :show-create-and-new="false"
                @cancel="router.visit(route('employee-loans.show', loan.id))"
            />
        </form>
    </AppLayout>
</template>
