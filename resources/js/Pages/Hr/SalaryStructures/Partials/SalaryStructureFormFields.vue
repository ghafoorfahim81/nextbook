<script setup>
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextDatePicker from '@/Components/next/NextDatePicker.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import { Button } from '@/Components/ui/button';
import { Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    form: { type: Object, required: true },
    filterOptions: { type: Object, default: () => ({}) },
    components: { type: [Array, Object], default: () => [] },
});

const componentList = computed(() => props.components?.data ?? props.components ?? []);
const currencies = computed(() => props.filterOptions?.currencies || []);
const frequencies = computed(() => props.filterOptions?.payFrequencies || []);
const departments = computed(() => props.filterOptions?.departments || []);
const designations = computed(() => props.filterOptions?.designations || []);

const componentFor = (id) => componentList.value.find((c) => c.id === id);

const addLine = () => {
    props.form.lines.push({
        salary_component_id: null,
        amount: null,
        percentage: null,
        sequence: props.form.lines.length + 1,
    });
};

const removeLine = (index) => {
    props.form.lines.splice(index, 1);
    props.form.lines.forEach((line, i) => { line.sequence = i + 1; });
};

// Picking a component seeds its own defaults, so the common case is one click.
const onComponentChosen = (index, option) => {
    const line = props.form.lines[index];
    line.salary_component_id = option?.id ?? null;

    const component = componentFor(option?.id);
    if (!component) return;

    line.amount = component.amount;
    line.percentage = component.percentage;
};

const isPercentageLine = (line) => {
    const component = componentFor(line.salary_component_id);

    return ['percent_of_basic', 'percent_of_gross'].includes(component?.calculation_type);
};

// Only components on a fixed amount can be totalled up front. A percentage of
// gross is not known until the whole payslip is resolved.
const indicativeGross = computed(() => {
    let total = Number(props.form.basic_salary || 0);

    for (const line of props.form.lines) {
        const component = componentFor(line.salary_component_id);
        if (!component || component.component_type !== 'earning') continue;

        if (component.calculation_type === 'fixed') {
            total += Number(line.amount || 0);
        } else if (component.calculation_type === 'percent_of_basic') {
            total += Number(props.form.basic_salary || 0) * Number(line.percentage || 0) / 100;
        }
    }

    return total;
});

