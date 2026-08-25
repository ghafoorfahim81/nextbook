<script setup>
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextDatePicker from '@/Components/next/NextDatePicker.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import { Button } from '@/Components/ui/button';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    form: { type: Object, required: true },
    filterOptions: { type: Object, default: () => ({}) },
    /** Assigned by the server, shown read-only so the loan can be referred to. */
    number: { type: [String, Number], default: null },
});

const loanTypes = computed(() => props.filterOptions?.loanTypes || []);
const employees = computed(() => props.filterOptions?.employees || []);
const currencies = computed(() => props.filterOptions?.currencies || []);

// Shown live, because an instalment schedule that does not repay the loan is
// rejected on save and it is better to see that while typing.
const scheduleTotal = computed(() =>
    Number(props.form.installment_amount || 0) * Number(props.form.installments_count || 0));
const scheduleShortfall = computed(() => Number(props.form.principal_amount || 0) - scheduleTotal.value);

const suggestInstalment = () => {
    const count = Number(props.form.installments_count || 0);
    if (!count || !props.form.principal_amount) return;

    props.form.installment_amount = Math.ceil(Number(props.form.principal_amount) / count);
};
</script>

<template>
    <!-- Loan details -->
    <fieldset class="relative mb-5 rounded-xl border border-violet-500 p-4 shadow-sm">
        <legend class="px-2 text-sm font-semibold text-violet-500">{{ t('hr.loan_details') }}</legend>

        <div class="mt-2 grid grid-cols-1 gap-4 md:grid-cols-3">
            <NextSelect
                :options="employees"
                v-model="form.selected_employee"
                @update:modelValue="(v) => { form.employee_id = v?.id ?? null }"
                label-key="name" value-key="id" :reduce="(x) => x"
                :searchable="true" resource-type="employees" :search-fields="['full_name', 'code']"
                :floating-text="t('hr.employee')" :error="form.errors?.employee_id"
            />

            <NextSelect
                :options="loanTypes" v-model="form.selected_type"
                @update:modelValue="(v) => { form.loan_type = v?.id ?? null }"
                label-key="name" value-key="id" :reduce="(x) => x"
                :floating-text="t('hr.loan_type')" :error="form.errors?.loan_type"
            />

            <NextSelect
                :options="currencies" v-model="form.selected_currency"
                @update:modelValue="(v) => { form.currency_id = v?.id ?? null }"
                label-key="code" value-key="id" :reduce="(x) => x"
                :floating-text="t('general.currency')" :error="form.errors?.currency_id"
            />

            <NextInput :label="t('hr.principal')" type="number" step="any" v-model="form.principal_amount" :error="form.errors?.principal_amount" />
            <NextDatePicker :label="t('hr.issue_date')" v-model="form.issue_date" :error="form.errors?.issue_date" />
            <NextInput :label="t('general.rate')" type="number" step="any" v-model="form.rate" :error="form.errors?.rate" />

            <NextInput
                v-if="number"
                :label="t('general.number')"
                :model-value="String(number)"
                disabled
            />
        </div>
    </fieldset>

    <!-- Repayment schedule -->
    <fieldset class="relative mb-5 rounded-xl border border-violet-500 p-4 shadow-sm">
        <legend class="px-2 text-sm font-semibold text-violet-500">{{ t('hr.repayment_schedule') }}</legend>

        <div class="mt-2 grid grid-cols-1 gap-4 md:grid-cols-3">
            <NextInput :label="t('hr.installments_count')" type="number" v-model="form.installments_count" :error="form.errors?.installments_count" />

            <div class="flex items-start gap-2">
                <div class="flex-1">
                    <NextInput :label="t('hr.installment')" type="number" step="any" v-model="form.installment_amount" :error="form.errors?.installment_amount" />
                </div>
                <Button type="button" size="sm" variant="outline" class="mt-1" @click="suggestInstalment">
                    {{ t('hr.suggest') }}
                </Button>
            </div>

            <NextDatePicker :label="t('hr.first_deduction_period')" v-model="form.first_deduction_period" :error="form.errors?.first_deduction_period" />

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" v-model="form.deduct_from_payroll" class="h-4 w-4 rounded border-border text-primary" />
                {{ t('hr.deduct_from_payroll') }}
            </label>

            <div class="flex items-center md:col-span-2">
                <p v-if="scheduleShortfall > 0.0001" class="text-sm text-destructive">
                    {{ t('hr.instalments_do_not_cover_loan') }}
                </p>
                <p v-else-if="form.principal_amount" class="text-sm text-muted-foreground">
                    {{ t('hr.schedule_covers_loan') }}
                </p>
            </div>

            <div class="md:col-span-3">
                <NextTextarea :label="t('admin.shared.remark')" v-model="form.remark" :error="form.errors?.remark" />
            </div>
        </div>
    </fieldset>
</template>
