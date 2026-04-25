<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { h, ref, watch, onMounted, onUnmounted, computed } from 'vue';
import axios from 'axios';
import { useForm } from '@inertiajs/vue3';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import DiscountField from '@/Components/next/DiscountField.vue';
import PaymentDialog from '@/Components/next/PaymentDialog.vue';
import { useI18n } from 'vue-i18n';
import TransactionSummary from '@/Components/next/TransactionSummary.vue';
import DiscountSummary from '@/Components/next/DiscountSummary.vue';
import TaxSummary from '@/Components/next/TaxSummary.vue';
import SubmitButtons from '@/Components/SubmitButtons.vue';
import ModuleHelpButton from '@/Components/ModuleHelpButton.vue';
import { useSidebar } from '@/Components/ui/sidebar/utils';
import { ToastAction } from '@/Components/ui/toast';
import { useToast } from '@/Components/ui/toast/use-toast';
import NextDate from '@/Components/next/NextDatePicker.vue';
import { Trash2 } from 'lucide-vue-next';
import { useLazyProps } from '@/composables/useLazyProps';

const { t } = useI18n();
const { toast } = useToast();

const props = defineProps({
    sale: { type: Object, required: true },
    ledgers: { type: Object, required: false, default: () => ({ data: [] }) },
    salePurchaseTypes: { type: Object, required: true },
    currencies: { type: Object, required: true },
    warehouses: { type: Object, required: true },
    unitMeasures: { type: Object, required: true },
    accounts: { type: Object, required: false, default: () => ({ data: [] }) },
    user_preferences: { type: Object, required: true },
    bankAccounts: { type: Array, required: true },
});

const userPreferences = computed(() => props.user_preferences?.data ?? props.user_preferences ?? {});
const generalFields = computed(() => userPreferences.value?.sale?.general_fields ?? {});
const itemColumns = computed(() => userPreferences.value?.sale?.item_columns ?? {});
const itemManagement = computed(() => userPreferences.value?.item_management ?? {});
const specText = computed(() => itemManagement.value?.spec_text ?? 'batch');
const decimalPlaces = computed(() => Number(userPreferences.value?.appearance?.decimal_places ?? 2));
const saleRecord = props.sale?.data ?? props.sale ?? {};
const MIN_SALE_ROWS = 6;

useLazyProps(props, ['ledgers', 'accounts']);

const toNum = (value, fallback = 0) => {
    const number = Number(value);
    return Number.isNaN(number) ? fallback : number;
};

const quantityOutInBaseUnits = ({ quantity = 0, free = 0, selectedMeasure = null, selectedItem = null } = {}) => {
    const baseUnit = Number(selectedItem?.unitMeasure?.unit) || 1;
    const selectedUnit = Number(selectedMeasure?.unit) || baseUnit;
    return (toNum(quantity, 0) + toNum(free, 0)) * (selectedUnit / baseUnit);
};

const buildEmptyRow = () => ({
    item_id: '',
    selected_item: '',
    quantity: '',
    unit_measure_id: '',
    batch: '',
    selected_batch: null,
    expire_date: '',
    unit_price: '',
    base_unit_price: '',
    on_hand: '',
    available_measures: [],
    selected_measure: '',
    item_discount: '',
    free: '',
    tax: '',
    persisted_item_id: '',
    persisted_quantity_base: 0,
});

const getAvailableMeasures = (selectedItem) => {
    if (!selectedItem) return [];
    const selectedUnitMeasure = selectedItem?.unitMeasure || {};
    const selectedQuantityId = selectedUnitMeasure.quantity_id ?? selectedUnitMeasure.quantity?.id;
    const selectedQuantityName = (selectedUnitMeasure.quantity?.name || selectedUnitMeasure.quantity?.code || '').toString().toLowerCase();
    return (props.unitMeasures?.data || []).filter((unit) => {
        const unitQuantityId = unit?.quantity_id ?? unit?.quantity?.id;
        const unitQuantityName = (unit?.quantity?.name || unit?.quantity?.code || '').toString().toLowerCase();
        return (selectedQuantityId && unitQuantityId === selectedQuantityId)
            || (!!selectedQuantityName && unitQuantityName === selectedQuantityName);
    });
};

const findById = (items, id) => (items || []).find((item) => item?.id === id) || null;
const bankAccountIds = new Set((props.bankAccounts || []).map((account) => account.id));
const paymentLine = (saleRecord?.transaction?.lines || []).find(
    (line) => bankAccountIds.has(line.account_id) && toNum(line.debit, 0) > 0
);

