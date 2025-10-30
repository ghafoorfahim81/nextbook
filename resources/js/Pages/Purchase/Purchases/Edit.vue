<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { h, ref, watch, onMounted, onUnmounted, computed, nextTick } from 'vue';
import { useForm } from '@inertiajs/vue3';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import DiscountField from '@/Components/next/DiscountField.vue';
import PaymentDialog from '@/Components/next/PaymentDialog.vue';
import { useI18n } from 'vue-i18n';
import TransactionSummary from '@/Components/next/TransactionSummary.vue';
import DiscountSummary from '@/Components/next/DiscountSummary.vue';
import TaxSummary from '@/Components/next/TaxSummary.vue';
import { useSidebar } from '@/Components/ui/sidebar/utils';
import { ToastAction } from '@/Components/ui/toast'
import { useToast } from '@/Components/ui/toast/use-toast'
import NextDate from '@/Components/next/NextDatePicker.vue'
import { Trash2 } from 'lucide-vue-next';

const { t } = useI18n();
const { toast } = useToast()

const props = defineProps({
    purchase: {type: Object, required: true},
    ledgers: {type: Object, required: true},
    salePurchaseTypes: {type: Object, required: true},
    currencies: {type: Object, required: true},
    items: {type: Object, required: true},
    stores: {type: Object, required: true},
    unitMeasures: {type: Object, required: true},
    accounts: {type: Object, required: true},
    purchaseNumber: {type: String, required: true},
})

const purchase = props.purchase.data;
console.log('purchase', purchase);
const form = useForm({
    number: purchase.number || '',
    supplier_id: purchase.supplier_id || '',
    selected_ledger: purchase.supplier || '',
    selected_currency: purchase.transaction?.currency || '',
    date: purchase.date || '',
    selected_store: purchase.store || '',
    currency_id: purchase?.transaction?.currency_id || '',
    rate: purchase?.transaction?.rate || '',
    sale_purchase_type_id: purchase?.sale_purchase_type_id || '',
    selected_sale_purchase_type: purchase?.type || '',
    discount: purchase?.discount || '',
    discount_type: purchase?.discount_type || 'percentage',
    description: purchase?.description || '',
    store_id: purchase?.store_id || '',
    item_list: purchase?.item_list || [],
    payment: purchase?.payment || {
        method: '',
        amount: '',
        account_id: '',
        note: '',
    },
    status: purchase?.status || '',
    items: purchase?.item_list?.map(item => ({
        item_id: item.item_id,
        selected_item: props.items?.data?.find(i => i.id === item.item_id) || null,
        quantity: item.quantity,
        unit_measure_id: item.unit_measure_id,
        batch: item.batch || '',
        expire_date: '',
        unit_price: item.unit_price,
        selected_measure: props.unitMeasures?.data?.find(u => u.id === item.unit_measure_id) || null,
        item_discount: item.discount || '',
        free: item.free || '',
        tax: item.tax || '',
        base_unit_price: item.unit_price || 0,
        available_measures: [],
    })) || [],
})

// Set selected values based on existing data
onMounted(() => {
    // Initialize form with existing data
    if (props.purchase?.currency_id && props.currencies?.data) {
        const selectedCurrency = props.currencies.data.find(c => c.id === props.purchase.currency_id);
        if (selectedCurrency) {
            form.selected_currency = selectedCurrency;
        }
    }

    if (props.purchase?.supplier_id && props.ledgers?.data) {
        const selectedLedger = props.ledgers.data.find(l => l.id === props.purchase.supplier_id);
        if (selectedLedger) {
            form.selected_ledger = selectedLedger;
        }
    }

    if (props.purchase?.sale_purchase_type_id && props.salePurchaseTypes) {
        const selectedType = props.salePurchaseTypes.find(t => t.id === props.purchase.sale_purchase_type_id);
        if (selectedType) {
            form.selected_sale_purchase_type = selectedType;
        }
    }

    if (props.purchase?.store_id && props.stores?.data) {
        const selectedStore = props.stores.data.find(s => s.id === props.purchase.store_id);
        if (selectedStore) {
            form.selected_store = selectedStore;
        }
    }
    form.items.push({
        item_id: '',
        selected_item: '',
        quantity: '',
        unit_measure_id: '',
        batch: '',
        expire_date: '',
        unit_price: '',
    });
});

