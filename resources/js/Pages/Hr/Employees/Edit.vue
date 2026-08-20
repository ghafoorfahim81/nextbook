<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import FormPageToolbar from '@/Components/FormPageToolbar.vue';
import SubmitButtons from '@/Components/SubmitButtons.vue';
import EmployeeFormFields from './Partials/EmployeeFormFields.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    employee: { type: Object, required: true },
    options: { type: Object, default: () => ({}) },
});

const data = computed(() => props.employee?.data ?? props.employee);

const form = useForm({
    code: data.value.code ?? '',
    first_name: data.value.first_name ?? '',
    last_name: data.value.last_name ?? '',
    father_name: data.value.father_name ?? '',
    grand_father_name: data.value.grand_father_name ?? '',
    gender: data.value.gender ?? null, selected_gender: null,
    marital_status: data.value.marital_status ?? null, selected_marital_status: null,
    date_of_birth: data.value.date_of_birth ?? '',
    national_id: data.value.national_id ?? '',
    passport_number: data.value.passport_number ?? '',
    tin: data.value.tin ?? '',
    blood_group: data.value.blood_group ?? '',
    country_id: data.value.country_id ?? null, selected_country: null,
    province_id: data.value.province_id ?? null, selected_province: null,
    phone_number: data.value.phone_number ?? '',
    alternate_phone: data.value.alternate_phone ?? '',
    whatsapp_number: data.value.whatsapp_number ?? '',
    email: data.value.email ?? '',
    present_address: data.value.present_address ?? '',
    permanent_address: data.value.permanent_address ?? '',
    emergency_contact_name: data.value.emergency_contact_name ?? '',
    emergency_contact_phone: data.value.emergency_contact_phone ?? '',
    emergency_contact_relation: data.value.emergency_contact_relation ?? '',
    user_id: data.value.user_id ?? null, selected_user: null,
    department_id: data.value.department_id ?? null, selected_department: null,
    designation_id: data.value.designation_id ?? null, selected_designation: null,
    reports_to_id: data.value.reports_to_id ?? null, selected_manager: null,
    employment_type: data.value.employment_type ?? 'permanent', selected_employment_type: null,
    employment_status: data.value.employment_status ?? 'probation', selected_employment_status: null,
    joining_date: data.value.joining_date ?? '',
    probation_end_date: data.value.probation_end_date ?? '',
    confirmation_date: data.value.confirmation_date ?? '',
    separation_date: data.value.separation_date ?? '',
    separation_reason: data.value.separation_reason ?? '',
    currency_id: data.value.currency_id ?? null, selected_currency: null,
    basic_salary: data.value.basic_salary ?? '',
    payment_method: data.value.payment_method ?? null, selected_payment_method: null,
    bank_name: data.value.bank_name ?? '',
    bank_account_number: data.value.bank_account_number ?? '',
    bank_account_title: data.value.bank_account_title ?? '',
    iban: data.value.iban ?? '',
    is_tax_exempt: !!data.value.is_tax_exempt,
    self_service_enabled: !!data.value.self_service_enabled,
    is_active: !!data.value.is_active,
    remark: data.value.remark ?? '',
    documents: [],
});

const submitAction = ref(null);
const updateLoading = computed(() => form.processing && submitAction.value === 'update');

// Rehydrate the comboboxes from the option lists so they render the current
// selection rather than appearing empty on an edit.
const byId = (list, id) => (props.options?.[list] || []).find((x) => x.id === id) ?? null;

onMounted(() => {
    form.selected_gender = byId('genders', form.gender);
    form.selected_marital_status = byId('maritalStatuses', form.marital_status);
    form.selected_country = byId('countries', form.country_id);
    form.selected_province = byId('provinces', form.province_id);
    form.selected_user = byId('users', form.user_id);
    form.selected_department = byId('departments', form.department_id);
    form.selected_designation = byId('designations', form.designation_id);
    form.selected_manager = byId('managers', form.reports_to_id);
    form.selected_employment_type = byId('employmentTypes', form.employment_type);
    form.selected_employment_status = byId('employmentStatuses', form.employment_status);
    form.selected_currency = byId('currencies', form.currency_id);
    form.selected_payment_method = byId('paymentModes', form.payment_method);
});

const stripUiOnlyFields = (payload) => Object.fromEntries(
    Object.entries(payload).filter(([key]) => !key.startsWith('selected_'))
);

const submit = () => {
    submitAction.value = 'update';

    // Inertia cannot PATCH multipart, so a document upload posts with a method
    // override instead. Without this the files are silently dropped.
    const hasFiles = (form.documents || []).length > 0;

    form.transform((payload) => ({
        ...stripUiOnlyFields(payload),
        ...(hasFiles ? { _method: 'patch' } : {}),
    }));

    const options = {
        preserveScroll: true,
        onFinish: () => { submitAction.value = null; },
    };

    if (hasFiles) {
        form.post(route('employees.update', data.value.id), options);
    } else {
        form.patch(route('employees.update', data.value.id), options);
    }
};
</script>

<template>
    <AppLayout :title="t('general.edit', { name: t('hr.employee') })" :sidebar-collapsed="true">
        <FormPageToolbar back-route="employees.index" module="employees" />

        <form @submit.prevent="submit">
            <EmployeeFormFields :form="form" :options="options" />

            <SubmitButtons
                :create-label="t('general.update')"
                :create-and-new-label="t('general.create_and_new')"
                :cancel-label="t('general.cancel')"
                :creating-label="t('general.creating')"
                :create-loading="updateLoading"
                :show-create-and-new="false"
                @cancel="router.visit(route('employees.show', data.id))"
            />
        </form>
    </AppLayout>
</template>
