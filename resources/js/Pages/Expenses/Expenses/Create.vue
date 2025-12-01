<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import NextDate from '@/Components/next/NextDatePicker.vue';
import { useI18n } from 'vue-i18n';
import { useSidebar } from '@/Components/ui/sidebar/utils';
import { useToast } from '@/Components/ui/toast/use-toast';
import { Trash2, Plus, Upload } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Spinner } from '@/components/ui/spinner';

const { t } = useI18n();
const { toast } = useToast();

const props = defineProps({
    categories: { type: Object, required: true },
    expenseAccounts: { type: Array, required: true },
    bankAccounts: { type: Array, required: true },
    currencies: { type: Array, required: true },
    homeCurrency: { type: Object, required: true },
});

console.log('this is homeCurrency', props.homeCurrency);

const form = useForm({
    date: '',
    category_id: '',
    expense_account_id: '',
    bank_account_id: '',
    currency_id: '',
    rate: 1,
    remarks: '',
    attachment: null,
    details: [
        { amount: '', title: '' },
    ],
    // For UI state
    selected_category: null,
    selected_expense_account: null,
    selected_bank_account: null,
    selected_currency: null,
});

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
    return form.details.reduce((sum, d) => sum + (Number(d.amount) || 0), 0);
});

const baseTotal = computed(() => {
    return total.value * (Number(form.rate) || 1);
});

// Add new detail line
const addDetailLine = () => {
    form.details.push({ amount: '', title: '' });
};