const initialRows = (saleRecord?.items || []).map((item) => {
    const selectedItem = item.item || null;
    const availableMeasures = getAvailableMeasures(selectedItem);
    const selectedMeasure = findById(availableMeasures, item.unit_measure_id)
        || item.unit_measure
        || selectedItem?.unitMeasure
        || '';
    const baseUnit = Number(selectedItem?.unitMeasure?.unit) || 1;
    const selectedUnit = Number(selectedMeasure?.unit) || baseUnit;
    const rate = toNum(saleRecord?.rate, 1) || 1;
    const baseUnitPrice = selectedUnit
        ? (toNum(item.unit_price, 0) * baseUnit) / (selectedUnit * rate)
        : toNum(item.unit_price, 0);
    const persistedQuantityBase = quantityOutInBaseUnits({
        quantity: item.quantity,
        free: item.free,
        selectedMeasure,
        selectedItem,
    });

    return {
        ...buildEmptyRow(),
        item_id: item.item_id,
        selected_item: selectedItem,
        quantity: item.quantity,
        unit_measure_id: item.unit_measure_id,
        batch: item.batch || '',
        selected_batch: item.batch ? { batch: item.batch, expire_date: item.expire_date, on_hand: 0 } : null,
        expire_date: item.expire_date || '',
        unit_price: item.unit_price,
        base_unit_price: Number.isFinite(baseUnitPrice) ? baseUnitPrice : toNum(item.unit_price, 0),
        on_hand: selectedItem?.on_hand ?? 0,
        available_measures: availableMeasures,
        selected_measure: selectedMeasure,
        item_discount: item.discount || '',
        free: item.free || '',
        tax: item.tax || '',
        persisted_item_id: item.item_id,
        persisted_quantity_base: persistedQuantityBase,
    };
});

const form = useForm({
    number: saleRecord.number,
    customer_id: saleRecord.customer_id,
    date: saleRecord.date,
    currency_id: saleRecord.currency_id,
    rate: saleRecord.rate,
    sale_type: saleRecord.sale_purchase_type_id,
    selected_currency: findById(props.currencies?.data, saleRecord.currency_id) || saleRecord.transaction?.currency || '',
    selected_ledger: saleRecord.customer || '',
    selected_sale_type: findById(props.salePurchaseTypes, saleRecord.sale_purchase_type_id) || '',
    selected_bank_account: findById(props.bankAccounts, paymentLine?.account_id) || '',
    bank_account_id: paymentLine?.account_id || '',
    discount: saleRecord.discount ?? '',
    transaction_total: saleRecord.transaction_total || 0,
    discount_total: 0,
    discount_type: saleRecord.discount_type || 'percentage',
    description: saleRecord.description || '',
    payment: paymentLine ? {
        method: 'cash',
        amount: toNum(paymentLine.debit, 0),
        account_id: paymentLine.account_id,
        note: '',
    } : {
        method: '',
        amount: '',
        account_id: '',
        note: '',
    },
    status: saleRecord.status || '',
    warehouse_id: saleRecord.warehouse_id,
    selected_warehouse: findById(props.warehouses?.data, saleRecord.warehouse_id) || saleRecord.warehouse || '',
    item_list: saleRecord.item_list || [],
    items: initialRows.length ? [...initialRows] : Array.from({ length: MIN_SALE_ROWS }, buildEmptyRow),
});

const hydratedExistingRows = ref(false);
const itemOptions = ref([]);
const showPaymentDialog = ref(false);
const submitAction = ref(null);
const pendingPrintWindow = ref(null);

const itemSearchOptions = computed(() => {
    const additionalParams = {};
    if (form.warehouse_id) additionalParams.warehouse_id = form.warehouse_id;
    return { additionalParams, limit: 200 };
});

const loadItemOptions = async (warehouseId = form.warehouse_id) => {
    if (!warehouseId) {
        itemOptions.value = [];
        return;
    }
    try {
        const response = await axios.get(route('search.items-list'), {
            params: { warehouse_id: warehouseId, limit: 50 },
        });
        itemOptions.value = response.data?.data || [];
    } catch (error) {
        console.error('Failed to load items', error);
        itemOptions.value = [];
    }
};

const ensureTrailingEmptyRow = () => {
    const lastRow = form.items[form.items.length - 1];
    if (lastRow?.selected_item) form.items.push(buildEmptyRow());
};

const ensureMinimumRows = () => {
    while (form.items.length < MIN_SALE_ROWS) {
        form.items.push(buildEmptyRow());
    }
};

const normalizeRowLayout = () => {
    ensureTrailingEmptyRow();
    ensureMinimumRows();
};

