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
    salaryStructure: { type: Object, required: true },
    filterOptions: { type: Object, default: () => ({}) },
    components: { type: [Array, Object], default: () => [] },
});

const structure = computed(() => props.salaryStructure?.data ?? props.salaryStructure);

const form = useForm({
    name: structure.value.name ?? '',
    code: structure.value.code ?? '',
    employee_id: structure.value.employee_id ?? null, selected_employee: null,
    designation_id: structure.value.designation_id ?? null, selected_designation: null,
    department_id: structure.value.department_id ?? null, selected_department: null,
    currency_id: structure.value.currency_id ?? null, selected_currency: null,
    effective_from: structure.value.effective_from ?? '',
    effective_to: structure.value.effective_to ?? null,
    basic_salary: structure.value.basic_salary ?? null,
    pay_frequency: structure.value.pay_frequency ?? 'monthly', selected_frequency: null,
    is_active: structure.value.is_active ?? true,
    remark: structure.value.remark ?? '',
    lines: (structure.value.lines ?? []).map((line) => ({
        salary_component_id: line.salary_component_id,
        amount: line.amount,
        percentage: line.percentage,
        sequence: line.sequence,
    })),
});

const updating = ref(false);

// Rehydrate the comboboxes so an edit shows the current selection rather than
// appearing blank. The employee box searches remotely, so it is seeded from
// the record itself rather than from an option list.
const byId = (list, id) => (props.filterOptions?.[list] || []).find((x) => x.id === id) ?? null;

onMounted(() => {
    if (structure.value.employee_id) {
        form.selected_employee = { id: structure.value.employee_id, name: structure.value.employee_name };
    }

    form.selected_currency = byId('currencies', form.currency_id);
    form.selected_frequency = byId('payFrequencies', form.pay_frequency);
    form.selected_department = byId('departments', form.department_id);
    form.selected_designation = byId('designations', form.designation_id);
});

const stripUiOnlyFields = (data) => Object.fromEntries(
    Object.entries(data).filter(([key]) => !key.startsWith('selected_'))
);

const submit = () => {
    updating.value = true;

    form.transform(stripUiOnlyFields).put(route('salary-structures.update', structure.value.id), {
        preserveScroll: true,
        onError: () => toast.error(t('general.error')),
        onFinish: () => { updating.value = false; },
    });
};
</script>

<template>
    <AppLayout :title="t('general.edit', { name: t('hr.salary_structure') })">
        <FormPageToolbar back-route="salary-structures.show" :back-route-params="structure.id" module="salary_structures" />

        <form @submit.prevent="submit">
            <SalaryStructureFormFields :form="form" :filter-options="filterOptions" :components="components" />

            <SubmitButtons
                :create-label="t('general.update')"
                :create-and-new-label="t('general.create_and_new')"
                :cancel-label="t('general.cancel')"
                :creating-label="t('general.creating', { name: t('hr.salary_structure') })"
                :create-loading="updating"
                :show-create-and-new="false"
                @cancel="router.visit(route('salary-structures.show', structure.id))"
            />
        </form>
    </AppLayout>
</template>
