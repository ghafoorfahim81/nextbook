<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import NextDate from '@/Components/next/NextDatePicker.vue';
import SubmitButtons from '@/Components/SubmitButtons.vue';
import ModuleHelpButton from '@/Components/ModuleHelpButton.vue'
import { useI18n } from 'vue-i18n';
import { useSidebar } from '@/Components/ui/sidebar/utils';
import { Trash2, Plus, Upload } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
const { t } = useI18n();

const props = defineProps({
    accounts: { type: Object, required: true },
    ledgers: { type: Array, required: true },
    currencies: { type: Array, required: true },
    homeCurrency: { type: Object, required: true },
    journalClasses: { type: Array, required: true },
});

const form = useForm({
    date: '',
    number: '',
    currency_id: '',
    rate: 1,
    remarks: '',
    lines: [
        {
            account_id: '',
            selected_account:null,
            debit: '',
            credit: '',
            remark: '',
            ledger_id: '',
            selected_ledger:null, 
            journal_class_id: '',
            selected_journal_class:null,
        },
        {
            account_id: '',
            selected_account:null,
            debit: '',
            credit: '',
            remark: '',
            ledger_id: '',
            selected_ledger:null,
            journal_class_id: '',
            selected_journal_class:null,
        },
        {
            account_id: '',
            selected_account:null,
            debit: '',
            credit: '',
            remark: '',
            ledger_id: '',
            selected_ledger:null,
            journal_class_id: '',
            selected_journal_class:null,
        },

    ],
});

const submitAction = ref(null);
const createLoading = computed(() => form.processing && submitAction.value === 'create');
const createAndNewLoading = computed(() => form.processing && submitAction.value === 'create_and_new');

const handleSubmitAction = (createAndNew = false) => {
    const isCreateAndNew = createAndNew === true;
    submitAction.value = isCreateAndNew ? 'create_and_new' : 'create';
    handleSubmit(isCreateAndNew);
};

const fileInput = ref(null);
const attachmentPreview = ref(null);

// Set default currency
watch(() => props.currencies, (currencies) => {
    if (currencies && !form.currency_id) {
        const baseCurrency = props.homeCurrency;
        if (baseCurrency) {
            form.selected_currency = baseCurrency;
            form.currency_id = baseCurrency.id;
            form.rate = baseCurrency.exchange_rate || 1;
        }
    }
}, { immediate: true });

// Update rate when currency changes
const handleCurrencyChange = (currency) => {
    form.selected_currency = currency;
    form.currency_id = currency?.id || '';
    form.rate = currency?.exchange_rate || 1;
};

// Calculate total
const total = computed(() => {
    return form.lines.reduce((sum, d) => sum + (Number(d.debit) || 0), 0);
});

const baseTotal = computed(() => {
    return total.value * (Number(form.rate) || 1);
});

// Add new detail line
const addLine = () => {
    form.lines.push({ account_id: '', debit: '', credit: '', remark: '', ledger_id: '', journal_class_id: '' });
};

// Remove detail line
const removeLine = (index) => {
    if (form.lines.length > 1) {
        form.lines.splice(index, 1);
    }
};

// Handle file upload
const handleFileChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        form.attachment = file;
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                attachmentPreview.value = e.target.result;
            };
            reader.readAsDataURL(file);
        } else {
            attachmentPreview.value = null;
        }
    }
};