// Payment dialog state
const showPaymentDialog = ref(false);

// Watch for sale/purchase type changes and show payment dialog for credit transactions
watch(() => form.selected_sale_purchase_type, (newType) => {
    if (newType && newType.id === 'credit') {
        showPaymentDialog.value = true;
    }
});


let disabled = false;

const handleSelectChange = (field, value) => {
    if (field === 'currency_id') {
        form.rate = value.exchange_rate;
    }
    form[field] = value;
};

function handleSubmit() {
    if (form.items[0]?.selected_item === '' || form.items[0]?.selected_item === null) {
        toast({
            title: 'Please add items',
            description: 'Please add at least one item to update the purchase',
            variant: 'destructive',
            class: 'bg-yellow-600 text-white',
        });
        return;
    } else {
        const FormItems = form.items.filter(item => item.selected_item && item.item_id);
        form.item_list = FormItems.map(item => ({
            item_id: item.item_id,
            quantity: item.quantity,
            unit_price: item.unit_price,
            free: item.free || 0,
            batch: item.batch || '',
            discount: item.item_discount || 0,
            tax: item.tax || 0,
            unit_measure_id: item.selected_measure?.id || item.unit_measure_id,
        }));

        form.transaction_total = form.items.reduce((acc, item) =>
            acc + ((parseFloat(item.unit_price) || 0) * (parseFloat(item.quantity) || 0) - (parseFloat(item.item_discount) || 0) + (parseFloat(item.tax) || 0)), 0
        );
    }

    form.put(route('purchases.update', props.purchase.id), {
        onSuccess: () => {
            toast({
                title: 'Success',
                description: 'Purchase updated successfully',
                variant: 'success',
                class: 'bg-green-600 text-white',
            });
        },
        onError: () => {
            toast({
                title: 'Error updating purchase',
                description: 'Error updating purchase',
                variant: 'destructive',
                class: 'bg-pink-600 text-white',
            });
        }
    });
}

// Payment dialog handlers
const handlePaymentDialogConfirm = () => {
    showPaymentDialog.value = false;
};

const handlePaymentDialogCancel = () => {
    if (props.salePurchaseTypes) {
        const debitType = props.salePurchaseTypes.find(type => type.id === 'debit');
        if (debitType) {
            form.selected_sale_purchase_type = debitType;
        }
    }
    showPaymentDialog.value = false;
};

// Collapse sidebar while on this page, restore on leave (safe if provider missing)
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

// Recalculate item unit prices when currency rate changes
watch(() => form.rate, (newRate) => {
    if (!Array.isArray(form.items)) return;
    form.items.forEach((row) => {
        if (!row || !row.selected_item) return;
        const baseUnit = Number(row.selected_item?.unitMeasure?.unit) || 1;
        const selectedUnit = Number(row.selected_measure?.unit) || baseUnit;
        const baseUnitPrice = Number(row.base_unit_price ?? row.selected_item?.unit_price ?? row.selected_item?.purchase_price ?? 0);
        row.unit_price = (baseUnitPrice / (selectedUnit || 1)) * (Number(newRate) || 0);
    });
});