const hydrateExistingItems = () => {
    form.items.forEach((row) => {
        if (!row.item_id) return;

        const liveItem = itemOptions.value.find((option) => option.id === row.item_id);
        const selectedItem = liveItem || row.selected_item;
        if (!selectedItem) return;

        const availableMeasures = getAvailableMeasures(selectedItem);
        const selectedMeasure = findById(availableMeasures, row.unit_measure_id)
            || row.selected_measure
            || selectedItem.unitMeasure
            || '';
        const selectedBatch = selectedItem?.batches?.find((batch) => {
            const batchExpiry = batch?.expire_date || '';
            return batch?.batch === row.batch && batchExpiry === (row.expire_date || '');
        }) || selectedItem?.batches?.find((batch) => batch?.batch === row.batch) || row.selected_batch;
        const baseUnit = Number(selectedItem?.unitMeasure?.unit) || 1;
        const selectedUnit = Number(selectedMeasure?.unit) || baseUnit;
        const rate = toNum(form.rate, 1) || 1;
        const baseUnitPrice = selectedUnit
            ? (toNum(row.unit_price, 0) * baseUnit) / (selectedUnit * rate)
            : toNum(row.unit_price, 0);

        row.selected_item = selectedItem;
        row.available_measures = availableMeasures;
        row.selected_measure = selectedMeasure;
        row.selected_batch = selectedBatch ?? null;
        row.batch = selectedBatch?.batch ?? row.batch ?? '';
        row.expire_date = selectedBatch?.expire_date ?? row.expire_date ?? '';
        row.on_hand = selectedBatch?.on_hand ?? selectedItem?.on_hand ?? row.on_hand ?? '';
        row.base_unit_price = Number.isFinite(baseUnitPrice) ? baseUnitPrice : toNum(row.unit_price, 0);
    });

    normalizeRowLayout();
    hydratedExistingRows.value = true;
};

watch(() => form.warehouse_id, async (warehouseId, previousWarehouseId) => {
    if (!warehouseId) {
        itemOptions.value = [];
        return;
    }

    await loadItemOptions(warehouseId);

    if (!hydratedExistingRows.value) {
        hydrateExistingItems();
        return;
    }

    if (previousWarehouseId && previousWarehouseId !== warehouseId) {
        form.items = Array.from({ length: MIN_SALE_ROWS }, buildEmptyRow);
    }
}, { immediate: true });

watch(() => props.bankAccounts, (bankAccounts) => {
    if (bankAccounts && !form.selected_bank_account) {
        const baseBankAccount = bankAccounts.find((account) => account.slug === 'cash-in-hand');
        if (baseBankAccount) {
            form.selected_bank_account = baseBankAccount;
            form.bank_account_id = baseBankAccount.id;
        }
    }
}, { immediate: true });

watch(() => form.selected_sale_type, (newType) => {
    if (newType?.id === 'credit') showPaymentDialog.value = true;
});

let disabled = false;

const createLoading = computed(() => form.processing && submitAction.value === 'update');
const createAndNewLoading = computed(() => false);
const saveAndPrintLoading = computed(() => form.processing && submitAction.value === 'save_and_print');

const handleSubmitAction = (action = 'update') => {
    submitAction.value = action;
    if (action === 'save_and_print') {
        pendingPrintWindow.value = window.open('about:blank', '_blank');
    }
    handleSubmit({ saveAndPrint: action === 'save_and_print' });
};

const handleResetPayment = () => {
    form.payment = { method: '', amount: '', account_id: '', note: '' };
};

const handleSelectChange = (field, value) => {
    if (field === 'currency_id') form.rate = value.exchange_rate;
    if (field === 'sale_type' && value?.id === 'cash') handleResetPayment();
    if (field === 'warehouse_id') form.items = Array.from({ length: MIN_SALE_ROWS }, buildEmptyRow);
    form[field] = value?.id ?? value;
};

const finalizePrint = (page) => {
    const printUrl = page?.props?.flash?.print_url;
    if (!printUrl) {
        if (pendingPrintWindow.value && !pendingPrintWindow.value.closed) pendingPrintWindow.value.close();
        pendingPrintWindow.value = null;
        return;
    }
    if (pendingPrintWindow.value && !pendingPrintWindow.value.closed) {
        pendingPrintWindow.value.location = printUrl;
        pendingPrintWindow.value.focus?.();
    } else {
        window.open(printUrl, '_blank');
    }
    pendingPrintWindow.value = null;
};

const cleanupPrintWindow = () => {
    if (pendingPrintWindow.value && !pendingPrintWindow.value.closed) pendingPrintWindow.value.close();
    pendingPrintWindow.value = null;
};

const notifySound = (type) => {
    const fileName = type === 'success' ? '/notify_sounds/filling-your-inbox.mp3' : '/notify_sounds/glass-breaking.mp3';
    const sound = new Audio(fileName);
    sound.play().catch((error) => console.error('Error playing sound:', error));
};

const isRowEnabled = (index) => {
    if (!form.selected_ledger) return false;
    for (let i = 0; i < index; i += 1) {
        if (!form.items[i]?.selected_item) return false;
    }
    return true;
};

const buildRowKey = (row) => [
    (row.item_id || row.selected_item?.id || '').toString(),
    (row.batch || '').toString().trim().toLowerCase(),
    (row.expire_date || '').toString().trim(),
    (row?.selected_measure?.id || '').toString(),
].join('|');

const isDuplicateRow = (index) => {
    const row = form.items[index];
    if (!row || !row.selected_item || !row.selected_measure) return false;

    const rowKey = buildRowKey(row);
    let count = 0;
    for (let i = 0; i < form.items.length; i += 1) {
        if (buildRowKey(form.items[i]) === rowKey) count += 1;
        if (count > 1) return true;
    }
    return false;
};

