<script setup>
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import NextDate from '@/Components/next/NextDatePicker.vue';
import AttachmentUploader from '@/Components/AttachmentUploader.vue';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';

const { t } = useI18n();

const props = defineProps({
    form: { type: Object, required: true },
    options: { type: Object, default: () => ({}) },
    showDocuments: { type: Boolean, default: true },
});

const opt = (key) => props.options?.[key] || [];

// A separated employee must record when they left; an employed one must not.
// Mirrors the server-side rule in EmployeeStoreRequest so the field appears
// exactly when it is required rather than the user discovering it on submit.
const separatedStatuses = ['resigned', 'terminated', 'retired'];
const isSeparated = computed(() => separatedStatuses.includes(props.form.employment_status));

function pick(field, value, idKey = 'id') {
    props.form[field] = value?.[idKey] ?? null;
}
</script>

<template>
    <!-- Identity -->
    <fieldset class="relative mb-5 rounded-xl border border-violet-500 p-4 shadow-sm">
        <legend class="px-2 text-sm font-semibold text-violet-500">{{ t('hr.sections.identity') }}</legend>
        <div class="mt-2 grid grid-cols-1 gap-4 md:grid-cols-3">
            <NextInput :label="t('general.code')" v-model="form.code" :error="form.errors?.code" is-required />
            <NextInput :label="t('hr.first_name')" v-model="form.first_name" :error="form.errors?.first_name" is-required autofocus />
            <NextInput :label="t('hr.last_name')" v-model="form.last_name" :error="form.errors?.last_name" />
            <NextInput :label="t('hr.father_name')" v-model="form.father_name" :error="form.errors?.father_name" />
            <NextInput :label="t('hr.grand_father_name')" v-model="form.grand_father_name" :error="form.errors?.grand_father_name" />

            <NextSelect
                :options="opt('genders')" v-model="form.selected_gender"
                @update:modelValue="(v) => pick('gender', v)"
                label-key="name" value-key="id" :reduce="(x) => x"
                :floating-text="t('hr.gender')" :error="form.errors?.gender"
            />
            <NextSelect
                :options="opt('maritalStatuses')" v-model="form.selected_marital_status"
                @update:modelValue="(v) => pick('marital_status', v)"
                label-key="name" value-key="id" :reduce="(x) => x"
                :floating-text="t('hr.marital_status')" :error="form.errors?.marital_status"
            />
            <NextDate v-model="form.date_of_birth" :label="t('hr.date_of_birth')" :error="form.errors?.date_of_birth" show-label />

            <NextInput :label="t('hr.national_id')" v-model="form.national_id" :error="form.errors?.national_id" />
            <NextInput :label="t('hr.passport_number')" v-model="form.passport_number" :error="form.errors?.passport_number" />
            <NextInput :label="t('hr.tin')" v-model="form.tin" :error="form.errors?.tin" />
            <NextInput :label="t('hr.blood_group')" v-model="form.blood_group" :error="form.errors?.blood_group" />
        </div>
    </fieldset>

    <!-- Contact -->
    <fieldset class="relative mb-5 rounded-xl border border-violet-500 p-4 shadow-sm">
        <legend class="px-2 text-sm font-semibold text-violet-500">{{ t('hr.sections.contact') }}</legend>
        <div class="mt-2 grid grid-cols-1 gap-4 md:grid-cols-3">
            <NextInput :label="t('hr.phone_number')" v-model="form.phone_number" :error="form.errors?.phone_number" />
            <NextInput :label="t('hr.alternate_phone')" v-model="form.alternate_phone" :error="form.errors?.alternate_phone" />
            <NextInput :label="t('hr.whatsapp_number')" v-model="form.whatsapp_number" :error="form.errors?.whatsapp_number" />
            <NextInput :label="t('admin.shared.email')" type="email" v-model="form.email" :error="form.errors?.email" />

            <NextSelect
                :options="opt('countries')" v-model="form.selected_country"
                @update:modelValue="(v) => pick('country_id', v)"
                label-key="name" value-key="id" :reduce="(x) => x"
                :floating-text="t('admin.shared.country')" :error="form.errors?.country_id"
            />
            <NextSelect
                :options="opt('provinces')" v-model="form.selected_province"
                @update:modelValue="(v) => pick('province_id', v)"
                label-key="name" value-key="id" :reduce="(x) => x"
                :floating-text="t('admin.shared.province')" :error="form.errors?.province_id"
            />

            <div class="md:col-span-3 grid grid-cols-1 gap-4 md:grid-cols-2">
                <NextTextarea :label="t('hr.present_address')" v-model="form.present_address" :error="form.errors?.present_address" />
                <NextTextarea :label="t('hr.permanent_address')" v-model="form.permanent_address" :error="form.errors?.permanent_address" />
            </div>
        </div>
    </fieldset>

    <!-- Emergency contact -->
    <fieldset class="relative mb-5 rounded-xl border border-violet-500 p-4 shadow-sm">
        <legend class="px-2 text-sm font-semibold text-violet-500">{{ t('hr.sections.emergency_contact') }}</legend>
        <div class="mt-2 grid grid-cols-1 gap-4 md:grid-cols-3">
            <NextInput :label="t('hr.emergency_contact_name')" v-model="form.emergency_contact_name" :error="form.errors?.emergency_contact_name" />
            <NextInput :label="t('hr.emergency_contact_phone')" v-model="form.emergency_contact_phone" :error="form.errors?.emergency_contact_phone" />
            <NextInput :label="t('hr.emergency_contact_relation')" v-model="form.emergency_contact_relation" :error="form.errors?.emergency_contact_relation" />
        </div>
    </fieldset>

    <!-- Job -->
    <fieldset class="relative mb-5 rounded-xl border border-violet-500 p-4 shadow-sm">
        <legend class="px-2 text-sm font-semibold text-violet-500">{{ t('hr.sections.job') }}</legend>
        <div class="mt-2 grid grid-cols-1 gap-4 md:grid-cols-3">
            <NextSelect
                :options="opt('departments')" v-model="form.selected_department"
                @update:modelValue="(v) => pick('department_id', v)"
                label-key="name" value-key="id" :reduce="(x) => x"
                :floating-text="t('hr.department')" :error="form.errors?.department_id"
            />
            <NextSelect
                :options="opt('designations')" v-model="form.selected_designation"
                @update:modelValue="(v) => pick('designation_id', v)"
                label-key="name" value-key="id" :reduce="(x) => x"
                :floating-text="t('hr.designation')" :error="form.errors?.designation_id"
            />
            <NextSelect
                :options="opt('managers')" v-model="form.selected_manager"
                @update:modelValue="(v) => pick('reports_to_id', v)"
                label-key="name" value-key="id" :reduce="(x) => x"
                :searchable="true" resource-type="employees" :search-fields="['full_name', 'code']"
                :floating-text="t('hr.manager')" :error="form.errors?.reports_to_id"
            />

            <NextSelect
                :options="opt('employmentTypes')" v-model="form.selected_employment_type"
                @update:modelValue="(v) => pick('employment_type', v)"
                label-key="name" value-key="id" :reduce="(x) => x"
                :floating-text="t('hr.employment_type')" :error="form.errors?.employment_type"
            />
            <NextSelect
                :options="opt('employmentStatuses')" v-model="form.selected_employment_status"
                @update:modelValue="(v) => pick('employment_status', v)"
                label-key="name" value-key="id" :reduce="(x) => x"
                :floating-text="t('hr.employment_status')" :error="form.errors?.employment_status"
            />
            <NextDate v-model="form.joining_date" :label="t('hr.joining_date')" :error="form.errors?.joining_date" show-label />

            <NextDate v-model="form.probation_end_date" :label="t('hr.probation_end_date')" :error="form.errors?.probation_end_date" show-label />
            <NextDate v-model="form.confirmation_date" :label="t('hr.confirmation_date')" :error="form.errors?.confirmation_date" show-label />

            <template v-if="isSeparated">
                <NextDate v-model="form.separation_date" :label="t('hr.separation_date')" :error="form.errors?.separation_date" show-label />
                <div class="md:col-span-2">
                    <NextTextarea :label="t('hr.separation_reason')" v-model="form.separation_reason" :error="form.errors?.separation_reason" />
                </div>
            </template>
        </div>
    </fieldset>

    <!-- Salary & payment -->
    <fieldset class="relative mb-5 rounded-xl border border-violet-500 p-4 shadow-sm">
        <legend class="px-2 text-sm font-semibold text-violet-500">{{ t('hr.sections.salary') }}</legend>
        <div class="mt-2 grid grid-cols-1 gap-4 md:grid-cols-3">
            <NextSelect
                :options="opt('currencies')" v-model="form.selected_currency"
                @update:modelValue="(v) => pick('currency_id', v)"
                label-key="code" value-key="id" :reduce="(x) => x"
                :floating-text="t('general.currency')" :error="form.errors?.currency_id"
            />
            <NextInput :label="t('hr.basic_salary')" type="number" step="any" v-model="form.basic_salary" :error="form.errors?.basic_salary" />
            <NextSelect
                :options="opt('paymentModes')" v-model="form.selected_payment_method"
                @update:modelValue="(v) => pick('payment_method', v)"
                label-key="name" value-key="id" :reduce="(x) => x"
                :floating-text="t('hr.payment_method')" :error="form.errors?.payment_method"
            />

            <NextInput :label="t('hr.bank_name')" v-model="form.bank_name" :error="form.errors?.bank_name" />
            <NextInput :label="t('hr.bank_account_number')" v-model="form.bank_account_number" :error="form.errors?.bank_account_number" />
            <NextInput :label="t('hr.bank_account_title')" v-model="form.bank_account_title" :error="form.errors?.bank_account_title" />
            <NextInput :label="t('hr.iban')" v-model="form.iban" :error="form.errors?.iban" />

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" v-model="form.is_tax_exempt" class="h-4 w-4 rounded border-border text-primary" />
                {{ t('hr.is_tax_exempt') }}
            </label>
        </div>
    </fieldset>

    <!-- System access -->
    <fieldset class="relative mb-5 rounded-xl border border-violet-500 p-4 shadow-sm">
        <legend class="px-2 text-sm font-semibold text-violet-500">{{ t('hr.sections.access') }}</legend>
        <div class="mt-2 grid grid-cols-1 gap-4 md:grid-cols-3">
            <NextSelect
                :options="opt('users')" v-model="form.selected_user"
                @update:modelValue="(v) => pick('user_id', v)"
                label-key="name" value-key="id" :reduce="(x) => x"
                :searchable="true" resource-type="users"
                :floating-text="t('hr.linked_user')" :error="form.errors?.user_id"
            />
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" v-model="form.self_service_enabled" class="h-4 w-4 rounded border-border text-primary" />
                {{ t('hr.self_service_enabled') }}
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" v-model="form.is_active" class="h-4 w-4 rounded border-border text-primary" />
                {{ t('general.active') }}
            </label>

            <div class="md:col-span-3">
                <NextTextarea :label="t('admin.shared.remark')" v-model="form.remark" :error="form.errors?.remark" />
            </div>
        </div>
    </fieldset>

    <!-- Documents -->
    <fieldset v-if="showDocuments" class="relative mb-5 rounded-xl border border-violet-500 p-4 shadow-sm">
        <legend class="px-2 text-sm font-semibold text-violet-500">{{ t('hr.documents') }}</legend>
        <div class="mt-2">
            <AttachmentUploader
                v-model="form.documents"
                :label="t('general.attachments')"
                :error="form.errors?.['documents.0']"
            />
        </div>
    </fieldset>
</template>
