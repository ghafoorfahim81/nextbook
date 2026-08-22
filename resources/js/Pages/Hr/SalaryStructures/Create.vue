<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import FormPageToolbar from '@/Components/FormPageToolbar.vue';
import SubmitButtons from '@/Components/SubmitButtons.vue';
import SalaryStructureFormFields from './Partials/SalaryStructureFormFields.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t } = useI18n();

const props = defineProps({
    filterOptions: { type: Object, default: () => ({}) },
    components: { type: [Array, Object], default: () => [] },
});

const form = useForm({
    name: '',
    code: '',
    employee_id: null, selected_employee: null,
    designation_id: null, selected_designation: null,
    department_id: null, selected_department: null,
    currency_id: null, selected_currency: null,
    effective_from: '',
    effective_to: null,
    basic_salary: null,
    pay_frequency: 'monthly', selected_frequency: null,
    is_active: true,
    remark: '',
    lines: [],
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

const submit = () => {
    creating.value = true;

    form.transform(stripUiOnlyFields).post(route('salary-structures.store'), {
        preserveScroll: true,
        onError: () => toast.error(t('general.error')),
        onFinish: () => { creating.value = false; },
    });
};
</script>

<template>
    <AppLayout :title="t('general.create', { name: t('hr.salary_structure') })">
        <FormPageToolbar back-route="salary-structures.index" module="salary_structures" />

        <form @submit.prevent="submit">
            <SalaryStructureFormFields :form="form" :filter-options="filterOptions" :components="components" />

            <SubmitButtons
                :create-label="t('general.create', { name: t('hr.salary_structure') })"
                :create-and-new-label="t('general.create_and_new')"
                :cancel-label="t('general.cancel')"
                :creating-label="t('general.creating', { name: t('hr.salary_structure') })"
                :create-loading="createLoading"
                :show-create-and-new="false"
                @cancel="router.visit(route('salary-structures.index'))"
            />
        </form>
    </AppLayout>
</template>
