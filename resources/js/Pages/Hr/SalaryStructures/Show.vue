<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import FormPageToolbar from '@/Components/FormPageToolbar.vue';
import { computed } from 'vue';
import { Button } from '@/Components/ui/button';
import { Pencil } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { useAuth } from '@/composables/useAuth';

const props = defineProps({ salaryStructure: Object });

const { t } = useI18n();
const { can } = useAuth();

const structure = computed(() => props.salaryStructure?.data ?? props.salaryStructure);
const lines = computed(() => structure.value?.lines ?? []);

const money = (value) => Number(value ?? 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const appliesTo = computed(() => structure.value?.employee_name
    || structure.value?.designation_name
    || structure.value?.department_name
    || '—');
</script>

<template>
    <AppLayout :title="structure.name">
        <FormPageToolbar back-route="salary-structures.index" module="salary_structures" />

        <div class="space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-border bg-card p-4 shadow-sm">
                <div>
                    <h1 class="text-xl font-semibold">{{ structure.name }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ appliesTo }} · {{ structure.effective_from }}
                        <template v-if="structure.effective_to"> — {{ structure.effective_to }}</template>
                    </p>
                </div>
                <Button
                    v-if="can('salary_structures.update')"
                    size="sm"
                    variant="outline"
                    class="h-9 border-primary text-primary hover:bg-primary hover:text-white"
                    @click="$inertia.get(route('salary-structures.edit', structure.id))"
                >
                    <Pencil class="h-4 w-4 ltr:mr-1 rtl:ml-1" />
                    {{ t('general.edit', { name: t('hr.salary_structure') }) }}
                </Button>
            </div>

            <div class="grid grid-cols-2 gap-4 rounded-xl border border-border bg-card p-5 md:grid-cols-4">
                <div>
                    <p class="text-xs text-muted-foreground">{{ t('hr.basic_salary') }}</p>
                    <p class="mt-1 text-lg font-semibold">{{ money(structure.basic_salary) }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">{{ t('general.currency') }}</p>
                    <p class="mt-1 font-medium">{{ structure.currency_code ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">{{ t('hr.pay_frequency') }}</p>
                    <p class="mt-1 font-medium">{{ structure.pay_frequency_label }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">{{ t('general.status') }}</p>
                    <p class="mt-1 font-medium">{{ structure.is_active ? t('general.active') : t('general.inactive') }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-border bg-card">
                <div class="border-b border-border px-5 py-3">
                    <h2 class="text-base font-semibold">{{ t('hr.components') }}</h2>
                </div>

                <div v-if="!lines.length" class="px-5 py-8 text-center text-sm text-muted-foreground">
                    {{ t('hr.basic_only') }}
                </div>

                <table v-else class="w-full text-sm">
                    <thead class="bg-muted/50 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 text-start">{{ t('hr.component') }}</th>
                            <th class="px-4 py-2 text-start">{{ t('hr.component_type') }}</th>
                            <th class="px-4 py-2 text-start">{{ t('hr.calculation_type') }}</th>
                            <th class="px-4 py-2 text-end">{{ t('hr.value') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="line in lines" :key="line.id" class="border-t border-border">
                            <td class="px-4 py-2 font-medium">{{ line.component_name }}</td>
                            <td class="px-4 py-2 text-muted-foreground">{{ line.component_type }}</td>
                            <td class="px-4 py-2 text-muted-foreground">{{ line.calculation_type_label }}</td>
                            <td class="px-4 py-2 text-end">
                                <span v-if="line.percentage !== null">{{ line.percentage }}%</span>
                                <span v-else>{{ money(line.amount) }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-if="structure.remark" class="rounded-xl border border-border bg-card p-5 text-sm">
                {{ structure.remark }}
            </p>
        </div>
    </AppLayout>
</template>
