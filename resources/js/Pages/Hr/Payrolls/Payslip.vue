<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Printer, ArrowLeft } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    payroll: Object,
    payslip: Object,
});

const { t } = useI18n();
const page = usePage();

const run = computed(() => props.payroll?.data ?? props.payroll);
const slip = computed(() => props.payslip?.data ?? props.payslip);
const company = computed(() => page.props.company ?? {});

const components = computed(() => slip.value?.components ?? []);

// Split by component_type so the payslip reads the way a payslip reads —
// what was earned on one side, what was taken off on the other.
const earnings = computed(() => components.value.filter((c) => c.component_type === 'earning'));
const deductions = computed(() => components.value.filter((c) => c.component_type === 'deduction'));

const money = (value) => Number(value ?? 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const printPayslip = () => window.print();
</script>

<template>
    <AppLayout :title="`${t('hr.payslip')} — ${slip.employee_name}`">
        <div class="mx-auto max-w-3xl space-y-4">
            <div class="flex items-center justify-between print:hidden">
                <Button variant="outline" @click="$inertia.get(route('payrolls.show', run.id))">
                    <ArrowLeft class="mr-1.5 h-4 w-4" /> {{ t('general.back') }}
                </Button>
                <Button @click="printPayslip">
                    <Printer class="mr-1.5 h-4 w-4" /> {{ t('general.print') }}
                </Button>
            </div>

            <div class="rounded-xl border border-border bg-card p-8 print:border-0 print:p-0">
                <!-- Letterhead -->
                <div class="mb-6 border-b border-border pb-4 text-center">
                    <h1 class="text-lg font-bold">{{ company.name }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">{{ t('hr.payslip') }}</p>
                    <p class="text-sm font-medium">
                        {{ run.period_label || run.period_end }}
                    </p>
                </div>

                <!-- Who and when -->
                <div class="mb-6 grid grid-cols-2 gap-x-8 gap-y-2 text-sm">
                    <div>
                        <span class="text-muted-foreground">{{ t('hr.employee') }}:</span>
                        <span class="ms-2 font-medium">{{ slip.employee_name }}</span>
                    </div>
                    <div>
                        <span class="text-muted-foreground">{{ t('general.code') }}:</span>
                        <span class="ms-2 font-medium">{{ slip.employee_code }}</span>
                    </div>
                    <div>
                        <span class="text-muted-foreground">{{ t('hr.period') }}:</span>
                        <span class="ms-2">{{ run.period_start }} — {{ run.period_end }}</span>
                    </div>
                    <div>
                        <span class="text-muted-foreground">{{ t('hr.pay_date') }}:</span>
                        <span class="ms-2">{{ run.pay_date }}</span>
                    </div>
                    <div>
                        <span class="text-muted-foreground">{{ t('general.currency') }}:</span>
                        <span class="ms-2">{{ slip.currency_code }}</span>
                    </div>
                    <div>
                        <span class="text-muted-foreground">{{ t('hr.payroll') }}:</span>
                        <span class="ms-2">#{{ run.number }}</span>
                    </div>
                </div>

                <!-- Attendance -->
                <div class="mb-6 grid grid-cols-5 gap-2 rounded-lg bg-muted/40 p-3 text-center text-xs">
                    <div>
                        <span class="block text-muted-foreground">{{ t('hr.working_days') }}</span>
                        <span class="font-semibold">{{ slip.working_days }}</span>
                    </div>
                    <div>
                        <span class="block text-muted-foreground">{{ t('hr.present_days') }}</span>
                        <span class="font-semibold">{{ slip.present_days }}</span>
                    </div>
                    <div>
                        <span class="block text-muted-foreground">{{ t('hr.absent_days') }}</span>
                        <span class="font-semibold">{{ slip.absent_days }}</span>
                    </div>
                    <div>
                        <span class="block text-muted-foreground">{{ t('hr.leave_days') }}</span>
                        <span class="font-semibold">{{ slip.paid_leave_days }}</span>
                    </div>
                    <div>
                        <span class="block text-muted-foreground">{{ t('hr.overtime_hours') }}</span>
                        <span class="font-semibold">{{ slip.overtime_hours }}</span>
                    </div>
                </div>

                <!-- Earnings and deductions -->
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <h3 class="mb-2 border-b border-border pb-1 text-sm font-semibold">
                            {{ t('hr.earnings') }}
                        </h3>
                        <table class="w-full text-sm">
                            <tbody>
                                <tr v-for="item in earnings" :key="item.id">
                                    <td class="py-1">{{ item.component_name }}</td>
                                    <td class="py-1 text-end">{{ money(item.amount) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="border-t border-border font-semibold">
                                    <td class="py-1.5">{{ t('hr.gross') }}</td>
                                    <td class="py-1.5 text-end">{{ money(slip.gross_earnings) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div>
                        <h3 class="mb-2 border-b border-border pb-1 text-sm font-semibold">
                            {{ t('hr.deductions') }}
                        </h3>
                        <table class="w-full text-sm">
                            <tbody>
                                <tr v-for="item in deductions" :key="item.id">
                                    <td class="py-1">{{ item.component_name }}</td>
                                    <td class="py-1 text-end">{{ money(item.amount) }}</td>
                                </tr>
                                <tr v-if="!deductions.length">
                                    <td class="py-1 text-muted-foreground" colspan="2">—</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="border-t border-border font-semibold">
                                    <td class="py-1.5">{{ t('hr.total_deductions') }}</td>
                                    <td class="py-1.5 text-end">{{ money(slip.total_deductions) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Net -->
                <div class="mt-6 flex items-center justify-between rounded-lg bg-primary/10 px-4 py-3">
                    <span class="font-semibold">{{ t('hr.net_payable') }}</span>
                    <span class="text-lg font-bold">{{ money(slip.net_payable) }} {{ slip.currency_code }}</span>
                </div>

                <div v-if="Number(slip.paid_amount) > 0" class="mt-2 flex items-center justify-between px-4 text-sm">
                    <span class="text-muted-foreground">{{ t('hr.paid') }}</span>
                    <span>{{ money(slip.paid_amount) }}</span>
                </div>
                <div v-if="Number(slip.outstanding) > 0" class="flex items-center justify-between px-4 text-sm">
                    <span class="text-muted-foreground">{{ t('hr.outstanding') }}</span>
                    <span class="font-medium">{{ money(slip.outstanding) }}</span>
                </div>

                <!-- Which tax table produced the figure above. A payslip that
                     cannot explain its own tax is not much of a record. -->
                <p v-if="slip.tax_table_name" class="mt-4 text-xs text-muted-foreground">
                    {{ t('hr.tax_computed_using', { table: slip.tax_table_name }) }}
                </p>

                <div class="mt-10 grid grid-cols-2 gap-8 text-xs text-muted-foreground">
                    <div class="border-t border-border pt-2 text-center">{{ t('hr.employee_signature') }}</div>
                    <div class="border-t border-border pt-2 text-center">{{ t('hr.authorised_signature') }}</div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@media print {
    :deep(aside),
    :deep(header),
    :deep(nav) {
        display: none !important;
    }
}
</style>