// Remove detail line
const removeDetailLine = (index) => {
    if (form.details.length > 1) {
        form.details.splice(index, 1);
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

// Submit form
const handleSubmit = (createAndNew = false) => {
    // Validate at least one detail
    const validDetails = form.details.filter(d => d.title && d.amount);
    if (validDetails.length === 0) {
        toast({
            title: t('expense.error'),
            description: t('expense.at_least_one_detail'),
            variant: 'destructive',
        });
        return;
    }

    const formData = new FormData();
    formData.append('date', form.date);
    formData.append('category_id', form.category_id);
    formData.append('expense_account_id', form.expense_account_id);
    formData.append('bank_account_id', form.bank_account_id);
    formData.append('currency_id', form.currency_id);
    formData.append('rate', form.rate);
    formData.append('remarks', form.remarks || '');

    if (form.attachment) {
        formData.append('attachment', form.attachment);
    }

    validDetails.forEach((detail, index) => {
        formData.append(`details[${index}][amount]`, detail.amount);
        formData.append(`details[${index}][title]`, detail.title);
    });

    if (createAndNew) {
        formData.append('create_and_new', '1');
    }

    form.post(route('expenses.store'), {
        data: formData,
        forceFormData: true,
        onSuccess: () => {
            toast({
                title: t('general.success'),
                description: t('expense.created_successfully'),
                class: 'bg-green-600 text-white',
            });
            if (createAndNew) {
                form.reset();
                form.details = [{ amount: '', title: '' }];
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
                description: t('expense.create_error'),
                variant: 'destructive',
            });
        },
    });
};

// Sidebar collapse behavior
let sidebar = null;
try {
    sidebar = useSidebar();
} catch (e) {
    sidebar = null;
}
const prevSidebarOpen = ref(true);

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
        <form @submit.prevent="handleSubmit(false)">
            <!-- General Section -->
            <div class="mb-5 rounded-xl border border-violet-500 p-4 shadow-sm relative">
                <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-violet-500">
                    {{ t('general.general_info') }}
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                    <NextDate
                        v-model="form.date"
                        :current-date="true"
                        :error="form.errors?.date"
                        :label="t('general.date')"
                    />
                    <NextSelect
                        :options="categories.data || categories"
                        v-model="form.selected_category"
                        @update:modelValue="(val) => { form.category_id = val?.id || '' }"
                        label-key="name"
                        value-key="id"
                        :reduce="cat => cat"
                        :floating-text="t('expense.category')"
                        :error="form.errors?.category_id"
                        :searchable="true"
                    />
                    <NextSelect
                        :options="expenseAccounts"
                        v-model="form.selected_expense_account"
                        @update:modelValue="(val) => { form.expense_account_id = val?.id || '' }"
                        label-key="name"
                        value-key="id"
                        :reduce="acc => acc"
                        :floating-text="t('expense.expense_account')"
                        :error="form.errors?.expense_account_id"
                        :searchable="true"
                    />
                    <NextSelect
                        :options="bankAccounts"
                        v-model="form.selected_bank_account"
                        @update:modelValue="(val) => { form.bank_account_id = val?.id || '' }"
                        label-key="name"
                        value-key="id"
                        :reduce="acc => acc"
                        :floating-text="t('expense.bank_account')"
                        :error="form.errors?.bank_account_id"
                        :searchable="true"
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
                </div>

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

            <!-- Expense Details Section -->
            <div class="rounded-xl border bg-card shadow-sm overflow-x-auto">
                <div class="p-4 border-b flex justify-between items-center">
                    <h3 class="font-semibold text-violet-500">{{ t('expense.detail_lines') }}</h3>
                    <Button type="button" variant="outline" size="sm" @click="addDetailLine">
                        <Plus class="w-4 h-4 mr-1" />
                        {{ t('general.add_more') }}
                    </Button>
                </div>
                <table class="w-full min-w-[600px]">
                    <thead class="bg-muted/50">
                        <tr class="text-sm text-muted-foreground">
                            <th class="px-4 py-2 w-12">#</th>
                            <th class="px-4 py-2 text-left">{{ t('expense.title') }} *</th>
                            <th class="px-4 py-2 w-40 text-right">{{ t('general.amount') }} *</th>
                            <th class="px-4 py-2 w-12"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(detail, index) in form.details" :key="index" class="border-t hover:bg-muted/30">
                            <td class="px-4 py-2 text-center text-muted-foreground">{{ index + 1 }}</td>
                            <td class="px-4 py-2">
                                <NextInput
                                    v-model="detail.title"
                                    :placeholder="t('expense.enter_title')"
                                    :error="form.errors?.[`details.${index}.title`]"
                                />
                            </td>
                            <td class="px-4 py-2">
                                <NextInput
                                    v-model="detail.amount"
                                    type="number"
                                    step="any"
                                    :placeholder="t('general.amount')"
                                    :error="form.errors?.[`details.${index}.amount`]"
                                    class="text-right"
                                />
                            </td>

                            <td class="px-4 py-2 text-center">
                                <button
                                    type="button"
                                    @click="removeDetailLine(index)"
                                    :disabled="form.details.length <= 1"
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
                            <td class="px-4 py-3 text-right">
                                {{ form.selected_currency?.symbol || '' }} {{ total.toLocaleString() }}
                            </td>
                            <td colspan="2"></td>
                        </tr>
                        <tr v-if="form.rate !== 1" class="font-semibold text-sm">
                            <td colspan="2" class="px-4 py-2 text-right text-muted-foreground">
                                {{ t('expense.base_currency_total') }}:
                            </td>
                            <td class="px-4 py-2 text-right text-muted-foreground">
                                {{ baseTotal.toLocaleString() }}
                            </td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Action Buttons -->
            <div class="mt-6 flex gap-3">
                <Button
                    type="submit"
                    :disabled="form.processing"
                    class="bg-primary"
                >
                    {{ t('general.create') }}
                    <Spinner v-if="form.processing" class="ml-2" />
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="form.processing"
                    @click="handleSubmit(true)"
                >
                    {{ t('general.create') }} & {{ t('general.new') }}
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    @click="$inertia.visit(route('expenses.index'))"
                >
                    {{ t('general.cancel') }}
                </Button>
            </div>
        </form>
    </AppLayout>
</template>