const money = (value) => Number(value ?? 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const lineError = (index) => props.form.errors?.[`lines.${index}.salary_component_id`];
</script>

<template>
    <!-- Package -->
    <fieldset class="relative mb-5 rounded-xl border border-violet-500 p-4 shadow-sm">
        <legend class="px-2 text-sm font-semibold text-violet-500">{{ t('hr.package') }}</legend>

        <p class="mb-3 text-sm text-muted-foreground">{{ t('hr.structure_target_hint') }}</p>

        <div class="mt-2 grid grid-cols-1 gap-4 md:grid-cols-3">
            <NextInput :label="t('general.name')" v-model="form.name" :error="form.errors?.name" autofocus />
            <NextInput :label="t('general.code')" v-model="form.code" :error="form.errors?.code" />

            <NextSelect
                :options="[]"
                v-model="form.selected_employee"
                @update:modelValue="(v) => { form.employee_id = v?.id ?? null }"
                label-key="name" value-key="id" :reduce="(x) => x"
                :searchable="true" resource-type="employees" :search-fields="['full_name', 'code']"
                :floating-text="t('hr.employee')" :error="form.errors?.employee_id"
            />

            <NextSelect
                :options="designations" v-model="form.selected_designation"
                @update:modelValue="(v) => { form.designation_id = v?.id ?? null }"
                label-key="name" value-key="id" :reduce="(x) => x"
                :floating-text="t('hr.designation')" :error="form.errors?.designation_id"
            />

            <NextSelect
                :options="departments" v-model="form.selected_department"
                @update:modelValue="(v) => { form.department_id = v?.id ?? null }"
                label-key="name" value-key="id" :reduce="(x) => x"
                :floating-text="t('hr.department')" :error="form.errors?.department_id"
            />

            <NextSelect
                :options="currencies" v-model="form.selected_currency"
                @update:modelValue="(v) => { form.currency_id = v?.id ?? null }"
                label-key="code" value-key="id" :reduce="(x) => x"
                :floating-text="t('general.currency')" :error="form.errors?.currency_id"
            />

            <NextInput :label="t('hr.basic_salary')" type="number" step="any" v-model="form.basic_salary" :error="form.errors?.basic_salary" />

            <NextSelect
                :options="frequencies" v-model="form.selected_frequency"
                @update:modelValue="(v) => { form.pay_frequency = v?.id ?? null }"
                label-key="name" value-key="id" :reduce="(x) => x"
                :floating-text="t('hr.pay_frequency')" :error="form.errors?.pay_frequency"
            />

            <NextDatePicker :label="t('hr.effective_from')" v-model="form.effective_from" :error="form.errors?.effective_from" />
            <NextDatePicker :label="t('hr.effective_to')" v-model="form.effective_to" :error="form.errors?.effective_to" />

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" v-model="form.is_active" class="h-4 w-4 rounded border-border text-primary" />
                {{ t('general.active') }}
            </label>

            <div class="md:col-span-3">
                <NextTextarea :label="t('admin.shared.remark')" v-model="form.remark" :error="form.errors?.remark" />
            </div>
        </div>
    </fieldset>

    <!-- Allowances and deductions -->
    <fieldset class="relative mb-5 rounded-xl border border-violet-500 p-4 shadow-sm">
        <legend class="px-2 text-sm font-semibold text-violet-500">{{ t('hr.components') }}</legend>

        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-muted-foreground">{{ t('hr.structure_components_hint') }}</p>
            <Button type="button" size="sm" variant="outline" @click="addLine">
                <Plus class="h-4 w-4 ltr:mr-1 rtl:ml-1" /> {{ t('hr.add_component') }}
            </Button>
        </div>

        <div v-if="!form.lines.length" class="rounded-xl border border-border py-8 text-center text-sm text-muted-foreground">
            {{ t('hr.basic_only') }}
        </div>

        <div v-else class="overflow-x-auto rounded-xl border border-border bg-card shadow-sm">
            <table class="w-full min-w-[720px] text-sm">
                <thead class="bg-muted/50 text-xs uppercase">
                    <tr class="font-semibold text-violet-500">
                        <th class="px-4 py-2 text-start">#</th>
                        <th class="px-4 py-2 text-start">{{ t('hr.component') }}</th>
                        <th class="px-4 py-2 text-start">{{ t('hr.calculation_type') }}</th>
                        <th class="px-4 py-2 text-end">{{ t('hr.value') }}</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(line, index) in form.lines" :key="index" class="border-t border-border">
                        <td class="px-4 py-2 text-muted-foreground">{{ line.sequence }}</td>
                        <td class="min-w-[220px] px-4 py-2">
                            <NextSelect
                                :options="componentList"
                                :model-value="componentFor(line.salary_component_id)"
                                @update:modelValue="(v) => onComponentChosen(index, v)"
                                label-key="name" value-key="id" :reduce="(x) => x"
                                :error="lineError(index)"
                            />
                        </td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ componentFor(line.salary_component_id)?.calculation_type_label ?? '—' }}
                        </td>
                        <td class="px-4 py-2 text-end">
                            <div class="flex items-center justify-end gap-1">
                                <input
                                    v-if="isPercentageLine(line)"
                                    type="number" step="any" v-model.number="line.percentage"
                                    class="w-28 rounded border border-border bg-background px-2 py-1 text-end"
                                />
                                <input
                                    v-else
                                    type="number" step="any" v-model.number="line.amount"
                                    class="w-28 rounded border border-border bg-background px-2 py-1 text-end"
                                />
                                <span v-if="isPercentageLine(line)" class="text-muted-foreground">%</span>
                            </div>
                        </td>
                        <td class="px-4 py-2 text-end">
                            <button type="button" class="text-muted-foreground transition hover:text-destructive" @click="removeLine(index)">
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p v-if="form.errors?.lines" class="mt-2 text-xs text-destructive">{{ form.errors.lines }}</p>

        <!-- Indicative only: anything on a percentage of GROSS is not known
             until the whole payslip is resolved. -->
        <div class="mt-3 flex items-center justify-between rounded-xl border border-border bg-muted/30 px-4 py-3 text-sm">
            <span class="text-muted-foreground">{{ t('hr.indicative_gross') }}</span>
            <span class="font-semibold">{{ money(indicativeGross) }}</span>
        </div>
    </fieldset>
</template>
