<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextDatePicker from '@/Components/next/NextDatePicker.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import { Button } from '@/Components/ui/button';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const props = defineProps({
    filterOptions: { type: Object, default: () => ({}) },
    nextNumber: { type: [String, Number], default: null },
});

const { t } = useI18n();

const currencies = computed(() => props.filterOptions?.currencies || []);
const bankAccounts = computed(() => props.filterOptions?.bankAccounts || []);

const form = useForm({
    employee_id: null,
    date: '',
    currency_id: null,
    rate: 1,
    amount: null,
    bank_account_id: null,
    cheque_no: '',
    narration: '',
    allocations: [],

    selected_employee: null,
    selected_currency: null,
    selected_account: null,
});

/**
 * What this employee is still owed.
 *
 * Read from the ledger rather than from a cached paid_amount, so the form
 * offers what is genuinely open even if the two ever disagree.
 */
const openItems = ref([]);
const loadingOpenItems = ref(false);

const loadOpenItems = async () => {
    if (!form.employee_id) {
        openItems.value = [];
        return;
    }

    loadingOpenItems.value = true;

    try {
        const { data } = await axios.get(route('salary-payments.open-payslips'), {
            params: { employee_id: form.employee_id, currency_id: form.currency_id },
        });
        openItems.value = data.open_items ?? [];
    } catch (error) {
        openItems.value = [];
        toast.error(t('general.error'));
    } finally {
        loadingOpenItems.value = false;
    }
};

watch(() => [form.employee_id, form.currency_id], loadOpenItems);

const totalOpen = computed(() => openItems.value.reduce(
    (sum, item) => sum + Number(item.remaining_amount ?? 0), 0
));

// Manual allocation is not an optional extra: "this is for Asad" is a thing
// employers say, and silently applying it to an older month produces a payslip
// history nobody recognises.
const manualAllocation = ref(false);
const allocationAmounts = ref({});

const allocatedTotal = computed(() => Object.values(allocationAmounts.value)
    .reduce((sum, value) => sum + Number(value || 0), 0));

const payAll = () => {
    form.amount = Number(totalOpen.value.toFixed(4));
};

const money = (value) => Number(value ?? 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const submit = () => {
    form.transform((data) => {
        const payload = Object.fromEntries(
            Object.entries(data).filter(([k]) => !k.startsWith('selected_'))
        );

        payload.allocations = manualAllocation.value
            ? Object.entries(allocationAmounts.value)
                .filter(([, amount]) => Number(amount) > 0)
                .map(([target_line_id, amount]) => ({ target_line_id, amount: Number(amount) }))
            // Empty means FIFO across whatever is open — the right default for
            // "pay this person their salary".
            : [];

        return payload;
    });

    form.post(route('salary-payments.store'), {
        onError: () => toast.error(t('general.error')),
    });
};
</script>

<template>
    <AppLayout :title="t('general.create', { name: t('hr.salary_payment') })">
        <form @submit.prevent="submit" class="mx-auto max-w-5xl space-y-5">
            <div class="flex items-baseline justify-between">
                <h1 class="text-xl font-semibold">{{ t('general.create', { name: t('hr.salary_payment') }) }}</h1>
                <span v-if="nextNumber" class="text-sm text-muted-foreground">#{{ nextNumber }}</span>
            </div>

            <div class="rounded-xl border border-border bg-card p-5">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <NextSelect
                        v-model="form.selected_employee"
                        @update:modelValue="(v) => { form.employee_id = v?.id ?? null }"
                        resource-type="employees"
                        label-key="name" value-key="id" :reduce="(x) => x"
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
                    <div class="md:col-span-2">
                        <NextTextarea :label="t('hr.narration')" v-model="form.narration" :error="form.errors?.narration" />
                    </div>
                </div>
            </div>

            <!-- What is outstanding -->
            <div class="rounded-xl border border-border bg-card">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-3">
                    <div>
                        <h2 class="text-base font-semibold">{{ t('hr.outstanding_payslips') }}</h2>
                        <p class="text-xs text-muted-foreground">
                            {{ t('hr.total_outstanding') }}: <strong>{{ money(totalOpen) }}</strong>
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" v-model="manualAllocation" class="h-4 w-4 rounded border-border text-primary" />
                            {{ t('hr.choose_payslips') }}
                        </label>
                        <Button type="button" size="sm" variant="outline" :disabled="!openItems.length" @click="payAll">
                            {{ t('hr.pay_all') }}
                        </Button>
                    </div>
                </div>

                <div v-if="loadingOpenItems" class="px-5 py-8 text-center text-sm text-muted-foreground">
                    {{ t('general.loading') }}
                </div>
                <div v-else-if="!openItems.length" class="px-5 py-8 text-center text-sm text-muted-foreground">
                    {{ form.employee_id ? t('hr.nothing_outstanding') : t('hr.choose_an_employee') }}
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead class="bg-muted/50 text-xs uppercase text-muted-foreground">
                            <tr>
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

                <p v-if="form.errors?.allocations" class="border-t border-border px-5 py-2 text-xs text-destructive">
                    {{ form.errors.allocations }}
                </p>
            </div>

            <div class="flex justify-end gap-3">
                <Button type="button" variant="outline" @click="$inertia.get(route('salary-payments.index'))">
                    {{ t('general.cancel') }}
                </Button>
                <Button type="submit" :disabled="form.processing">{{ t('general.create') }}</Button>
            </div>
        </form>
    </AppLayout>
</template>