const resetRow = (index) => {
    form.items[index] = buildEmptyRow();
    disabled = false;
};

const notifyIfDuplicate = (index) => {
    if (isDuplicateRow(index)) {
        const item = form.items[index];
        const batchText = item.batch ? `Batch: ${item.batch}` : 'No batch';
        const expiryText = item.expire_date ? `Expiry: ${item.expire_date}` : 'No expiry';
        disabled = true;
        toast({
            title: 'Duplicate item detected',
            description: `Same item with ${batchText} and ${expiryText} already exists.`,
            variant: 'destructive',
            class: 'bg-pink-600 text-white',
            duration: Infinity,
            action: h(ToastAction, {
                altText: 'Unselect',
                onClick: () => resetRow(index),
            }, { default: () => 'Unselect' }),
        });
    } else {
        disabled = false;
    }
};

const handleItemChange = (index, selectedItem) => {
    const row = form.items[index];
    if (!row) return;

    if (!selectedItem) {
        form.items[index] = buildEmptyRow();
        return;
    }

    row.available_measures = getAvailableMeasures(selectedItem);
    row.selected_measure = selectedItem.unitMeasure;
    row.item_id = selectedItem.id;
    row.unit_measure_id = selectedItem.unit_measure_id;
    row.on_hand = selectedItem.on_hand;
    row.selected_batch = null;
    row.batch = '';
    row.expire_date = '';
    row.quantity = '';
    row.item_discount = '';
    row.free = '';
    row.tax = '';

    const marginPercentage = toNum(selectedItem.margin_percentage, 0);
    row.base_unit_price = selectedItem.sale_price ?? (toNum(selectedItem.avg_cost, 0) * (1 + marginPercentage / 100));

    const baseUnit = Number(selectedItem.unitMeasure?.unit) || 1;
    const selectedUnit = Number(row.selected_measure?.unit) || baseUnit;
    row.unit_price = Number(((toNum(row.base_unit_price, 0) * selectedUnit * toNum(form.rate, 1)) / baseUnit).toFixed(decimalPlaces.value));
    row.selected_item = selectedItem;

    if (index === form.items.length - 1) form.items.push(buildEmptyRow());
    notifyIfDuplicate(index);
};

const handleBatchChange = (index, batch) => {
    const row = form.items[index];
    row.selected_batch = batch ?? null;
    row.batch = batch?.batch ?? '';
    row.expire_date = batch?.expire_date ?? '';
    row.on_hand = batch?.on_hand ?? row.selected_item?.on_hand ?? '';
    notifyIfDuplicate(index);
};

const onhand = (index) => {
    const item = form.items[index];
    if (!item || !item.selected_item) return '';

    const onHandBase = Number(item.selected_batch?.on_hand ?? item.selected_item?.on_hand ?? item.on_hand) || 0;
    const baseUnit = Number(item.selected_item?.unitMeasure?.unit) || 1;
    const selectedUnit = Number(item.selected_measure?.unit) || baseUnit;
    const persistedQuantityBase = (item.selected_item?.id || item.item_id) === item.persisted_item_id
        ? toNum(item.persisted_quantity_base, 0)
        : 0;
    const currentQuantityBase = quantityOutInBaseUnits({
        quantity: item.quantity,
        free: item.free,
        selectedMeasure: item.selected_measure,
        selectedItem: item.selected_item,
    });
    const adjustedOnHandBase = onHandBase + persistedQuantityBase - currentQuantityBase;
    const converted = (adjustedOnHandBase * baseUnit) / selectedUnit;

    return Number.isFinite(converted) ? Number(converted.toFixed(2)) : 0;
};

const rowTotal = (index) => {
    const item = form.items[index];
    if (!item || !item.selected_item) return '';
    return Number((
        toNum(item.quantity, 0) * toNum(item.unit_price, 0)
        - toNum(item.item_discount, 0)
        + toNum(item.tax, 0)
    ).toFixed(decimalPlaces.value));
};

const deleteRow = (index) => {
    if (form.items.length === 1) return;
    form.items.splice(index, 1);
    if (!form.items.length) form.items.push(buildEmptyRow());
    normalizeRowLayout();
};

watch(() => form.rate, (newRate) => {
    if (!Array.isArray(form.items)) return;
    form.items.forEach((row) => {
        if (!row?.selected_item) return;
        const baseUnit = Number(row.selected_item?.unitMeasure?.unit) || 1;
        const selectedUnit = Number(row.selected_measure?.unit) || baseUnit;
        const baseUnitPrice = toNum(row.base_unit_price ?? row.selected_item?.sale_price, 0);
        row.unit_price = (baseUnitPrice * selectedUnit * toNum(newRate, 1)) / baseUnit;
    });
});

const displayedAverageCost = (row) => {
    if (!row?.selected_item) return 0;

    const baseUnit = Number(row.selected_item?.unitMeasure?.unit) || 1;
    const selectedUnit = Number(row.selected_measure?.unit) || baseUnit;
    return (toNum(row.selected_item?.avg_cost, 0) * selectedUnit * toNum(form.rate, 1)) / baseUnit;
};

