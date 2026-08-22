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
    filterOptions: { type: Object, default: () => ({}) },
    nextNumber: { type: [String, Number], default: null },
});

const form = useForm({
    employee_id: null, selected_employee: null,
    loan_type: 'advance', selected_type: null,
    currency_id: null, selected_currency: null,
    rate: 1,
    principal_amount: null,
    installment_amount: null,
    installments_count: 1,
    deduct_from_payroll: true,
    issue_date: '',
    first_deduction_period: null,
    interest_rate: 0,
    bank_account_id: null, selected_account: null,
    remark: '',
});

const creating = ref(false);
const createLoading = computed(() => form.processing && creating.value);

const byId = (list, id) => (props.filterOptions?.[list] || []).find((x) => x.id === id) ?? null;

onMounted(() => {
    form.selected_type = byId('loanTypes', form.loan_type);
});

// The selected_* fields hold whole objects for the comboboxes; the server has
// no rule for them, so they are stripped on the way out.
const stripUiOnlyFields = (data) => Object.fromEntries(
    Object.entries(data).filter(([key]) => !key.startsWith('selected_'))
);

// Saving lands on the loan itself — approval and payout are the next steps —
// so there is no "create and new" here.
const submit = () => {
    creating.value = true;

    form.transform(stripUiOnlyFields).post(route('employee-loans.store'), {
        preserveScroll: true,
        onError: () => toast.error(t('general.error')),
        onFinish: () => { creating.value = false; },
    });
};
</script>

<template>
    <AppLayout :title="t('general.create', { name: t('hr.employee_loan') })">
        <FormPageToolbar back-route="employee-loans.index" module="employee_loans" />

        <form @submit.prevent="submit">
            <EmployeeLoanFormFields :form="form" :filter-options="filterOptions" :number="nextNumber" />

            <SubmitButtons
                :create-label="t('general.create', { name: t('hr.employee_loan') })"
                :create-and-new-label="t('general.create_and_new')"
                :cancel-label="t('general.cancel')"
                :creating-label="t('general.creating', { name: t('hr.employee_loan') })"
                :create-loading="createLoading"
                :show-create-and-new="false"
                @cancel="router.visit(route('employee-loans.index'))"
            />
        </form>
    </AppLayout>
</template>
