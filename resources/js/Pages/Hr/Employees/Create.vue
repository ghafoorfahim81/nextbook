<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import FormPageToolbar from '@/Components/FormPageToolbar.vue';
import SubmitButtons from '@/Components/SubmitButtons.vue';
import EmployeeFormFields from './Partials/EmployeeFormFields.vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { todayValueForCalendar } from '@/utils/dateDefaults';

const { t } = useI18n();
const page = usePage();
const calendarType = computed(() => page.props.auth?.user?.calendar_type || 'gregorian');

const props = defineProps({
    nextCode: { type: String, default: '' },
    options: { type: Object, default: () => ({}) },
});

const form = useForm({
    code: props.nextCode,
    first_name: '', last_name: '', father_name: '', grand_father_name: '',
    gender: null, selected_gender: null,
    marital_status: null, selected_marital_status: null,
    date_of_birth: '',
    national_id: '', passport_number: '', tin: '', blood_group: '',
    country_id: null, selected_country: null,
    province_id: null, selected_province: null,
    phone_number: '', alternate_phone: '', whatsapp_number: '', email: '',
    present_address: '', permanent_address: '',
    emergency_contact_name: '', emergency_contact_phone: '', emergency_contact_relation: '',
    user_id: null, selected_user: null,
    department_id: null, selected_department: null,
    designation_id: null, selected_designation: null,
    reports_to_id: null, selected_manager: null,
    employment_type: 'permanent', selected_employment_type: null,
    employment_status: 'probation', selected_employment_status: null,
    joining_date: '',
    probation_end_date: '', confirmation_date: '', separation_date: '', separation_reason: '',
    currency_id: null, selected_currency: null,
    basic_salary: '',
    payment_method: null, selected_payment_method: null,
    bank_name: '', bank_account_number: '', bank_account_title: '', iban: '',
    is_tax_exempt: false,
    self_service_enabled: false,
    is_active: true,
    remark: '',
    documents: [],
});

const submitAction = ref(null);
const createLoading = computed(() => form.processing && submitAction.value === 'create');
const createAndNewLoading = computed(() => form.processing && submitAction.value === 'create_and_new');

onMounted(() => {
    form.joining_date = todayValueForCalendar(calendarType.value);
    form.selected_employment_type = (props.options?.employmentTypes || []).find((x) => x.id === 'permanent') ?? null;
    form.selected_employment_status = (props.options?.employmentStatuses || []).find((x) => x.id === 'probation') ?? null;
});

// The selected_* fields hold whole objects for the comboboxes. Posting them
// would send nested relations the server has no rule for, so they are stripped
// on the way out — same approach as the sale form.
const stripUiOnlyFields = (data) => Object.fromEntries(
    Object.entries(data).filter(([key]) => !key.startsWith('selected_'))
);

const submit = (andNew = false) => {
    submitAction.value = andNew ? 'create_and_new' : 'create';

    form.transform((data) => ({ ...stripUiOnlyFields(data), create_and_new: andNew }))
        .post(route('employees.store'), {
            preserveScroll: true,
            onSuccess: () => {
                if (andNew) {
                    form.reset();
                    form.joining_date = todayValueForCalendar(calendarType.value);
                }
            },
            onFinish: () => { submitAction.value = null; },
        });
};
</script>

<template>
    <AppLayout :title="t('general.create', { name: t('hr.employee') })" :sidebar-collapsed="true">
        <FormPageToolbar back-route="employees.index" module="employees" />

        <form @submit.prevent="submit(false)">
            <EmployeeFormFields :form="form" :options="options" />

            <SubmitButtons
                :create-label="t('general.create', { name: t('hr.employee') })"
                :create-and-new-label="t('general.create_and_new')"
                :cancel-label="t('general.cancel')"
                :creating-label="t('general.creating')"
                :create-loading="createLoading"
                :create-and-new-loading="createAndNewLoading"
                @create-and-new="submit(true)"
                @cancel="router.visit(route('employees.index'))"
            />
        </form>
    </AppLayout>
</template>