const handleItemChange = async (index, selectedItem) => {
    const row = form.items[index];
    if (!row || !selectedItem) {
        row.selected_item = null;
        row.available_measures = [];
        row.selected_measure = null;
        row.item_id = '';
        row.unit_price = '';
        row.quantity = '';
        row.batch = '';
        row.discount = '';
        row.free = '';
        row.tax = '';
        return;
    }

    // Set the selected item and item_id
    row.selected_item = selectedItem;
    row.item_id = selectedItem.id;

    // Build available measures
    const selUM = selectedItem?.unitMeasure || {};
    const selectedQuantityId = selUM.quantity_id ?? selUM.quantity?.id;
    const selectedQuantityName = (selUM.quantity?.name || selUM.quantity?.code || '').toString().toLowerCase();
    row.available_measures = (props.unitMeasures?.data || []).filter(unit => {
        const unitQtyId = unit?.quantity_id ?? unit?.quantity?.id;
        const unitQtyName = (unit?.quantity?.name || unit?.quantity?.code || '').toString().toLowerCase();
        return (selectedQuantityId && unitQtyId === selectedQuantityId) || (!!selectedQuantityName && unitQtyName === selectedQuantityName);
    });

    // Set default measure (first available or the item's current measure)
    if (row.available_measures.length > 0) {
        row.selected_measure = row.available_measures.find(u => u.id === row.unit_measure_id) || row.available_measures[0];
    }

    // Set the base unit price
    row.base_unit_price = selectedItem.unit_price ?? selectedItem.purchase_price ?? 0;

    // Set the initial unit_price based on the selected measure
    if (row.selected_measure) {
        const baseUnit = Number(selectedItem.unitMeasure?.unit) || 1;
        const selectedUnit = Number(row.selected_measure?.unit) || baseUnit;
        row.unit_price = (row.base_unit_price / baseUnit) * selectedUnit * (form.rate || 1);
    }

    // Add a new empty row only when selecting into the last row
    if (index === form.items.length - 1) {
        addRow();
    }
};

const isRowEnabled = (index) => {
    if (!form.selected_ledger) return false;
    for (let i = 0; i < index; i++) {
        if (!form.items[i]?.selected_item) return false;
    }
    return true;
};

const addRow = () => {
    form.items.push({
        item_id: '',
        selected_item: '',
        quantity: '',
        unit_measure_id: '',
        batch: '',
        expire_date: '',
        unit_price: '',
        base_unit_price: '',
        selected_measure: '',
        item_discount: '',
        free: '',
        tax: '',
    });
};

const deleteRow = (index) => {
    if (form.items.length === 1) return;
    form.items.splice(index, 1);
};

const toNum = (v, d = 0) => {
    const n = Number(v);
    return isNaN(n) ? d : n;
};

const rowTotal = (index) => {
    const item = form.items[index];
    if (!item || !item.selected_item) return '';
    const qty = toNum(item.quantity, 0);
    const price = toNum(item.unit_price, 0);
    const disc = toNum(item.item_discount, 0);
    const tax = toNum(item.tax, 0);
    return qty * price - disc + tax;
};

// Bill discount currency and percent
const billDiscountCurrency = computed(() => {
    const billDisc = toNum(form.discount, 0)
    if (form.discount_type === 'percentage') {
        return goodsTotal.value * (billDisc / 100)
    }
    return billDisc
})
const totalRows = computed(() => form.items.length);
const totalItemDiscount = computed(() => form.items.reduce((acc, item) => acc + toNum(item.item_discount, 0), 0));
const totalTax = computed(() => form.items.reduce((acc, item) => acc + toNum(item.tax, 0), 0));
const goodsTotal = computed(() => form.items.reduce((acc, item) => acc + (toNum(item.unit_price, 0) * toNum(item.quantity, 0)), 0));
const totalQuantity = computed(() => form.items.reduce((acc, item) => acc + toNum(item.quantity, 0), 0));
const totalFree = computed(() => form.items.reduce((acc, item) => acc + toNum(item.free, 0), 0));
const totalRowTotal = computed(() => form.items.reduce((acc, item) => acc + (toNum(item.unit_price, 0) * toNum(item.quantity, 0) - toNum(item.item_discount, 0) + toNum(item.tax, 0)), 0));

