<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { computed } from 'vue';
import { Button } from '@/Components/ui/button';
import { ArrowLeft } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const props = defineProps({ salaryPayment: Object });

const { t } = useI18n();

const payment = computed(() => props.salaryPayment?.data ?? props.salaryPayment);
const lines = computed(() => payment.value?.lines ?? []);

const money = (value) => Number(value ?? 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});
</script>

<template>
    <AppLayout :title="`${t('hr.salary_payment')} #${payment.number}`">
        <div class="mx-auto max-w-4xl space-y-5">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">{{ t('hr.salary_payment') }} #{{ payment.number }}</h1>
                <Button variant="outline" @click="$inertia.get(route('salary-payments.index'))">
                    <ArrowLeft class="mr-1.5 h-4 w-4" /> {{ t('general.back') }}
                </Button>
            </div>

            <div class="grid grid-cols-1 gap-4 rounded-xl border border-border bg-card p-5 md:grid-cols-3">
                <div>
                    <p class="text-xs text-muted-foreground">{{ t('hr.employee') }}</p>
                    <p class="font-medium">{{ payment.employee_name }}</p>
                    <p class="text-xs text-muted-foreground">{{ payment.employee_code }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">{{ t('general.date') }}</p>
                    <p class="font-medium">{{ payment.date }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">{{ t('general.amount') }}</p>
                    <p class="font-medium">{{ money(payment.amount) }} {{ payment.currency_code }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">{{ t('hr.paid_from') }}</p>
                    <p class="font-medium">{{ payment.bank_account_name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">{{ t('hr.payment_mode') }}</p>
                    <p class="font-medium">{{ payment.payment_mode_label }}</p>
                </div>
                <div v-if="payment.cheque_no">
                    <p class="text-xs text-muted-foreground">{{ t('hr.cheque_no') }}</p>
                    <p class="font-medium">{{ payment.cheque_no }}</p>
                </div>
                <div v-if="payment.narration" class="md:col-span-3">
                    <p class="text-xs text-muted-foreground">{{ t('hr.narration') }}</p>
                    <p>{{ payment.narration }}</p>
                </div>
            </div>

            <!-- Which payslips this money actually relieved. -->
            <div class="rounded-xl border border-border bg-card">
                <div class="border-b border-border px-5 py-3">
                    <h2 class="text-base font-semibold">{{ t('hr.settled_payslips') }}</h2>
                </div>

                <div v-if="!lines.length" class="px-5 py-8 text-center text-sm text-muted-foreground">
                    {{ t('hr.paid_on_account') }}
                </div>

                <table v-else class="w-full text-sm">
                    <thead class="bg-muted/50 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 text-start">{{ t('hr.period') }}</th>
                            <th class="px-4 py-2 text-start">{{ t('hr.payroll') }}</th>
                            <th class="px-4 py-2 text-end">{{ t('general.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="line in lines" :key="line.id" class="border-t border-border">
                            <td class="px-4 py-2">{{ line.period_label ?? '—' }}</td>
                            <td class="px-4 py-2">
                                <span v-if="line.payroll_number">#{{ line.payroll_number }}</span>
                                <span v-else>—</span>
                            </td>
                            <td class="px-4 py-2 text-end font-medium">{{ money(line.amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
