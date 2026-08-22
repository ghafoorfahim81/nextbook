<script setup>
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextDatePicker from '@/Components/next/NextDatePicker.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    form: { type: Object, required: true },
    filterOptions: { type: Object, default: () => ({}) },
    /** Assigned by the server, shown read-only so the run can be referred to. */
    number: { type: [String, Number], default: null },
});

const currencies = computed(() => props.filterOptions?.currencies || []);
const frequencies = computed(() => props.filterOptions?.payFrequencies || []);
const departments = computed(() => props.filterOptions?.departments || []);
const employmentTypes = computed(() => props.filterOptions?.employmentTypes || []);
</script>

<template>
    <!-- Period -->
    <fieldset class="relative mb-5 rounded-xl border border-violet-500 p-4 shadow-sm">
        <legend class="px-2 text-sm font-semibold text-violet-500">{{ t('hr.payroll_period') }}</legend>

        <div class="mt-2 grid grid-cols-1 gap-4 md:grid-cols-3">
            <NextInput :label="t('general.name')" v-model="form.name" :error="form.errors?.name" autofocus />

            <NextInput
                v-if="number"
                :label="t('general.number')"
                :model-value="String(number)"
                disabled
            />

            <NextSelect
                :options="frequencies" v-model="form.selected_frequency"
                @update:modelValue="(v) => { form.pay_frequency = v?.id ?? null }"
                label-key="name" value-key="id" :reduce="(x) => x"
                :floating-text="t('hr.pay_frequency')" :error="form.errors?.pay_frequency"
            />

            <NextSelect
                :options="currencies" v-model="form.selected_currency"
                @update:modelValue="(v) => { form.currency_id = v?.id ?? null }"
                label-key="code" value-key="id" :reduce="(x) => x"
                :floating-text="t('general.currency')" :error="form.errors?.currency_id"
            />

            <NextDatePicker :label="t('hr.period_start')" v-model="form.period_start" :error="form.errors?.period_start" />
            <NextDatePicker :label="t('hr.period_end')" v-model="form.period_end" :error="form.errors?.period_end" />
            <NextDatePicker :label="t('hr.pay_date')" v-model="form.pay_date" :error="form.errors?.pay_date" />
        </div>
    </fieldset>

    <!-- Who the run covers -->
    <fieldset class="relative mb-5 rounded-xl border border-violet-500 p-4 shadow-sm">
        <legend class="px-2 text-sm font-semibold text-violet-500">{{ t('hr.payroll_scope') }}</legend>

        <p class="mb-3 text-sm text-muted-foreground">{{ t('hr.payroll_scope_hint') }}</p>

        <div class="mt-2 grid grid-cols-1 gap-4 md:grid-cols-3">
            <NextSelect
                :options="departments" v-model="form.selected_department"
                @update:modelValue="(v) => { form.department_id = v?.id ?? null }"
                label-key="name" value-key="id" :reduce="(x) => x"
                :floating-text="t('hr.department')" :error="form.errors?.department_id"
            />

            <NextSelect
                :options="employmentTypes" v-model="form.selected_employment_type"
                @update:modelValue="(v) => { form.employment_type = v?.id ?? null }"
                label-key="name" value-key="id" :reduce="(x) => x"
                :floating-text="t('hr.employment_type')" :error="form.errors?.employment_type"
            />

            <div class="md:col-span-3">
                <NextTextarea :label="t('admin.shared.remark')" v-model="form.remark" :error="form.errors?.remark" />
            </div>
        </div>
    </fieldset>
</template>