// Transaction summary
const transactionSummary = computed(() => {
    const paid = toNum(form.payment.amount, 0);
    const oldBalance = toNum(form?.selected_ledger?.statement?.balance, 0);
    const nature = form?.selected_ledger?.statement?.balance_nature;
    const hasSelectedItem = Array.isArray(form.items) && form.items.some(r => !!r.selected_item);
    const netAmount = goodsTotal.value - (form.discount_type === 'percentage' ? goodsTotal.value * (toNum(form.discount, 0) / 100) : toNum(form.discount, 0)) + totalTax.value;
    const grandTotal = netAmount - paid;
    const balance = hasSelectedItem
        ? (nature === 'dr' ? (grandTotal + oldBalance) : (grandTotal - oldBalance))
        : 0;
    return {
        valueOfGoods: goodsTotal.value,
        billDiscount: form.discount_type === 'percentage' ? goodsTotal.value * (toNum(form.discount, 0) / 100) : toNum(form.discount, 0),
        itemDiscount: totalItemDiscount.value,
        billDiscountPercent: form.discount_type === 'percentage' ? toNum(form.discount, 0) : 0,
        cashReceived: paid,
        balance: balance,
        grandTotal: grandTotal,
        oldBalance: oldBalance,
        balanceNature: nature,
        currencySymbol: form.selected_currency?.symbol,
    };
});
</script>

