<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import FormPageToolbar from '@/Components/FormPageToolbar.vue';
import SubmitButtons from '@/Components/SubmitButtons.vue';
import LeaveRequestFields from './Partials/LeaveRequestFields.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps({
    nextNumber: { type: [String, Number], default: '' },
    options: { type: Object, default: () => ({}) },
});

const form = useForm({
    employee_id: null, selected_employee: null,
    leave_type_id: null, selected_leave_type: null,
    from_date: '', to_date: '',
    is_half_day: false,
    half_day_period: null, selected_half_day_period: null,
    reason: '',
    contact_during_leave: '',
    handover_to_id: null, selected_handover: null,
    documents: [],
    submit: false,
});

const submitAction = ref(null);
const draftLoading = computed(() => form.processing && submitAction.value === 'draft');
const submitLoading = computed(() => form.processing && submitAction.value === 'submit');

const stripUiOnly = (data) => Object.fromEntries(
    Object.entries(data).filter(([k]) => !k.startsWith('selected_'))
);

// Two ways out of this form: file it now, or keep it as a draft. Most people
// want the first, so it is the primary button.
const save = (andSubmit) => {
    submitAction.value = andSubmit ? 'submit' : 'draft';

    form.transform((data) => ({ ...stripUiOnly(data), submit: andSubmit }))
        .post(route('leave-requests.store'), {
            preserveScroll: true,
            onFinish: () => { submitAction.value = null; },
        });
};
</script>

<template>
    <AppLayout :title="t('general.create', { name: t('hr.leave_request') })">
        <FormPageToolbar back-route="leave-requests.index" module="leave_requests" />

        <form @submit.prevent="save(true)">
            <LeaveRequestFields :form="form" :options="options" />

            <SubmitButtons
                :create-label="t('hr.submit_for_approval')"
                :create-and-new-label="t('hr.save_as_draft')"
                :cancel-label="t('general.cancel')"
                :creating-label="t('general.creating')"
                :create-loading="submitLoading"
                :create-and-new-loading="draftLoading"
                @create-and-new="save(false)"
                @cancel="router.visit(route('leave-requests.index'))"
            />
        </form>
    </AppLayout>
</template>
