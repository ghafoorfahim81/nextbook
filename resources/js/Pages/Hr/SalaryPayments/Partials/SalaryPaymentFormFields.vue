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
    /** Assigned by the server, shown read-only so the payment can be referred to. */
    number: { type: [String, Number], default: null },
    openItems: { type: Array, default: () => [] },
    loadingOpenItems: { type: Boolean, default: false },
    manualAllocation: { type: Boolean, default: false },
    allocationAmounts: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:manualAllocation', 'pay-all']);

const currencies = computed(() => props.filterOptions?.currencies || []);
const bankAccounts = computed(() => props.filterOptions?.bankAccounts || []);

const totalOpen = computed(() => props.openItems.reduce(
    (sum, item) => sum + Number(item.remaining_amount ?? 0), 0
));

const allocatedTotal = computed(() => Object.values(props.allocationAmounts)
    .reduce((sum, value) => sum + Number(value || 0), 0));

const money = (value) => Number(value ?? 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});
</script>

<template>
    <!-- The payment itself -->
    <fieldset class="relative mb-5 rounded-xl border border-violet-500 p-4 shadow-sm">
        <legend class="px-2 text-sm font-semibold text-violet-500">{{ t('hr.salary_payment') }}</legend>

        <div class="mt-2 grid grid-cols-1 gap-4 md:grid-cols-3">
            <NextSelect
                :options="[]"
                v-model="form.selected_employee"
                @update:modelValue="(v) => { form.employee_id = v?.id ?? null }"
                label-key="name" value-key="id" :reduce="(x) => x"
                :searchable="true" resource-type="employees" :search-fields="['full_name', 'code']"
                :floating-text="t('hr.employee')" :error="form.errors?.employee_id"
            />

            <NextDatePicker :label="t('general.date')" v-model="form.date" :error="form.errors?.date" />

            <NextSelect
                :options="currencies" v-model="form.selected_currency"
                @update:modelValue="(v) => { form.currency_id = v?.id ?? null }"
                label-key="code" value-key="id" :reduce="(x) => x"
                :floating-text="t('general.currency')" :error="form.errors?.currency_id"
            />

            <NextInput :label="t('general.rate')" type="number" step="any" v-model="form.rate" :error="form.errors?.rate" />
            <NextInput :label="t('general.amount')" type="number" step="any" v-model="form.amount" :error="form.errors?.amount" />

            <NextSelect
                :options="bankAccounts" v-model="form.selected_account"
                @update:modelValue="(v) => { form.bank_account_id = v?.id ?? null }"
                label-key="name" value-key="id" :reduce="(x) => x"
                :floating-text="t('hr.paid_from')" :error="form.errors?.bank_account_id"
            />

            <NextInput :label="t('hr.cheque_no')" v-model="form.cheque_no" :error="form.errors?.cheque_no" />

            <NextInput
                v-if="number"
                :label="t('general.number')"
                :model-value="String(number)"
                disabled
            />

            <div class="md:col-span-3">
                <NextTextarea :label="t('hr.narration')" v-model="form.narration" :error="form.errors?.narration" />
            </div>
        </div>
    </fieldset>

    <!-- What is outstanding -->
    <fieldset class="relative mb-5 rounded-xl border border-violet-500 p-4 shadow-sm">
        <legend class="px-2 text-sm font-semibold text-violet-500">{{ t('hr.outstanding_payslips') }}</legend>

        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-muted-foreground">
                {{ t('hr.total_outstanding') }}: <strong>{{ money(totalOpen) }}</strong>
            </p>
            <div class="flex items-center gap-3">
                <label class="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        :checked="manualAllocation"
                        class="h-4 w-4 rounded border-border text-primary"
                        @change="emit('update:manualAllocation', $event.target.checked)"
                    />
                    {{ t('hr.choose_payslips') }}
                </label>
                <Button type="button" size="sm" variant="outline" :disabled="!openItems.length" @click="emit('pay-all')">
                    {{ t('hr.pay_all') }}
                </Button>
            </div>
        </div>

        <div v-if="loadingOpenItems" class="rounded-xl border border-border py-8 text-center text-sm text-muted-foreground">
            {{ t('general.loading') }}
        </div>
        <div v-else-if="!openItems.length" class="rounded-xl border border-border py-8 text-center text-sm text-muted-foreground">
            {{ form.employee_id ? t('hr.nothing_outstanding') : t('hr.choose_an_employee') }}
        </div>

        <div v-else class="overflow-x-auto rounded-xl border border-border bg-card shadow-sm">
            <table class="w-full min-w-[720px] text-sm">
                <thead class="bg-muted/50 text-xs uppercase">
                    <tr class="font-semibold text-violet-500">
                        <th class="px-4 py-2 text-start">{{ t('hr.period') }}</th>
                        <th class="px-4 py-2 text-start">{{ t('hr.document') }}</th>
                        <th class="px-4 py-2 text-start">{{ t('general.date') }}</th>
                        <th class="px-4 py-2 text-end">{{ t('general.amount') }}</th>
                        <th class="px-4 py-2 text-end">{{ t('hr.outstanding') }}</th>
                        <th v-if="manualAllocation" class="px-4 py-2 text-end">{{ t('hr.allocate') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in openItems" :key="item.target_line_id" class="border-t border-border">
                        <td class="px-4 py-2">{{ item.period_label ?? '—' }}</td>
                        <td class="px-4 py-2">
                            <span v-if="item.payroll_number">#{{ item.payroll_number }}</span>
                            <span v-else class="text-muted-foreground">{{ item.document_number ?? '—' }}</span>
                        </td>
                        <td class="px-4 py-2">{{ item.date }}</td>
                        <td class="px-4 py-2 text-end">{{ money(item.original_amount) }}</td>
                        <td class="px-4 py-2 text-end font-medium">{{ money(item.remaining_amount) }}</td>
                        <td v-if="manualAllocation" class="px-4 py-2 text-end">
                            <input
                                type="number" step="any"
                                v-model.number="allocationAmounts[item.target_line_id]"
                                :max="item.remaining_amount"
                                class="w-28 rounded border border-border bg-background px-2 py-1 text-end"
                            />
                        </td>
                    </tr>
                </tbody>
                <tfoot v-if="manualAllocation">
                    <tr class="border-t border-border bg-muted/30 font-medium">
                        <td class="px-4 py-2" colspan="4">{{ t('hr.allocated') }}</td>
                        <td class="px-4 py-2"></td>
                        <td class="px-4 py-2 text-end">{{ money(allocatedTotal) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <p v-if="form.errors?.allocations" class="mt-2 text-xs text-destructive">
            {{ form.errors.allocations }}
        </p>
    </fieldset>
</template>