const getLossWarning = (row) => {
    if (!row?.selected_item) return '';

    const avgCost = displayedAverageCost(row);
    const unitPrice = toNum(row.unit_price, 0);
    if (unitPrice <= 0 || avgCost <= 0 || unitPrice >= avgCost) {
        return '';
    }

    return t('general.loss_price_warning', {
        avgCost: avgCost.toFixed(decimalPlaces.value),
        unitPrice: unitPrice.toFixed(decimalPlaces.value),
    });
};

const totalRows = computed(() => form.items.filter((item) => item?.selected_item).length);
const totalItemDiscount = computed(() => form.items.reduce((acc, item) => acc + toNum(item.item_discount, 0), 0));
const totalTax = computed(() => form.items.reduce((acc, item) => acc + toNum(item.tax, 0), 0));
const goodsTotal = computed(() => form.items.reduce((acc, item) => acc + (toNum(item.unit_price, 0) * toNum(item.quantity, 0)), 0));
const billDiscountCurrency = computed(() => {
    const billDiscount = toNum(form.discount, 0);
    return form.discount_type === 'percentage' ? goodsTotal.value * (billDiscount / 100) : billDiscount;
});
const billDiscountPercent = computed(() => {
    const billDiscount = toNum(form.discount, 0);
    if (form.discount_type === 'percentage') return billDiscount;
    return goodsTotal.value > 0 ? (billDiscount / goodsTotal.value) * 100 : 0;
});
const totalDiscount = computed(() => Number((billDiscountCurrency.value + totalItemDiscount.value).toFixed(decimalPlaces.value)));
const totalRowTotal = computed(() => Number(form.items.reduce((acc, item) => {
    return acc + (toNum(item.unit_price, 0) * toNum(item.quantity, 0) - toNum(item.item_discount, 0) + toNum(item.tax, 0));
}, 0).toFixed(decimalPlaces.value)));
const totalQuantity = computed(() => Number(form.items.reduce((acc, item) => acc + toNum(item.quantity, 0), 0).toFixed(decimalPlaces.value)));
const totalFree = computed(() => Number(form.items.reduce((acc, item) => acc + toNum(item.free, 0), 0).toFixed(decimalPlaces.value)));

const transactionSummary = computed(() => {
    const type = form.selected_sale_type?.id || form.sale_type;
    const oldBalance = toNum(form?.selected_ledger?.statement?.balance, 0);
    const balanceNature = form?.selected_ledger?.statement?.balance_nature;
    const hasSelectedItem = Array.isArray(form.items) && form.items.some((row) => !!row.selected_item);
    const netAmount = goodsTotal.value - totalDiscount.value + totalTax.value;
    const paid = type === 'cash'
        ? netAmount
        : (type === 'credit' ? toNum(form.payment.amount, 0) : 0);
    const receivableDelta = netAmount - paid;
    const signedOldBalance = balanceNature === 'dr' ? oldBalance : -oldBalance;
    const signedBalance = hasSelectedItem ? signedOldBalance + receivableDelta : signedOldBalance;

    return {
        valueOfGoods: Number(goodsTotal.value.toFixed(decimalPlaces.value)),
        billDiscountPercent: billDiscountPercent.value,
        billDiscount: billDiscountCurrency.value,
        itemDiscount: totalItemDiscount.value,
        cashReceived: paid,
        balance: Math.abs(signedBalance),
        grandTotal: netAmount,
        oldBalance,
        balanceNature: signedBalance >= 0 ? 'dr' : 'cr',
        currencySymbol: form.selected_currency?.symbol,
    };
});

const handleSubmit = ({ saveAndPrint = false } = {}) => {
    const selectedItems = form.items.filter((item) => item.selected_item && item.item_id);

    if (!selectedItems.length) {
        cleanupPrintWindow();
        notifySound('error');
        toast({
            title: 'Please add items',
            description: 'Please add at least one item to update the sale',
            variant: 'destructive',
            class: 'bg-yellow-600 text-white',
        });
        return;
    }

    form.item_list = selectedItems.map((item) => ({
        item_id: item.item_id,
        quantity: item.quantity,
        unit_price: item.unit_price,
        free: item.free || 0,
        batch: item.batch || '',
        expire_date: item.expire_date || null,
        item_discount: item.item_discount || 0,
        tax: item.tax || 0,
        unit_measure_id: item.selected_measure?.id || item.unit_measure_id,
    }));
    form.transaction_total = toNum(goodsTotal.value - totalDiscount.value + totalTax.value);
    form.discount_total = toNum(totalDiscount.value);

    form.transform((data) => ({
        ...data,
        save_and_print: saveAndPrint,
    })).put(route('sales.update', saleRecord.id), {
        onSuccess: (page) => {
            notifySound('success');
            if (saveAndPrint) finalizePrint(page);
            toast({
                title: t('general.success'),
                description: t('general.update_success', { name: t('sale.sale') }),
                variant: 'success',
                class: 'bg-green-600 text-white',
            });
        },
        onError: () => {
            cleanupPrintWindow();
            notifySound('error');
            toast({
                title: t('general.error'),
                description: t('general.update_error', { name: t('sale.sale') }),
                variant: 'destructive',
                class: 'bg-pink-600 text-white',
            });
        },
    });
};

