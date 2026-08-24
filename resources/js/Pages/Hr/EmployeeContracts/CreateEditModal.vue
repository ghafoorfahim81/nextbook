<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ModalDialog from '@/Components/next/Dialog.vue';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import NextDate from '@/Components/next/NextDatePicker.vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t } = useI18n();

const props = defineProps({
    isDialogOpen: Boolean,
    editingItem: Object,
    filterOptions: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:isDialogOpen', 'saved']);

const isEditing = computed(() => !!props.editingItem?.id);
const localDialogOpen = ref(props.isDialogOpen);

watch(() => props.isDialogOpen, (val) => localDialogOpen.value = val);
watch(() => localDialogOpen.value, (val) => emit('update:isDialogOpen', val));

const blank = () => ({
    employee_id: null, selected_employee: null,
    contract_number: '',
    contract_type: 'fixed_term', selected_contract_type: null,
    start_date: '', end_date: '',
    is_current: true,
    basic_salary: '',
    probation_months: '', notice_period_days: '',
    working_hours_per_day: 8, working_days_per_week: 6,
    annual_leave_entitlement: 20,
    status: 'active', selected_status: null,
    reminder_days_before: 30,
    remark: '',
});

const form = useForm(blank());

const opt = (key) => props.filterOptions?.[key] || [];

watch(() => props.editingItem, (item) => {
    if (item) {
        Object.assign(form, {
            ...blank(),
            employee_id: item.employee_id,
            selected_employee: item.employee_id ? { id: item.employee_id, name: item.employee_name } : null,
            contract_number: item.contract_number ?? '',
            contract_type: item.contract_type ?? 'fixed_term',
            selected_contract_type: opt('contractTypes').find((x) => x.id === item.contract_type) ?? null,
            start_date: item.start_date ?? '',
            end_date: item.end_date ?? '',
            is_current: !!item.is_current,
            basic_salary: item.basic_salary ?? '',
            probation_months: item.probation_months ?? '',
            notice_period_days: item.notice_period_days ?? '',
            working_hours_per_day: item.working_hours_per_day ?? 8,
            working_days_per_week: item.working_days_per_week ?? 6,
            annual_leave_entitlement: item.annual_leave_entitlement ?? 20,
            status: item.status ?? 'active',
            selected_status: opt('statuses').find((x) => x.id === item.status) ?? null,
            reminder_days_before: item.reminder_days_before ?? 30,
            remark: item.remark ?? '',
        });
    } else {
        form.defaults(blank());
        form.reset();
    }
}, { immediate: true });

const closeModal = () => localDialogOpen.value = false;

// A permanent contract genuinely has no end date; every other type must have
// one or its renewal reminder can never fire.
const requiresEndDate = computed(() => form.contract_type !== 'permanent');

const handleSubmit = async () => {
    const isEdit = isEditing.value;

    form.transform((data) => Object.fromEntries(
        Object.entries(data).filter(([key]) => !key.startsWith('selected_'))
    ));

    const submitOptions = {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
            form.reset();
            closeModal();
            toast.success(t('general.success'), {
                description: t(isEdit ? 'general.update_success' : 'general.create_success', { name: t('hr.contract') }),
                class: 'bg-green-600',
            });
        },
    };

    if (isEdit) {
        await form.patch(route('employee-contracts.update', props.editingItem.id), submitOptions);
    } else {
        await form.post(route('employee-contracts.store'), submitOptions);
    }
};
</script>

<template>
    <ModalDialog
        :open="localDialogOpen"
        :title="isEditing ? t('general.edit', { name: t('hr.contract') }) : t('general.create', { name: t('hr.contract') })"
        :confirmText="isEditing ? t('general.update') : t('general.create')"
        :cancel-text="t('general.close')"
        :closeable="true"
        width="w-[95vw] max-w-[95vw] sm:w-[900px] sm:max-w-[900px]"
        @update:open="localDialogOpen = $event; emit('update:isDialogOpen', $event)"
        @confirm="handleSubmit"
        @cancel="closeModal"
        :submitting="form.processing"
    >
        <form @submit.prevent="handleSubmit" id="modalForm">
            <div class="grid grid-cols-1 gap-4 py-4 md:grid-cols-3">
                <NextSelect
                    :options="opt('employees')" v-model="form.selected_employee"
                    @update:modelValue="(v) => { form.employee_id = v?.id ?? null }"
                    label-key="name" value-key="id" :reduce="(x) => x"
                    :searchable="true" resource-type="employees" :search-fields="['full_name', 'code']"
                    :floating-text="t('hr.employee')" :error="form.errors?.employee_id"
                    append-in-dialog
                />
                <NextInput :label="t('hr.contract_number')" v-model="form.contract_number" :error="form.errors?.contract_number" />
                <NextSelect
                    :options="opt('contractTypes')" v-model="form.selected_contract_type"
                    @update:modelValue="(v) => { form.contract_type = v?.id ?? null }"
                    label-key="name" value-key="id" :reduce="(x) => x"
                    :floating-text="t('hr.contract_type')" :error="form.errors?.contract_type"
                    append-in-dialog
                />

                <NextDate v-model="form.start_date" :label="t('hr.start_date')" :error="form.errors?.start_date" show-label />
                <NextDate
                    v-if="requiresEndDate"
                    v-model="form.end_date" :label="t('hr.end_date')" :error="form.errors?.end_date" show-label
                />
                <NextSelect
                    :options="opt('statuses')" v-model="form.selected_status"
                    @update:modelValue="(v) => { form.status = v?.id ?? null }"
                    label-key="name" value-key="id" :reduce="(x) => x"
                    :floating-text="t('general.status')" :error="form.errors?.status"
                    append-in-dialog
                />

                <NextInput :label="t('hr.basic_salary')" type="number" step="any" v-model="form.basic_salary" :error="form.errors?.basic_salary" />
                <NextInput :label="t('hr.probation_months')" type="number" v-model="form.probation_months" :error="form.errors?.probation_months" />
                <NextInput :label="t('hr.notice_period_days')" type="number" v-model="form.notice_period_days" :error="form.errors?.notice_period_days" />

                <NextInput :label="t('hr.working_hours_per_day')" type="number" step="any" v-model="form.working_hours_per_day" :error="form.errors?.working_hours_per_day" />
                <NextInput :label="t('hr.working_days_per_week')" type="number" v-model="form.working_days_per_week" :error="form.errors?.working_days_per_week" />
                <NextInput :label="t('hr.annual_leave_entitlement')" type="number" step="any" v-model="form.annual_leave_entitlement" :error="form.errors?.annual_leave_entitlement" />

                <NextInput :label="t('hr.reminder_days_before')" type="number" v-model="form.reminder_days_before" :error="form.errors?.reminder_days_before" />
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" v-model="form.is_current" class="h-4 w-4 rounded border-border text-primary" />
                    {{ t('hr.is_current') }}
                </label>

                <div class="md:col-span-3">
                    <NextTextarea :label="t('admin.shared.remark')" v-model="form.remark" :error="form.errors?.remark" />
                </div>
            </div>
        </form>
    </ModalDialog>
</template>
