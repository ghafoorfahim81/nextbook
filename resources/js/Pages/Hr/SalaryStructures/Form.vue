<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextDatePicker from '@/Components/next/NextDatePicker.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import { Button } from '@/Components/ui/button';
import { Plus, Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t } = useI18n();

const props = defineProps({
    salaryStructure: { type: Object, default: null },
    filterOptions: { type: Object, default: () => ({}) },
    components: { type: [Array, Object], default: () => [] },
});

const isEditing = computed(() => !!props.salaryStructure?.id);

const componentList = computed(() => props.components?.data ?? props.components ?? []);
const currencies = computed(() => props.filterOptions?.currencies || []);
const frequencies = computed(() => props.filterOptions?.payFrequencies || []);
const departments = computed(() => props.filterOptions?.departments || []);
const designations = computed(() => props.filterOptions?.designations || []);

const form = useForm({
    name: props.salaryStructure?.name ?? '',
    code: props.salaryStructure?.code ?? '',
    employee_id: props.salaryStructure?.employee_id ?? null,
    designation_id: props.salaryStructure?.designation_id ?? null,
    department_id: props.salaryStructure?.department_id ?? null,
    currency_id: props.salaryStructure?.currency_id ?? null,
    effective_from: props.salaryStructure?.effective_from ?? '',
    effective_to: props.salaryStructure?.effective_to ?? null,
    basic_salary: props.salaryStructure?.basic_salary ?? null,
    pay_frequency: props.salaryStructure?.pay_frequency ?? 'monthly',
    is_active: props.salaryStructure?.is_active ?? true,
    remark: props.salaryStructure?.remark ?? '',
    lines: (props.salaryStructure?.lines ?? []).map((line) => ({
        salary_component_id: line.salary_component_id,
        amount: line.amount,
        percentage: line.percentage,
        sequence: line.sequence,
    })),

    selected_employee: props.salaryStructure?.employee_id
        ? { id: props.salaryStructure.employee_id, name: props.salaryStructure.employee_name }
        : null,
    selected_designation: null,
    selected_department: null,
    selected_currency: null,
    selected_frequency: null,
});

watch([currencies, frequencies, departments, designations], () => {
    form.selected_currency = currencies.value.find((x) => x.id === form.currency_id) ?? null;
    form.selected_frequency = frequencies.value.find((x) => x.id === form.pay_frequency) ?? null;
    form.selected_department = departments.value.find((x) => x.id === form.department_id) ?? null;
    form.selected_designation = designations.value.find((x) => x.id === form.designation_id) ?? null;
}, { immediate: true });

const componentFor = (id) => componentList.value.find((c) => c.id === id);

const addLine = () => {
    form.lines.push({
        salary_component_id: null,
        amount: null,
        percentage: null,
        sequence: form.lines.length + 1,
    });
};

const removeLine = (index) => {
    form.lines.splice(index, 1);
    form.lines.forEach((line, i) => { line.sequence = i + 1; });
};

// Picking a component seeds its own defaults, so the common case is one click.
const onComponentChosen = (index, option) => {
    const line = form.lines[index];
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
    let total = Number(form.basic_salary || 0);

    for (const line of form.lines) {
        const component = componentFor(line.salary_component_id);
        if (!component || component.component_type !== 'earning') continue;

        if (component.calculation_type === 'fixed') {
            total += Number(line.amount || 0);
        } else if (component.calculation_type === 'percent_of_basic') {
            total += Number(form.basic_salary || 0) * Number(line.percentage || 0) / 100;
        }
    }

    return total;
});

const money = (value) => Number(value ?? 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const submit = () => {
    form.transform((data) => Object.fromEntries(
        Object.entries(data).filter(([k]) => !k.startsWith('selected_'))
    ));

    const options = { onError: () => toast.error(t('general.error')) };

    if (isEditing.value) form.put(route('salary-structures.update', props.salaryStructure.id), options);
    else form.post(route('salary-structures.store'), options);
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-5">
        <div class="rounded-xl border border-border bg-card p-5">
            <h2 class="mb-1 text-base font-semibold">{{ t('hr.package') }}</h2>
            <p class="mb-4 text-sm text-muted-foreground">{{ t('hr.structure_target_hint') }}</p>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <NextInput :label="t('general.name')" v-model="form.name" :error="form.errors?.name" autofocus />
                <NextInput :label="t('general.code')" v-model="form.code" :error="form.errors?.code" />

                <NextSelect
                    v-model="form.selected_employee"
                    @update:modelValue="(v) => { form.employee_id = v?.id ?? null }"
                    resource-type="employees"
                    label-key="name" value-key="id" :reduce="(x) => x"
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
            </div>
        </div>

        <!-- Allowances and deductions -->
        <div class="rounded-xl border border-border bg-card">
            <div class="flex items-center justify-between border-b border-border px-5 py-3">
                <div>
                    <h2 class="text-base font-semibold">{{ t('hr.components') }}</h2>
                    <p class="text-xs text-muted-foreground">{{ t('hr.structure_components_hint') }}</p>
                </div>
                <Button type="button" size="sm" variant="outline" @click="addLine">
                    <Plus class="mr-1 h-4 w-4" /> {{ t('hr.add_component') }}
                </Button>
            </div>

            <div v-if="!form.lines.length" class="px-5 py-8 text-center text-sm text-muted-foreground">
                {{ t('hr.basic_only') }}
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-sm">
                    <thead class="bg-muted/50 text-xs uppercase text-muted-foreground">
                        <tr>
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
                            <td class="px-4 py-2 min-w-[220px]">
                                <NextSelect
                                    :options="componentList"
                                    :model-value="componentFor(line.salary_component_id)"
                                    @update:modelValue="(v) => onComponentChosen(index, v)"
                                    label-key="name" value-key="id" :reduce="(x) => x"
                                    :error="form.errors?.[`lines.${index}.salary_component_id`]"
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

            <p v-if="form.errors?.lines" class="border-t border-border px-5 py-2 text-xs text-destructive">
                {{ form.errors.lines }}
            </p>

            <!-- Indicative only: anything on a percentage of GROSS is not
                 known until the whole payslip is resolved. -->
            <div class="flex items-center justify-between border-t border-border bg-muted/30 px-5 py-3 text-sm">
                <span class="text-muted-foreground">{{ t('hr.indicative_gross') }}</span>
                <span class="font-semibold">{{ money(indicativeGross) }}</span>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-5">
            <NextTextarea :label="t('admin.shared.remark')" v-model="form.remark" :error="form.errors?.remark" />
            <label class="mt-4 flex items-center gap-2 text-sm">
                <input type="checkbox" v-model="form.is_active" class="h-4 w-4 rounded border-border text-primary" />
                {{ t('general.active') }}
            </label>
        </div>

        <div class="flex justify-end gap-3">
            <Button type="button" variant="outline" @click="$inertia.get(route('salary-structures.index'))">
                {{ t('general.cancel') }}
            </Button>
            <Button type="submit" :disabled="form.processing">
                {{ isEditing ? t('general.update') : t('general.create') }}
            </Button>
        </div>
    </form>
</template>