const handlePaymentDialogConfirm = () => {
    showPaymentDialog.value = false;
};

const handlePaymentDialogCancel = () => {
    const cashType = props.salePurchaseTypes.find((type) => type.id === 'cash');
    if (cashType) {
        form.selected_sale_type = cashType;
        form.sale_type = cashType.id;
    }
    showPaymentDialog.value = false;
};

let sidebar = null;
try {
    sidebar = useSidebar();
} catch (error) {
    sidebar = null;
}

const previousSidebarOpen = ref(true);
onMounted(() => {
    if (sidebar) {
        previousSidebarOpen.value = sidebar.open.value;
        sidebar.setOpen(false);
    }
});
onUnmounted(() => {
    if (sidebar) {
        sidebar.setOpen(previousSidebarOpen.value);
    }
});
</script>
<template>
    <AppLayout :title="t('general.edit', { name: t('sale.sale') })" :sidebar-collapsed="true">
        <form @submit.prevent="handleSubmitAction('update')">
            <div class="mb-5 rounded-xl border border-violet-500 p-4 shadow-sm relative">
                <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
                    {{ t('general.edit', { name: t('sale.sale') }) }}
                </div>
                <ModuleHelpButton module="sales" />
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                    <NextSelect
                        :options="ledgers?.data || []"
                        v-model="form.selected_ledger"
                        @update:modelValue="(value) => handleSelectChange('customer_id', value)"
                        label-key="name"
                        value-key="id"
                        :reduce="(ledger) => ledger"
                        :floating-text="t('ledger.customer.customer')"
                        :error="form.errors?.customer_id"
                        :searchable="true"
                        resource-type="ledgers"
                        :search-fields="['name', 'email', 'phone_no']"
                        :search-options="{ type: 'customer' }"
                    />
                    <NextInput
                        v-if="generalFields.number"
                        v-model="form.number"
                        placeholder="Number"
                        :error="form.errors?.number"
                        type="number"
                        :label="t('general.bill_number')"
                    />
                    <NextDate
                        v-if="generalFields.date"
                        v-model="form.date"
                        :error="form.errors?.date"
                        :placeholder="t('general.enter', { text: t('general.date') })"
                        :label="t('general.date')"
                    />
                    <div class="grid grid-cols-2 gap-2" v-if="generalFields.currency">
                        <NextSelect
                            :options="currencies.data"
                            v-model="form.selected_currency"
                            label-key="code"
                            value-key="id"
                            :clearable="false"
                            @update:modelValue="(value) => handleSelectChange('currency_id', value)"
                            :reduce="(currency) => currency"
                            :floating-text="t('admin.currency.currency')"
                            :error="form.errors?.currency_id"
                            :searchable="true"
                            resource-type="currencies"
                            :search-fields="['name', 'code', 'symbol']"
                        />
                        <NextInput
                            v-model="form.rate"
                            placeholder="Rate"
                            :error="form.errors?.rate"
                            type="number"
                            step="any"
                            :disabled="form.selected_currency?.is_base_currency === true"
                            :label="t('general.rate')"
                        />
                    </div>
                    <NextSelect
                        :options="bankAccounts"
                        v-model="form.selected_bank_account"
                        @update:modelValue="(value) => handleSelectChange('bank_account_id', value)"
                        label-key="name"
                        :searchable="true"
                        :floating-text="t('general.bank_account')"
                        :error="form.errors?.bank_account_id"
                        resource-type="accounts"
                        :search-fields="['name', 'number', 'slug']"
                        value-key="id"
                        :reduce="(bankAccount) => bankAccount"
                    />
                    <div class="grid grid-cols-1 gap-2" v-if="generalFields.type">
                        <NextSelect
                            :options="salePurchaseTypes"
                            v-model="form.selected_sale_type"
                            :clearable="false"
                            @update:modelValue="(value) => handleSelectChange('sale_type', value)"
                            label-key="name"
                            value-key="id"
                            :reduce="(salePurchaseType) => salePurchaseType"
                            :floating-text="t('general.sale_type')"
                            :error="form.errors?.sale_type"
                        />
                    </div>
                    <NextSelect
                        v-if="generalFields.warehouse"
                        :options="warehouses.data"
                        :clearable="false"
                        v-model="form.selected_warehouse"
                        @update:modelValue="(value) => handleSelectChange('warehouse_id', value)"
                        label-key="name"
                        value-key="id"
                        :reduce="(warehouse) => warehouse"
                        :floating-text="t('admin.warehouse.warehouse')"
                        :error="form.errors?.warehouse_id"
                        resource-type="warehouses"
                        :search-fields="['name', 'code', 'address']"
                    />
                </div>
            </div>

            <div class="rounded-xl border bg-card shadow-sm border-violet-500">
                <table class="w-full table-fixed min-w-[1200px] sale-table border-separate">
                    <thead class="" :class="form.sale_type === 'cash' ? 'bg-card sticky top-0 z-[200]' : ''">
                        <tr class="rounded-xl text-muted-foreground font-semibold text-sm text-violet-500">
                            <th class="px-1 py-1 w-5 min-w-5">#</th>
                            <th class="px-1 py-1 w-40 min-w-64">{{ t('item.item') }}</th>
                            <th class="px-1 py-1 w-32" v-if="itemColumns.batch">{{ t(specText) }}</th>
                            <th class="px-1 py-1 w-36" v-if="itemColumns.expiry">{{ t('general.expire_date') }}</th>
                            <th class="px-1 py-1 w-16">{{ t('general.qty') }}</th>
                            <th class="px-1 py-1 w-24" v-if="itemColumns.on_hand">{{ t('general.on_hand') }}</th>
                            <th class="px-1 py-1 w-24" v-if="itemColumns.measure">{{ t('general.unit') }}</th>
                            <th class="px-1 py-1 w-24">{{ t('general.price') }}</th>
                            <th class="px-1 py-1 w-24" v-if="itemColumns.discount">{{ t('general.discount') }}</th>
                            <th class="px-1 py-1 w-16" v-if="itemColumns.free">{{ t('general.free') }}</th>
                            <th class="px-1 py-1 w-16" v-if="itemColumns.tax">{{ t('general.tax') }}</th>
                            <th class="px-1 py-1 w-16">{{ t('general.total') }}</th>
                            <th class="px-1 py-1 w-10">
                                <Trash2 class="w-4 h-4 text-fuchsia-700 inline" />
                            </th>
                        </tr>
                    </thead>
                    <tbody class="p-2">
                        <tr v-for="(item, index) in form.items" :key="`${index}-${item.item_id || 'row'}`" class="hover:bg-muted/40 transition-colors">
                            <td class="px-1 py-2 align-top w-5">{{ index + 1 }}</td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                                <NextSelect
                                    :options="itemOptions"
                                    v-model="item.selected_item"
                                    label-key="name"
                                    :placeholder="t('general.search_or_select')"
                                    id="item_id"
                                    :error="form.errors?.[`item_list.${index}.item_id`]"
                                    :show-arrow="false"
                                    :searchable="true"
                                    resource-type="items-list"
                                    :search-fields="['name', 'code', 'generic_name', 'packing', 'barcode', 'fast_search']"
                                    value-key="id"
                                    :reduce="(itemValue) => itemValue"
                                    :search-options="itemSearchOptions"
                                    @update:modelValue="(value) => handleItemChange(index, value)"
                                />
                            </td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }" v-if="itemColumns.batch">
                                <NextSelect
                                    :options="item.selected_item?.batches"
                                    v-model="item.selected_batch"
                                    label-key="batch"
                                    :placeholder="t('general.search_or_select')"
                                    id="batch_id"
                                    :error="form.errors?.[`item_list.${index}.batch`]"
                                    :show-arrow="false"
                                    value-key="batch"
                                    :reduce="(batch) => batch"
                                    @update:modelValue="(value) => handleBatchChange(index, value)"
                                />
                            </td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }" v-if="itemColumns.expiry">
                                <NextDate
                                    v-model="item.expire_date"
                                    popover="top-left"
                                    disabled="true"
                                    :error="form.errors?.[`item_list.${index}.expire_date`]"
                                />
                            </td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                                <NextInput
                                    v-model="item.quantity"
                                    :disabled="!item?.selected_item"
                                    type="number"
                                    step="any"
                                    inputmode="decimal"
                                    :error="form.errors?.[`item_list.${index}.quantity`]"
                                />
                            </td>
                            <td class="text-center" v-if="itemColumns.on_hand">
                                <span :title="String(onhand(index))">{{ Number(onhand(index) || 0)  }}</span>
                            </td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }" v-if="itemColumns.measure">
                                <NextSelect
                                    :options="item.available_measures"
                                    v-model="item.selected_measure"
                                    label-key="name"
                                    :error="form.errors?.[`item_list.${index}.unit_measure_id`]"
                                    value-key="id"
                                    :show-arrow="false"
                                    :reduce="(unit) => unit"
                                    @update:modelValue="(measure) => {
                                        const baseUnit = Number(form.items[index]?.selected_item?.unitMeasure?.unit) || 1;
                                        const selectedUnit = Number(measure?.unit) || baseUnit;
                                        const baseUnitPrice = Number(form.items[index]?.base_unit_price) || 0;
                                        form.items[index].unit_price = (baseUnitPrice * selectedUnit * form.rate) / baseUnit;
                                        form.items[index].unit_measure_id = measure?.id || '';
                                        notifyIfDuplicate(index);
                                    }"
                                />
                            </td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                                <div class="space-y-1">
                                    <NextInput
                                        v-model="item.unit_price"
                                        :disabled="!item?.selected_item"
                                        type="number"
                                        step="any"
                                        inputmode="decimal"
                                        :error="form.errors?.[`item_list.${index}.unit_price`]"
                                    />
                                    <div
                                        v-if="getLossWarning(item)"
                                        class="text-xs text-amber-600 dark:text-amber-400"
                                    >
                                        {{ getLossWarning(item) }}
                                    </div>
                                </div>
                            </td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }" v-if="itemColumns.discount">
                                <NextInput
                                    v-model="item.item_discount"
                                    :disabled="!item?.selected_item"
                                    type="number"
                                    step="any"
                                    inputmode="decimal"
                                    :error="form.errors?.[`item_list.${index}.item_discount`]"
                                />
                            </td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }" v-if="itemColumns.free">
                                <NextInput
                                    v-model="item.free"
                                    :disabled="!item?.selected_item"
                                    type="number"
                                    step="any"
                                    inputmode="decimal"
                                    :error="form.errors?.[`item_list.${index}.free`]"
                                />
                            </td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }" v-if="itemColumns.tax">
                                <NextInput
                                    v-model="item.tax"
                                    :disabled="!item?.selected_item"
                                    type="number"
                                    step="any"
                                    inputmode="decimal"
                                    :error="form.errors?.[`item_list.${index}.tax`]"
                                />
                            </td>
                            <td class="text-center">
                                {{ rowTotal(index) }} {{ item?.selected_item ? transactionSummary?.currencySymbol : '' }}
                            </td>
                            <td class="w-10 text-center">
                                <Trash2 class="w-4 h-4 cursor-pointer text-fuchsia-500 inline" @click="deleteRow(index)" />
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="sticky bottom-0 bg-card">
                        <tr class="bg-violet-500/10 hover:bg-violet-500/30 transition-colors">
                            <td></td>
                            <td class="text-center">{{ totalRows }}</td>
                            <td v-if="itemColumns.batch"></td>
                            <td v-if="itemColumns.expiry"></td>
                            <td class="text-center">{{ totalQuantity || 0 }}</td>
                            <td v-if="itemColumns.on_hand"></td>
                            <td v-if="itemColumns.measure"></td>
                            <td class="text-center">{{ goodsTotal || 0 }}</td>
                            <td class="text-center" v-if="itemColumns.discount">{{ totalItemDiscount || 0 }}</td>
                            <td class="text-center" v-if="itemColumns.free">{{ totalFree || 0 }}</td>
                            <td class="text-center" v-if="itemColumns.tax">{{ totalTax || 0 }}</td>
                            <td class="text-center">{{ totalRowTotal || 0 }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="mt-5 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-2 items-start">
                <DiscountSummary
                    :summary="form.summary"
                    :total-item-discount="totalItemDiscount"
                    :bill-discount="billDiscountCurrency"
                    :total-discount="totalDiscount"
                />
                <TaxSummary :summary="form.summary" :total-item-tax="totalTax" />
                <div class="rounded-xl p-4">
                    <div class="text-sm font-semibold mb-3 text-violet-500">{{ t('general.bill_discount') }}</div>
                    <DiscountField
                        v-model="form.discount"
                        v-model:discount-type="form.discount_type"
                        :error="form.errors?.discount"
                    />
                </div>
                <TransactionSummary
                    :summary="transactionSummary"
                    :balance-nature="form?.selected_ledger?.statement?.balance_nature"
                />
            </div>

            <SubmitButtons
                :create-label="t('general.update')"
                :create-and-new-label="t('general.update')"
                :save-and-print-label="t('general.save_and_print')"
                :cancel-label="t('general.cancel')"
                :creating-label="t('general.update')"
                :create-loading="createLoading"
                :create-and-new-loading="createAndNewLoading"
                :save-and-print-loading="saveAndPrintLoading"
                :show-create-and-new="false"
                :show-save-and-print="true"
                :disabled="disabled"
                @save-and-print="handleSubmitAction('save_and_print')"
                @cancel="() => $inertia.visit(route('sales.index'))"
            />
        </form>

        <PaymentDialog
            :open="showPaymentDialog"
            :bill-total="totalRowTotal"
            :payment="form.payment"
            :errors="form.errors"
            :accounts="props.accounts?.data || []"
            :submitting="false"
            @update:open="(value) => showPaymentDialog = value"
            @confirm="handlePaymentDialogConfirm"
            @cancel="handlePaymentDialogCancel"
            @update:payment="(payment) => form.payment = payment"
        />
    </AppLayout>
</template>

<style scoped>
.sale-table thead {
    border: 2px solid hsl(var(--border));
    border-radius: 8px;
}

.sale-table thead th {
    border-bottom: 1px solid hsl(var(--border));
    padding: 0.5rem;
    white-space: nowrap;
    overflow: hidden;
}
</style>