const removeAttachment = () => {
    form.attachment = null;
    attachmentPreview.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const normalize = () => {
    return form.lines.filter(d =>
        d.account_id &&
        ((Number(d.debit) > 0) || (Number(d.credit) > 0))
    );
}
// Submit form
const handleSubmit = (createAndNew = false) => {
    // Validate at least one detail
    const validDetails = normalize();
    if (validDetails.length === 0) {
        toast.error(t('account.detail_lines'), {
            description: t('account.at_least_one_detail'),
            class: 'bg-red-600',
        });
        return;
    }

    if (totalDebit.value !== totalCredit.value) {
        toast.error(t('account.equality_error'), {
            description: t('account.total_debit_and_credit_must_be_equal_description'),
            class: 'bg-red-600',
        });
        return;
    }

    form.lines = normalize();
    const formData = new FormData();
    formData.append('date', form.date);
    formData.append('currency_id', form.currency_id);
    formData.append('rate', form.rate);
    formData.append('remarks', form.remarks || '');

    if (form.attachment) {
        formData.append('attachment', form.attachment);
    }

    validDetails.forEach((detail, index) => {
        formData.append(`lines[${index}][account_id]`, detail.account_id);
        formData.append(`lines[${index}][debit]`, detail.debit);
        formData.append(`lines[${index}][credit]`, detail.credit);
        formData.append(`lines[${index}][remark]`, detail.remark);
        formData.append(`lines[${index}][ledger_id]`, detail.ledger_id);
        formData.append(`lines[${index}][journal_class_id]`, detail.journal_class_id);
    });

    if (createAndNew) {
        formData.append('create_and_new', '1');
    }

    form.post(route('journal-entries.store'), {
        data: formData,
        forceFormData: true,
        onSuccess: () => {
            toast.success(t('general.success'), {
                description: t('general.create_success', { name: t('journal_entry.journal_entry') }),
                class: 'bg-green-600',
            });
            if (createAndNew) {
                form.reset();
                form.lines = [{ account_id: '', debit: '', credit: '', remark: '', ledger_id: '', journal_class_id: '' }];
                attachmentPreview.value = null;
                // Re-set default currency
                if (props.currencies) {
                    const baseCurrency = props.currencies.find(c => c.is_base_currency);
                    if (baseCurrency) {
                        form.selected_currency = baseCurrency;
                        form.currency_id = baseCurrency.id;
                        form.rate = baseCurrency.exchange_rate || 1;
                    }
                }
            }
        },
        onError: () => {
            toast({
                title: t('general.error'),
                description: t('journal_entry.create_error'),
                variant: 'destructive',
            });
        },
    });
};

const totalCredit = computed(() => {
    return form.lines.reduce((sum, d) => sum + (Number(d.credit) || 0), 0);
});

const totalDebit = computed(() => {
    return form.lines.reduce((sum, d) => sum + (Number(d.debit) || 0), 0);
});

// Sidebar collapse behavior
let sidebar = null;
    try {
        sidebar = useSidebar();
    } catch (e) {
        sidebar = null;
    }
    const prevSidebarOpen = ref(true);

const handleAmountChange = (index, type) => {
    if (type === 'debit') {
        form.lines[index].credit = 0;
    } else {
        form.lines[index].debit = 0;
    }
};
onMounted(() => {
    if (sidebar) {
        prevSidebarOpen.value = sidebar.open.value;
        sidebar.setOpen(false);
    }
});

onUnmounted(() => {
    if (sidebar) {
        sidebar.setOpen(prevSidebarOpen.value);
    }
});
</script>

<template>
    <AppLayout :title="t('general.create', { name: t('expense.expense') })" :sidebar-collapsed="true">
        <form @submit.prevent="handleSubmitAction(false)">
            <!-- General Section -->
            <div class="mb-5 rounded-xl border border-violet-500 p-4 shadow-sm relative">
                <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-violet-500">
                    {{ t('general.general_info') }}
                </div>
                <ModuleHelpButton module="journal_entry" />
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                    <NextDate
                        v-model="form.date"
                        :current-date="true"
                        :error="form.errors?.date"
                        :label="t('general.date')"
                    />
                    <NextInput
                        v-model="form.number"
                        :label="t('general.number')"
                        :error="form.errors?.number"
                    />
                    <div class="grid grid-cols-2 gap-2">
                        <NextSelect
                            :options="currencies.data || currencies"
                            v-model="form.selected_currency"
                            @update:modelValue="handleCurrencyChange"
                            label-key="code"
                            value-key="id"
                            :reduce="cur => cur"
                            :floating-text="t('admin.currency.currency')"
                            :error="form.errors?.currency_id"
                            :searchable="true"
                        />
                        <NextInput
                            v-model="form.rate"
                            type="number"
                            step="any"
                            :label="t('general.rate')"
                            :error="form.errors?.rate"
                        />
                    </div>
                    <NextTextarea
                        v-model="form.remarks"
                        :label="t('general.remarks')"
                        :error="form.errors?.remarks"
                        rows="2"
                    />

                <!-- Attachment -->
                <div class="mt-4">
                    <label class="block text-sm font-medium mb-2">{{ t('general.attachment') }}</label>
                    <div class="flex items-center gap-4">
                        <input
                            ref="fileInput"
                            type="file"
                            @change="handleFileChange"
                            class="hidden"
                            accept="image/*,.pdf,.doc,.docx"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            @click="() => fileInput?.click()"
                        >
                            <Upload class="w-4 h-4 mr-2" />
                            {{ t('general.upload_file') }}
                        </Button>
                        <span v-if="form.attachment" class="text-sm text-muted-foreground">
                            {{ form.attachment.name }}
                            <button
                                type="button"
                                @click="removeAttachment"
                                class="ml-2 text-red-500 hover:text-red-700"
                            >
                                <Trash2 class="w-4 h-4 inline" />
                            </button>
                        </span>
                    </div>
                    <img
                        v-if="attachmentPreview"
                        :src="attachmentPreview"
                        class="mt-2 max-h-32 rounded border"
                    />
                </div>
                </div>

            </div>

            <!-- Expense Details Section -->
            <div class="rounded-xl border bg-card shadow-sm overflow-x-auto mb-4">
                <div class="p-4 border-b flex justify-between items-center">
                    <h3 class="font-semibold text-violet-500">{{ t('account.detail_lines') }}</h3>

                </div>
                <table class="w-full min-w-[600px]">
                    <thead class="bg-muted/50">
                        <tr class="text-sm text-muted-foreground rtl:text-right ltr:text-left">
                            <th class="px-4 py-2 w-12">#</th>
                            <th class="px-4 py-2 ">{{ t('account.account') }} *</th>
                            <th class="px-4 py-2 ">{{ t('general.debit') }} *</th>
                            <th class="px-4 py-2 ">{{ t('general.credit') }} *</th>
                            <th class="px-4 py-2 ">{{ t('general.remark') }} </th>
                            <th class="px-4 py-2 ">{{ t('general.ledger') }} </th>
                            <th class="px-4 py-2 ">{{ t('sidebar.journal_entry.journal_class') }} </th>
                            <th class="px-4 py-2 w-12"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(line, index) in form.lines" :key="index" class="border-t hover:bg-muted/30">
                            <td class="px-4 py-2 text-center text-muted-foreground">{{ index + 1 }}</td>
                            <td class="px-4 py-2 w-64">
                                <NextSelect
                                    :options="accounts.data || accounts"
                                    v-model="line.selected_account"
                                    @update:modelValue="(val) => { line.account_id = val?.id || null }"
                                    label-key="name"
                                    value-key="id"
                                    :reduce="acc => acc"
                                    :error="form.errors?.[`line.${index}.account_id`]"
                                    :searchable="true"
                                />
                            </td>
                            <td class="px-4 py-2 w-40">
                                <NextInput
                                    v-model="line.debit"
                                    type="number"
                                    step="any"
                                    @input="handleAmountChange(index, 'debit')"
                                    :placeholder="t('general.debit')"
                                    :error="form.errors?.[`line.${index}.debit`]"
                                    class="text-right"
                                />
                            </td>
                            <td class="px-4 py-2 w-40">
                                <NextInput
                                    v-model="line.credit"
                                    type="number"
                                    step="any"
                                    @input="handleAmountChange(index, 'credit')"
                                    :placeholder="t('general.credit')"
                                    :error="form.errors?.[`line.${index}.credit`]"
                                    class="text-right"
                                />
                            </td>
                            <td class="px-4 py-2 w-40">
                                <NextInput
                                    v-model="line.remark"
                                    :placeholder="t('general.remark')"
                                    :error="form.errors?.[`line.${index}.remark`]"
                                    class="text-right"
                                />
                            </td>
                            <td class="px-4 py-2 w-64">
                                <NextSelect
                                    :options="ledgers.data || ledgers"
                                        v-model="line.selected_ledger"
                                    @update:modelValue="(val) => { line.ledger_id = val?.id || '' }"
                                    label-key="name"
                                    value-key="id"
                                    :reduce="ledger => ledger"
                                    :error="form.errors?.[`line.${index}.ledger_id`]"
                                    :searchable="true"
                                />
                            </td>
                            <td class="px-4 py-2 w-40">
                                <NextSelect
                                    :options="journalClasses.data || journalClasses"
                                    v-model="line.selected_journal_class"
                                    @update:modelValue="(val) => { line.journal_class_id = val?.id || '' }"
                                    label-key="name"
                                    value-key="id"
                                    :reduce="journalClass => journalClass"
                                    :error="form.errors?.[`line.${index}.journal_class_id`]"
                                    :searchable="true"
                                />
                            </td>

                            <td class="px-4 py-2 text-center">
                                <button
                                    type="button"
                                    @click="removeLine(index)"
                                    :disabled="form.lines.length <= 1"
                                    class="text-red-500 hover:text-red-700 disabled:opacity-30 disabled:cursor-not-allowed"
                                >
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-violet-500/10">
                        <tr class="font-semibold">
                            <td colspan="2" class="px-4 py-3 text-right">{{ t('general.total') }}:</td>
                            <td class="px-4 py-3 text-center">
                                {{ totalDebit.toLocaleString() }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                {{ totalCredit.toLocaleString() }}
                            </td>
                            <td colspan="2"></td>
                            <td class="px-4 py-3">
                                <Button type="button" variant="outline" size="sm" class="btn btn-primary px-4 py-2 rounded-md bg-violet-500 hover:bg-violet-600 border text-white disabled:bg-gray-300"
                                @click="addLine">
                                   <div class="flex items-center gap-2">
                                    {{ t('general.add_more') }}
                                    <Plus class="w-4 h-4 mr-1" />
                                   </div>
                                </Button>
                            </td>
                        </tr>

                    </tfoot>
                </table>
            </div>

            <!-- Action Buttons -->
            <SubmitButtons
                :create-label="t('general.create')"
                :create-and-new-label="t('general.create_and_new')"
                :cancel-label="t('general.cancel')"
                :creating-label="t('general.creating', { name: t('expense.expense') })"
                :create-loading="createLoading"
                :create-and-new-loading="createAndNewLoading"
                @create-and-new="handleSubmitAction(true)"
                @cancel="() => $inertia.visit(route('expenses.index'))"
            />
        </form>
    </AppLayout>
</template>

