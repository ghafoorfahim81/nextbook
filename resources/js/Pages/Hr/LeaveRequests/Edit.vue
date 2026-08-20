<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import FormPageToolbar from '@/Components/FormPageToolbar.vue';
import SubmitButtons from '@/Components/SubmitButtons.vue';
import LeaveRequestFields from './Partials/LeaveRequestFields.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    leaveRequest: { type: Object, required: true },
    options: { type: Object, default: () => ({}) },
});

const data = computed(() => props.leaveRequest?.data ?? props.leaveRequest);

const form = useForm({
    employee_id: data.value.employee_id, selected_employee: null,
    leave_type_id: data.value.leave_type_id, selected_leave_type: null,
    from_date: data.value.from_date ?? '',
    to_date: data.value.to_date ?? '',
    is_half_day: !!data.value.is_half_day,
    half_day_period: data.value.half_day_period ?? null, selected_half_day_period: null,
    reason: data.value.reason ?? '',
    contact_during_leave: data.value.contact_during_leave ?? '',
    handover_to_id: data.value.handover_to_id ?? null, selected_handover: null,
    documents: [],
});

const updating = ref(false);

onMounted(() => {
    // Rehydrate the comboboxes so an edit shows the current selection rather
    // than appearing blank.
    if (data.value.employee_id) {
        form.selected_employee = { id: data.value.employee_id, name: data.value.employee_name };
    }

    form.selected_leave_type = (props.options?.leaveTypes || [])
        .find((x) => x.id === data.value.leave_type_id) ?? null;

    if (data.value.handover_to_id) {
        form.selected_handover = { id: data.value.handover_to_id, name: data.value.handover_to_name };
    }

    if (data.value.half_day_period) {
        form.selected_half_day_period = {
            id: data.value.half_day_period,
            name: data.value.half_day_period_label,
        };
    }
});

const stripUiOnly = (payload) => Object.fromEntries(
    Object.entries(payload).filter(([k]) => !k.startsWith('selected_'))
);

const submit = () => {
    updating.value = true;

    // Inertia cannot PATCH multipart, so an upload posts with a method override.
    const hasFiles = (form.documents || []).length > 0;

    form.transform((payload) => ({
        ...stripUiOnly(payload),
        ...(hasFiles ? { _method: 'patch' } : {}),
    }));

    const options = {
        preserveScroll: true,
        onFinish: () => { updating.value = false; },
    };

    if (hasFiles) form.post(route('leave-requests.update', data.value.id), options);
    else form.patch(route('leave-requests.update', data.value.id), options);
};
</script>

<template>
    <AppLayout :title="t('general.edit', { name: t('hr.leave_request') })" :sidebar-collapsed="true">
        <FormPageToolbar back-route="leave-requests.index" module="leave_requests" />

        <form @submit.prevent="submit">
            <LeaveRequestFields :form="form" :options="options" />

            <SubmitButtons
                :create-label="t('general.update')"
                :create-and-new-label="t('general.create_and_new')"
                :cancel-label="t('general.cancel')"
                :creating-label="t('general.creating')"
                :create-loading="updating"
                :show-create-and-new="false"
                @cancel="router.visit(route('leave-requests.show', data.id))"
            />
        </form>
    </AppLayout>
</template>
