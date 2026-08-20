<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextDatePicker from '@/Components/next/NextDatePicker.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import { Button } from '@/Components/ui/button';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t } = useI18n();

const props = defineProps({
    payroll: { type: Object, default: null },
    filterOptions: { type: Object, default: () => ({}) },
    nextNumber: { type: [String, Number], default: null },
});

const isEditing = computed(() => !!props.payroll?.id);

const currencies = computed(() => props.filterOptions?.currencies || []);
const frequencies = computed(() => props.filterOptions?.payFrequencies || []);
const departments = computed(() => props.filterOptions?.departments || []);
const employmentTypes = computed(() => props.filterOptions?.employmentTypes || []);

const form = useForm({
    name: props.payroll?.name ?? '',
    period_start: props.payroll?.period_start ?? '',
    period_end: props.payroll?.period_end ?? '',
    pay_date: props.payroll?.pay_date ?? '',
    period_label: props.payroll?.period_label ?? '',
    pay_frequency: props.payroll?.pay_frequency ?? 'monthly',
    currency_id: props.payroll?.currency_id ?? null,
    rate: props.payroll?.rate ?? 1,
    department_id: props.payroll?.department_id ?? null,
    employment_type: props.payroll?.employment_type ?? null,
    remark: props.payroll?.remark ?? '',

    selected_frequency: null,
    selected_currency: null,
    selected_department: null,
    selected_employment_type: null,
});

watch([frequencies, currencies, departments, employmentTypes], () => {
    form.selected_frequency = frequencies.value.find((x) => x.id === form.pay_frequency) ?? null;
    form.selected_currency = currencies.value.find((x) => x.id === form.currency_id) ?? null;
    form.selected_department = departments.value.find((x) => x.id === form.department_id) ?? null;
    form.selected_employment_type = employmentTypes.value.find((x) => x.id === form.employment_type) ?? null;
}, { immediate: true });

const submit = () => {
    form.transform((data) => Object.fromEntries(
        Object.entries(data).filter(([k]) => !k.startsWith('selected_'))
    ));

    const options = {
        onError: () => toast.error(t('general.error')),
    };

    if (isEditing.value) form.put(route('payrolls.update', props.payroll.id), options);
    else form.post(route('payrolls.store'), options);
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <div class="rounded-xl border border-border bg-card p-5">
            <div class="mb-4 flex items-baseline justify-between">
                <h2 class="text-base font-semibold">{{ t('hr.payroll_period') }}</h2>
                <span v-if="!isEditing && nextNumber" class="text-sm text-muted-foreground">
                    #{{ nextNumber }}
                </span>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <NextInput :label="t('general.name')" v-model="form.name" :error="form.errors?.name" />

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
        </div>

        <div class="rounded-xl border border-border bg-card p-5">
            <h2 class="mb-1 text-base font-semibold">{{ t('hr.payroll_scope') }}</h2>
            <p class="mb-4 text-sm text-muted-foreground">{{ t('hr.payroll_scope_hint') }}</p>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
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
            </div>

            <div class="mt-4">
                <NextTextarea :label="t('admin.shared.remark')" v-model="form.remark" :error="form.errors?.remark" />
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <Button type="button" variant="outline" @click="$inertia.get(route('payrolls.index'))">
                {{ t('general.cancel') }}
            </Button>
            <Button type="submit" :disabled="form.processing">
                {{ isEditing ? t('general.update') : t('general.create') }}
            </Button>
        </div>
    </form>
</template>
