<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import FormPageToolbar from '@/Components/FormPageToolbar.vue';
import SubmitButtons from '@/Components/SubmitButtons.vue';
import SalaryPaymentFormFields from './Partials/SalaryPaymentFormFields.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t } = useI18n();

defineProps({
    filterOptions: { type: Object, default: () => ({}) },
    nextNumber: { type: [String, Number], default: null },
});

const form = useForm({
    employee_id: null, selected_employee: null,
    date: '',
    currency_id: null, selected_currency: null,
    rate: 1,
    amount: null,
    bank_account_id: null, selected_account: null,
    cheque_no: '',
    narration: '',
    allocations: [],
});

/**
 * What this employee is still owed.
 *
 * Read from the ledger rather than from a cached paid_amount, so the form
 * offers what is genuinely open even if the two ever disagree.
 */
const openItems = ref([]);
const loadingOpenItems = ref(false);

const loadOpenItems = async () => {
    if (!form.employee_id) {
        openItems.value = [];
        return;
    }

    loadingOpenItems.value = true;

    try {
        const { data } = await axios.get(route('salary-payments.open-payslips'), {
            params: { employee_id: form.employee_id, currency_id: form.currency_id },
        });
        openItems.value = data.open_items ?? [];
    } catch (error) {
        openItems.value = [];
        toast.error(t('general.error'));
    } finally {
        loadingOpenItems.value = false;
    }
};

watch(() => [form.employee_id, form.currency_id], loadOpenItems);

const totalOpen = computed(() => openItems.value.reduce(
    (sum, item) => sum + Number(item.remaining_amount ?? 0), 0
));

// Manual allocation is not an optional extra: "this is for Asad" is a thing
// employers say, and silently applying it to an older month produces a payslip
// history nobody recognises.
const manualAllocation = ref(false);
const allocationAmounts = ref({});

const payAll = () => {
    form.amount = Number(totalOpen.value.toFixed(4));
};

const creating = ref(false);
const createLoading = computed(() => form.processing && creating.value);

const submit = () => {
    creating.value = true;

    form.transform((data) => {
        const payload = Object.fromEntries(
            Object.entries(data).filter(([k]) => !k.startsWith('selected_'))
        );

        payload.allocations = manualAllocation.value
            ? Object.entries(allocationAmounts.value)
                .filter(([, amount]) => Number(amount) > 0)
                .map(([target_line_id, amount]) => ({ target_line_id, amount: Number(amount) }))
            // Empty means FIFO across whatever is open — the right default for
            // "pay this person their salary".
            : [];

        return payload;
    });

    form.post(route('salary-payments.store'), {
        preserveScroll: true,
        onError: () => toast.error(t('general.error')),
        onFinish: () => { creating.value = false; },
    });
};
</script>

<template>
    <AppLayout :title="t('general.create', { name: t('hr.salary_payment') })">
        <FormPageToolbar back-route="salary-payments.index" module="salary_payments" />

        <form @submit.prevent="submit">
            <SalaryPaymentFormFields
                :form="form"
                :filter-options="filterOptions"
                :number="nextNumber"
                :open-items="openItems"
                :loading-open-items="loadingOpenItems"
                v-model:manual-allocation="manualAllocation"
                :allocation-amounts="allocationAmounts"
                @pay-all="payAll"
            />

            <SubmitButtons
                :create-label="t('general.create', { name: t('hr.salary_payment') })"
                :create-and-new-label="t('general.create_and_new')"
                :cancel-label="t('general.cancel')"
                :creating-label="t('general.creating', { name: t('hr.salary_payment') })"
                :create-loading="createLoading"
                :show-create-and-new="false"
                @cancel="router.visit(route('salary-payments.index'))"
            />
        </form>
    </AppLayout>
</template>
