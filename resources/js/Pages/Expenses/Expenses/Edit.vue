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
    expense: { type: Object, required: true },
    categories: { type: Object, required: true },
    expenseAccounts: { type: Array, required: true },
    bankAccounts: { type: Array, required: true },
    currencies: { type: Array, required: true },
});
const expense = props.expense.data;
const form = useForm({
    date: expense.date || '',
    category_id: expense.category_id || '',
    expense_account_id: expense.expense_account_id || '',
    bank_account_id: expense.bank_account_id || '',
    currency_id: expense.currency_id || '',
    rate: expense.rate || 1,
    remarks: expense.remarks || '',
    attachment: null,
    details: expense.details?.length 
        ? expense.details.map(d => ({
            amount: d.amount,
            title: d.title,
            }))
        : [{ amount: '', title: '' }],
    // For UI state
    selected_category: expense.category,
    selected_expense_account: expense.expense_transaction.account,
    selected_bank_account: expense.bank_transaction.account,
    selected_currency: expense.bank_transaction.currency,
});

const fileInput = ref(null);
const attachmentPreview = ref(expense.attachment_url || null);
const existingAttachment = ref(expense.attachment || null);

// Initialize selected values
onMounted(() => {
    // Set category
    const categories = props.categories.data || props.categories;
    form.selected_category = categories.find(c => c.id === expense.category_id) || null;
    
    // Set expense account
    form.selected_expense_account = props.expenseAccounts.find(a => a.id === expense.expense_account_id) || null;
    
    // Set bank account
    form.selected_bank_account = props.bankAccounts.find(a => a.id === expense.bank_account_id) || null;
    
    // Set currency
    form.selected_currency = props.currencies.find(c => c.id === expense.currency_id) || null;
});

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
        existingAttachment.value = null;
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
    existingAttachment.value = null;
    attachmentPreview.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

// Submit form
const handleSubmit = () => {
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

    form.transform((data) => {
        const formData = {
            date: data.date,
            category_id: data.category_id,
            expense_account_id: data.expense_account_id,
            bank_account_id: data.bank_account_id,
            currency_id: data.currency_id,
            rate: data.rate,
            remarks: data.remarks || '',
            details: validDetails,
        };
        
        if (data.attachment) {
            formData.attachment = data.attachment;
        }
        
        return formData;
    }).put(route('expenses.update', expense.id), {
        forceFormData: true,
        onSuccess: () => {
            toast({
                title: t('general.success'),
                description: t('expense.updated_successfully'),
                class: 'bg-green-600 text-white',
            });
        },
        onError: () => {
            toast({
                title: t('general.error'),
                description: t('expense.update_error'),
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
    <AppLayout :title="t('general.edit', { name: t('expense.expense') })" :sidebar-collapsed="true">
        <form @submit.prevent="handleSubmit">
            <!-- General Section -->
            <div class="mb-5 rounded-xl border border-violet-500 p-4 shadow-sm relative">
                <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-violet-500">
                    {{ t('general.general_info') }}
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                    <NextDate 
                        v-model="form.date" 
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
                            {{ existingAttachment ? t('general.change_file') : t('general.upload_file') }}
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
                        <span v-else-if="existingAttachment" class="text-sm text-muted-foreground">
                            {{ t('general.current_file') }}
                            <a :href="props.expense.attachment_url" target="_blank" class="text-violet-600 hover:underline ml-1">
                                {{ t('general.view') }}
                            </a>
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
                    {{ t('general.update') }}
                    <Spinner v-if="form.processing" class="ml-2" />
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

