<script setup>
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import NextDate from '@/Components/next/NextDatePicker.vue';
import AttachmentUploader from '@/Components/AttachmentUploader.vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    form: { type: Object, required: true },
    options: { type: Object, default: () => ({}) },
});

const leaveTypes = computed(() => props.options?.leaveTypes || []);

const halfDayPeriods = computed(() => ([
    { id: 'first_half', name: t('enums.half_day_period.first_half') },
    { id: 'second_half', name: t('enums.half_day_period.second_half') },
]));

const selectedType = computed(() =>
    leaveTypes.value.find((x) => x.id === props.form.leave_type_id) ?? null
);

// Surfaced up front rather than discovered on submit: the server rejects a
// request of this type without a document.
const requiresAttachment = computed(() => !!selectedType.value?.requires_attachment);

const onHalfDayToggle = () => {
    if (props.form.is_half_day) {
        // A half day is half of ONE day, so collapse the range rather than
        // letting the user submit something the server will reject.
        props.form.to_date = props.form.from_date;
    } else {
        props.form.half_day_period = null;
        props.form.selected_half_day_period = null;
    }
};
</script>

<template>
    <fieldset class="relative mb-5 rounded-xl border border-violet-500 p-4 shadow-sm">
        <legend class="px-2 text-sm font-semibold text-violet-500">{{ t('hr.leave_request') }}</legend>

        <div class="mt-2 grid grid-cols-1 gap-4 md:grid-cols-3">
            <NextSelect
                :options="[]"
                v-model="form.selected_employee"
                @update:modelValue="(v) => { form.employee_id = v?.id ?? null }"
                label-key="name" value-key="id" :reduce="(x) => x"
                :searchable="true" resource-type="employees" :search-fields="['full_name', 'code']"
                :floating-text="t('hr.employee')"
                :error="form.errors?.employee_id"
            />

            <NextSelect
                :options="leaveTypes"
                v-model="form.selected_leave_type"
                @update:modelValue="(v) => { form.leave_type_id = v?.id ?? null }"
                label-key="name" value-key="id" :reduce="(x) => x"
                :floating-text="t('hr.leave_type')"
                :error="form.errors?.leave_type_id"
            />

            <label class="flex items-center gap-2 text-sm">
                <input
                    type="checkbox" v-model="form.is_half_day"
                    class="h-4 w-4 rounded border-border text-primary"
                    @change="onHalfDayToggle"
                />
                {{ t('hr.is_half_day') }}
            </label>

            <NextDate
                v-model="form.from_date"
                :label="t('hr.from_date')" show-label
                :error="form.errors?.from_date"
                @update:modelValue="() => { if (form.is_half_day) form.to_date = form.from_date }"
            />
            <NextDate
                v-model="form.to_date"
                :label="t('hr.to_date')" show-label
                :error="form.errors?.to_date"
                :disabled="form.is_half_day"
            />
            <NextSelect
                v-if="form.is_half_day"
                :options="halfDayPeriods"
                v-model="form.selected_half_day_period"
                @update:modelValue="(v) => { form.half_day_period = v?.id ?? null }"
                label-key="name" value-key="id" :reduce="(x) => x"
                :floating-text="t('hr.half_day_period')"
                :error="form.errors?.half_day_period"
            />

            <NextInput
                :label="t('hr.contact_during_leave')"
                v-model="form.contact_during_leave"
                :error="form.errors?.contact_during_leave"
            />
            <NextSelect
                :options="[]"
                v-model="form.selected_handover"
                @update:modelValue="(v) => { form.handover_to_id = v?.id ?? null }"
                label-key="name" value-key="id" :reduce="(x) => x"
                :searchable="true" resource-type="employees" :search-fields="['full_name', 'code']"
                :floating-text="t('hr.handover_to')"
                :error="form.errors?.handover_to_id"
            />

            <div class="md:col-span-3">
                <NextTextarea :label="t('hr.reason')" v-model="form.reason" :error="form.errors?.reason" />
            </div>

            <div class="md:col-span-3">
                <AttachmentUploader
                    v-model="form.documents"
                    :label="requiresAttachment ? `${t('general.attachments')} *` : t('general.attachments')"
                    :error="form.errors?.documents || form.errors?.['documents.0']"
                />
                <p v-if="requiresAttachment" class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                    {{ t('hr.requires_attachment_hint') }}
                </p>
            </div>
        </div>
    </fieldset>
</template>
