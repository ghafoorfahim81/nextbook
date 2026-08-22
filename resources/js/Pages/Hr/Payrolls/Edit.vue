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
    payroll: { type: Object, required: true },
    filterOptions: { type: Object, default: () => ({}) },
});

const record = computed(() => props.payroll?.data ?? props.payroll);

const form = useForm({
    name: record.value.name ?? '',
    period_start: record.value.period_start ?? '',
    period_end: record.value.period_end ?? '',
    pay_date: record.value.pay_date ?? '',
    period_label: record.value.period_label ?? '',
    pay_frequency: record.value.pay_frequency ?? 'monthly', selected_frequency: null,
    currency_id: record.value.currency_id ?? null, selected_currency: null,
    rate: record.value.rate ?? 1,
    department_id: record.value.department_id ?? null, selected_department: null,
    employment_type: record.value.employment_type ?? null, selected_employment_type: null,
    remark: record.value.remark ?? '',
});

const updating = ref(false);

// Rehydrate the comboboxes from the option lists so they render the current
// selection rather than appearing empty on an edit.
const byId = (list, id) => (props.filterOptions?.[list] || []).find((x) => x.id === id) ?? null;

onMounted(() => {
    form.selected_frequency = byId('payFrequencies', form.pay_frequency);
    form.selected_currency = byId('currencies', form.currency_id);
    form.selected_department = byId('departments', form.department_id);
    form.selected_employment_type = byId('employmentTypes', form.employment_type);
});

const stripUiOnlyFields = (data) => Object.fromEntries(
    Object.entries(data).filter(([key]) => !key.startsWith('selected_'))
);

const submit = () => {
    updating.value = true;

    form.transform(stripUiOnlyFields).put(route('payrolls.update', record.value.id), {
        preserveScroll: true,
        onError: () => toast.error(t('general.error')),
        onFinish: () => { updating.value = false; },
    });
};
</script>

<template>
    <AppLayout :title="t('general.edit', { name: t('hr.payroll') })">
        <FormPageToolbar back-route="payrolls.show" :back-route-params="record.id" module="payrolls" />

        <form @submit.prevent="submit">
            <PayrollFormFields :form="form" :filter-options="filterOptions" :number="record.number" />

            <SubmitButtons
                :create-label="t('general.update')"
                :create-and-new-label="t('general.create_and_new')"
                :cancel-label="t('general.cancel')"
                :creating-label="t('general.creating', { name: t('hr.payroll') })"
                :create-loading="updating"
                :show-create-and-new="false"
                @cancel="router.visit(route('payrolls.show', record.id))"
            />
        </form>
    </AppLayout>
</template>