<template>
    <AppLayout :title="t('general.edit', { name: t('purchase.purchase') })" :sidebar-collapsed="true">
        <form @submit.prevent="handleSubmit">
            <div class="mb-5 rounded-xl border border-violet-500 p-4 shadow-sm relative">
                <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
                    {{ t('general.edit', { name: t('purchase.purchase') }) }}
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                    <NextSelect
                        :options="ledgers?.data || ledgers || []"
                        v-model="form.selected_ledger"
                        @update:modelValue="(value) => handleSelectChange('supplier_id', value.id)"
                        label-key="name"
                        value-key="id"
                        :reduce="ledger => ledger"
                        :floating-text="t('ledger.supplier.supplier')"
                        :error="form.errors?.supplier_id"
                        :searchable="true"
                        resource-type="ledgers"
                        :search-fields="['name', 'email', 'phone_no']"
                        :search-options="{ type: 'supplier' }"
                    />
                    <NextInput placeholder="Number" :error="form.errors?.number" type="number" v-model="form.number" :label="t('general.bill_number')" />
                    <NextDate v-model="form.date" :error="form.errors?.date" :placeholder="t('general.enter', { text: t('general.date') })" :label="t('general.date')" />
                    <div class="grid grid-cols-2 gap-2">
                        <NextSelect
                            :options="currencies?.data || currencies || []"
                            v-model="form.selected_currency"
                            label-key="code"
                            value-key="id"
                            @update:modelValue="(value) => handleSelectChange('currency_id', value.id)"
                            :reduce="currency => currency"
                            :floating-text="t('admin.currency.currency')"
                            :error="form.errors?.currency_id"
                            :searchable="true"
                            resource-type="currencies"
                            :search-fields="['name', 'code', 'symbol']"
                        />
                        <NextInput placeholder="Rate" :error="form.errors?.rate" type="number" step="any" v-model="form.rate" :label="t('general.rate')" />
                    </div>

                    <div class="grid grid-cols-1 gap-2">
                        <NextSelect
                            :options="salePurchaseTypes?.data || salePurchaseTypes || []"
                            v-model="form.selected_sale_purchase_type"
                            @update:modelValue="(value) => handleSelectChange('sale_purchase_type_id', value)"
                            label-key="name"
                            value-key="id"
                            :reduce="salePurchaseType => salePurchaseType.id"
                            :floating-text="t('general.type')"
                            :error="form.errors?.sale_purchase_type_id"
                        />
                    </div>
                    <NextSelect
                        :options="stores?.data || stores || []"
                        v-model="form.selected_store"
                        @update:modelValue="(value) => handleSelectChange('store_id', value)"
                        label-key="name"
                        value-key="id"
                        :reduce="store => store.id"
                        :floating-text="t('admin.store.store')"
                        :error="form.errors?.store_id"
                    />
                </div>
            </div>

            <div class="rounded-xl border bg-card shadow-sm overflow-x-auto max-h-80">
                <table class="w-full table-fixed min-w-[1000px] purchase-table border-separate">
                    <thead class="bg-card sticky top-0 z-[200]">
                        <tr class="text-muted-foreground font-semibold text-sm text-violet-500">
                            <th class="px-1 py-1 w-5 min-w-5">#</th>
                            <th class="px-1 py-1 w-40 min-w-64">{{ t('item.item') }}</th>
                            <th class="px-1 py-1 w-32">{{ t('general.batch') }}</th>
                            <th class="px-1 py-1 w-36">{{ t('general.expire_date') }}</th>
                            <th class="px-1 py-1 w-16">{{ t('general.qty') }}</th>
                            <th class="px-1 py-1 w-24">{{ t('general.unit') }}</th>
                            <th class="px-1 py-1 w-24">{{ t('general.price') }}</th>
                            <th class="px-1 py-1 w-24">{{ t('general.discount') }}</th>
                            <th class="px-1 py-1 w-16">{{ t('general.free') }}</th>
                            <th class="px-1 py-1 w-16">{{ t('general.tax') }}</th>
                            <th class="px-1 py-1 w-16">{{ t('general.total') }}</th>
                            <th class="px-1 py-1 w-10">
                                <Trash2 class="w-4 h-4 text-red-500 inline" />
                            </th>
                        </tr>
                    </thead>
                    <tbody class="p-2">
                        <tr v-for="(item, index) in form.items" :key="item.id" class="hover:bg-muted/40 transition-colors">
                            <td class="px-1 py-2 align-top w-5">{{ index + 1 }}</td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                                <NextSelect
                                    :options="items?.data || items || []"
                                    v-model="item.selected_item"
                                    label-key="name"
                                    :placeholder="t('general.search_or_select')"
                                    id="item_id"
                                    :error="form.errors?.item_id"
                                    :show-arrow="false"
                                    :searchable="true"
                                    resource-type="items"
                                    :search-fields="['name', 'code', 'generic_name', 'packing', 'barcode', 'fast_search']"
                                    value-key="id"
                                    :reduce="item => item"
                                    @update:modelValue="value => { handleItemChange(index, value); }"
                                />
                            </td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                                <NextInput
                                    v-model="item.batch"
                                    :disabled="!item.selected_item"
                                    :error="form.errors?.[`item_list.${index}.batch`]"
                                />
                            </td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                                <NextDate v-model="item.expire_date"
                                    :error="form.errors?.[`item_list.${index}.expire_date`]" />
                            </td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                                <NextInput
                                    v-model="item.quantity"
                                    :disabled="!item.selected_item"
                                    type="number" step="any"
                                    inputmode="decimal"
                                    :error="form.errors?.[`item_list.${index}.quantity`]"
                                />
                            </td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                                <NextSelect
                                    :options="item.available_measures || []"
                                    v-model="item.selected_measure"
                                    label-key="name"
                                    :error="form.errors?.[`item_list.${index}.unit_measure_id`]"
                                    value-key="id"
                                    :show-arrow="false"
                                    :reduce="unit => unit"
                                    @update:modelValue="(measure) => {
                                        const baseUnit = Number(form.items[index]?.selected_item?.unitMeasure?.unit) || 1;
                                        const selectedUnit = Number(measure?.unit) || baseUnit;
                                        const baseUnitPrice = Number(form.items[index]?.base_unit_price) || 0;
                                        form.items[index].unit_price = baseUnitPrice / selectedUnit * form.rate;
                                    }"
                                />
                            </td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                                <NextInput
                                    v-model="item.unit_price"
                                    :disabled="!item.selected_item"
                                    type="number" step="any"
                                    inputmode="decimal"
                                    :error="form.errors?.[`item_list.${index}.unit_price`]"
                                />
                            </td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                                <NextInput
                                    v-model="item.item_discount"
                                    :disabled="!item.selected_item"
                                    type="number" step="any"
                                    inputmode="decimal"
                                    :error="form.errors?.[`item_list.${index}.item_discount`]"
                                />
                            </td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                                <NextInput
                                    v-model="item.free"
                                    :disabled="!item.selected_item"
                                    type="number" step="any"
                                    inputmode="decimal"
                                    :error="form.errors?.[`item_list.${index}.free`]"
                                />
                            </td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                                <NextInput
                                    v-model="item.tax"
                                    :disabled="!item.selected_item"
                                    type="number" step="any"
                                    inputmode="decimal"
                                    :error="form.errors?.[`item_list.${index}.tax`]"
                                />
                            </td>
                            <td class="text-center">
                                 {{ rowTotal(index) }} {{ item.selected_item ? transactionSummary?.currencySymbol : '' }}
                            </td>
                            <td class="w-10 text-center">
                                <Trash2 class="w-4 h-4 cursor-pointer text-fuchsia-500 inline" @click="deleteRow(index)" />
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="sticky bottom-0 bg-card">
                        <tr class="bg-muted/40">
                            <td></td>
                            <td class="text-center">{{ totalRows }}</td>
                            <td></td>
                            <td></td>
                            <td class="text-center">{{ totalQuantity || 0 }}</td>
                            <td></td>
                            <td class="text-center">{{ goodsTotal || 0 }}</td>
                            <td class="text-center">{{ totalItemDiscount || 0 }}</td>
                            <td class="text-center">{{ totalFree || 0 }}</td>
                            <td class="text-center">{{ totalTax || 0 }}</td>
                            <td class="text-center">{{ totalRowTotal || 0 }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="mt-5 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-2 items-start">
                <DiscountSummary :summary="form.summary" :total-item-discount="totalItemDiscount" :bill-discount="transactionSummary.billDiscount" :total-discount="transactionSummary.itemDiscount + transactionSummary.billDiscount" />
                <TaxSummary :summary="form.summary" :total-item-tax="totalTax" />
                <div class="rounded-xl p-4">
                    <div class="text-sm font-semibold mb-3 text-violet-500 text-sm">{{ t('general.bill_discount') }}</div>
                    <DiscountField
                        v-model="form.discount"
                        v-model:discount-type="form.discount_type"
                        :error="form.errors?.discount"
                    />
                </div>
                <TransactionSummary :summary="transactionSummary" :balance-nature="form?.selected_ledger?.statement?.balance_nature" />
            </div>

            <div class="mt-4 flex gap-2">
                <button type="submit" class="btn btn-primary px-4 py-2 rounded-md bg-primary text-white disabled:bg-gray-300" :disabled="disabled">
                    {{ t('general.update') }}
                </button>
                <button type="button" class="btn px-4 py-2 rounded-md border" @click="() => $inertia.visit(route('purchases.index'))">
                    {{ t('general.cancel') }}
                </button>
            </div>
        </form>

        <!-- Payment Dialog for Credit Transactions -->
        <PaymentDialog
            :open="showPaymentDialog"
            :payment="form.payment"
            :errors="form.errors"
            :accounts="accounts?.data || accounts || []"
            :submitting="false"
            @update:open="(value) => showPaymentDialog = value"
            @confirm="handlePaymentDialogConfirm"
            @cancel="handlePaymentDialogCancel"
            @update:payment="(payment) => form.payment = payment"
        />
    </AppLayout>
</template>

<style scoped>
.purchase-table thead {
    border: 2px solid hsl(var(--border));
    border-radius: 8px;
}

.purchase-table thead th {
    border-bottom: 1px solid hsl(var(--border));
    padding: 0.5rem;
    white-space: nowrap;
    overflow: hidden;
}
</style>
