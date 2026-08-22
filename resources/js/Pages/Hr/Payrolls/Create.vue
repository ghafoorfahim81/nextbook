<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import FormPageToolbar from '@/Components/FormPageToolbar.vue';
import SubmitButtons from '@/Components/SubmitButtons.vue';
import PayrollFormFields from './Partials/PayrollFormFields.vue';
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
    name: '',
    period_start: '',
    period_end: '',
    pay_date: '',
    period_label: '',
    pay_frequency: 'monthly', selected_frequency: null,
    currency_id: null, selected_currency: null,
    rate: 1,
    department_id: null, selected_department: null,
    employment_type: null, selected_employment_type: null,
    remark: '',
});

const creating = ref(false);
const createLoading = computed(() => form.processing && creating.value);

const byId = (list, id) => (props.filterOptions?.[list] || []).find((x) => x.id === id) ?? null;

onMounted(() => {
    form.selected_frequency = byId('payFrequencies', form.pay_frequency);
});

// The selected_* fields hold whole objects for the comboboxes; the server has
// no rule for them, so they are stripped on the way out.
const stripUiOnlyFields = (data) => Object.fromEntries(
    Object.entries(data).filter(([key]) => !key.startsWith('selected_'))
);

// A run is created then calculated, so there is no "create and new" here —
// the next step is always the run that was just made.
const submit = () => {
    creating.value = true;

    form.transform(stripUiOnlyFields).post(route('payrolls.store'), {
        preserveScroll: true,
        onError: () => toast.error(t('general.error')),
        onFinish: () => { creating.value = false; },
    });
};
</script>

<template>
    <AppLayout :title="t('general.create', { name: t('hr.payroll') })">
        <FormPageToolbar back-route="payrolls.index" module="payrolls" />

        <form @submit.prevent="submit">
            <PayrollFormFields :form="form" :filter-options="filterOptions" :number="nextNumber" />

            <SubmitButtons
                :create-label="t('general.create', { name: t('hr.payroll') })"
                :create-and-new-label="t('general.create_and_new')"
                :cancel-label="t('general.cancel')"
                :creating-label="t('general.creating', { name: t('hr.payroll') })"
                :create-loading="createLoading"
                :show-create-and-new="false"
                @cancel="router.visit(route('payrolls.index'))"
            />
        </form>
    </AppLayout>
</template>
