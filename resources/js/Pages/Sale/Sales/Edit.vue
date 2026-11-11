<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { useFormGuard } from '@/composables/useFormGuard'
import { h, ref, watch, onMounted, onUnmounted, computed, reactive } from 'vue';
import axios from 'axios';
import { useForm, usePage, router } from '@inertiajs/vue3';
import AttachmentUploader from '@/Components/AttachmentUploader.vue';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import DiscountField from '@/Components/next/DiscountField.vue';
import FormPreferencesPanel from '@/Components/FormPreferencesPanel.vue';
import PaymentDialog from '@/Components/next/PaymentDialog.vue';
import { useI18n } from 'vue-i18n';
import { firstServerError } from '@/composables/useDocumentAction'
import TransactionSummary from '@/Components/next/TransactionSummary.vue';
import SubmitButtons from '@/Components/SubmitButtons.vue';
import FormPageToolbar from '@/Components/FormPageToolbar.vue';
import { useSidebar } from '@/Components/ui/sidebar/utils';
import { ToastAction } from '@/Components/ui/toast';
import { useToast } from '@/Components/ui/toast/use-toast';
import { printDocument } from '@/composables/usePrintDocument';
import NextDate from '@/Components/next/NextDatePicker.vue';
import { pickDefaultVariant, resolveVariantUnitPrice, repriceRow, resolveVariantAverageCost, variantStockFigure, expiryOptionsForRow, batchOptionsForRow, linePriceToHome, repriceRowsForRate, stockInLineUnit, priceInLineUnit } from '@/composables/useVariantLine'
import VariantCell from '@/Components/inventory/VariantCell.vue'
import NextTextarea from '@/Components/next/NextTextarea.vue';
import { Trash2, ScanBarcode } from 'lucide-vue-next';
import { useLazyProps } from '@/composables/useLazyProps';
import { useLineDiscounts } from '@/composables/useLineDiscounts';
import { toDocumentCurrency } from '@/utils/currency';
import { useSaveShortcut } from '@/composables/useSaveShortcut';
import { useLineEnterNavigation } from '@/composables/useLineEnterNavigation';
import BarcodeSearchInput from '@/Components/next/BarcodeSearchInput.vue';

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
// Single reactive copy of the sale preferences so the panel and form stay in sync live.
const salePrefs = reactive(JSON.parse(JSON.stringify(userPreferences.value?.sale ?? {})));
if (!salePrefs.general_fields || typeof salePrefs.general_fields !== 'object') salePrefs.general_fields = {};
if (!salePrefs.item_columns || typeof salePrefs.item_columns !== 'object') salePrefs.item_columns = {};
if (salePrefs.item_columns.reserved_out === undefined) salePrefs.item_columns.reserved_out = true;
const showAttachmentsField = computed(() => salePrefs.show_attachments === true);
const generalFields = computed(() => salePrefs.general_fields);
const itemColumns = computed(() => salePrefs.item_columns);
const showPreferencesPanel = ref(false);
const itemManagement = computed(() => userPreferences.value?.item_management ?? {});
const specText = computed(() => itemManagement.value?.spec_text ?? 'batch');
const decimalPlaces = computed(() => Number(userPreferences.value?.appearance?.decimal_places ?? 2));
const saleRecord = props.sale?.data ?? props.sale ?? {};
const MIN_SALE_ROWS = 6;

const { loading: lazyLoading } = useLazyProps(props, ['ledgers']);

const toNum = (value, fallback = 0) => {
    const number = Number(value);
    return Number.isNaN(number) ? fallback : number;
};

// Money formatter for footer cards: "1,234.00 AFN"
const fmtMoney = (value) => {
    const code = form.selected_currency?.code ?? '';
    const amount = toNum(value, 0).toFixed(decimalPlaces.value);
    return code ? `${amount} ${code}` : amount;
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
    variant_id: null,
    selected_item_variant: null,
    item_variants: [],
    expire_date: '',
    unit_price: '',
    base_unit_price: '',
    on_hand: '',
    reserved_out: '',
    reserved_in: '',
    available: '',
    available_measures: [],
    selected_measure: '',
    item_discount: '',
    // Which rule filled item_discount in, and whether it has since been
    // overruled by hand. Both are UI-only and never posted.
    discount_rule: null,
    discount_touched: false,
    free: '',
    tax: '',
    // True once the price has been typed by hand (UI-only, never posted).
    price_touched: false,
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
// How the draft was paid, as it was saved. A draft has no ledger lines, so the
// server hands the chosen account and the amount taken back on the sale itself.
const savedPayment = saleRecord?.payment || null;
const savedBankAccountId = saleRecord?.bank_account_id || '';

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
        ? (toNum(item.unit_price, 0) * rate * baseUnit) / selectedUnit
        : toNum(item.unit_price, 0) * rate;

    return {
        ...buildEmptyRow(),
        item_id: item.item_id,
        selected_item: selectedItem,
        quantity: item.quantity,
        unit_measure_id: item.unit_measure_id,
        batch: item.batch || '',
        selected_batch: item.batch ? { batch: item.batch, expire_date: item.expire_date, on_hand: 0 } : null,
        variant_id: item.variant_id || null,
        selected_item_variant: item.variant || null,
        item_variants: selectedItem?.item_variants || [],
        expire_date: item.expire_date || '',
        unit_price: item.unit_price,
        base_unit_price: Number.isFinite(baseUnitPrice) ? baseUnitPrice : toNum(item.unit_price, 0),
        on_hand: selectedItem?.on_hand ?? 0,
        reserved_out: selectedItem?.reserved_out ?? 0,
        reserved_in: selectedItem?.reserved_in ?? 0,
        available: selectedItem?.available ?? 0,
        available_measures: availableMeasures,
        selected_measure: selectedMeasure,
        item_discount: item.discount || '',
        // A saved line's discount is settled history. Only a line whose item is
        // picked afresh in this edit asks the rules for a figure.
        discount_touched: true,
        // Likewise its price: it was agreed when the draft was saved.
        price_touched: true,
        free: item.free || '',
        tax: item.tax || '',
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
    selected_bank_account: findById(props.bankAccounts, savedBankAccountId) || '',
    bank_account_id: findById(props.bankAccounts, savedBankAccountId)?.id || '',
    discount: saleRecord.discount ?? '',
    transaction_total: saleRecord.transaction_total || 0,
    discount_total: 0,
    discount_type: saleRecord.discount_type || 'percentage',
    description: saleRecord.description || '',
    payment: toNum(savedPayment?.amount, 0) > 0 ? {
        method: savedPayment.method || '',
        amount: toNum(savedPayment.amount, 0),
        account_id: savedPayment.account_id || '',
        note: savedPayment.note || '',
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
    attachments: [],
});

const existingAttachments = ref(saleRecord.attachments || []);
const removeExistingAttachment = (id) => {
    router.delete(route('attachments.destroy', id), {
        preserveScroll: true,
        onSuccess: () => { existingAttachments.value = existingAttachments.value.filter(a => a.id !== id); },
    });
};

const hydratedExistingRows = ref(false);
const itemOptions = ref([]);
const showPaymentDialog = ref(false);
const submitAction = ref(null);

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

// Each saved line is matched to the live item in the warehouse, so it shows its
// real stock and offers its units, variants and batches. The first page of
// items is not the whole catalogue: a line whose item is not on it is looked
// up by name — without this it showed no stock at all and read "-1".
const findLiveItem = async (row) => {
    const preloaded = itemOptions.value.find((option) => option.id === row.item_id);
    if (preloaded) return preloaded;

    const name = row.selected_item?.name;
    if (!name || !form.warehouse_id) return null;

    try {
        const response = await axios.get(route('search.items-list'), {
            params: { warehouse_id: form.warehouse_id, search: name, limit: 20, in_stock_only: 0 },
        });
        return (response.data?.data || []).find((option) => option.id === row.item_id) || null;
    } catch (error) {
        console.error('Failed to load item for saved line', error);
        return null;
    }
};

const hydrateExistingItems = async () => {
    for (const row of form.items) {
        if (!row.item_id) continue;

        const liveItem = await findLiveItem(row);
        const selectedItem = liveItem || row.selected_item;
        if (!selectedItem) continue;

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
            ? (toNum(row.unit_price, 0) * rate * baseUnit) / selectedUnit
            : toNum(row.unit_price, 0) * rate;

        row.selected_item = selectedItem;
        row.available_measures = availableMeasures;
        row.selected_measure = selectedMeasure;
        row.selected_batch = selectedBatch ?? null;
        row.batch = selectedBatch?.batch ?? row.batch ?? '';
        row.expire_date = row.expire_date ?? '';
        row.on_hand = selectedBatch?.on_hand ?? selectedItem?.on_hand ?? row.on_hand ?? '';
        row.base_unit_price = Number.isFinite(baseUnitPrice) ? baseUnitPrice : toNum(row.unit_price, 0);
        // The live variants carry this warehouse's stock; the saved ones do not.
        if (liveItem) {
            row.item_variants = liveItem.item_variants || [];
            row.selected_item_variant = row.item_variants.find((variant) => variant.id === row.variant_id)
                || row.selected_item_variant;
        }
    }

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

const createLoading = computed(() => form.processing && submitAction.value === 'update');
const createAndNewLoading = computed(() => false);
const saveAndPrintLoading = computed(() => form.processing && submitAction.value === 'save_and_print');

const handleSubmitAction = (action = 'update') => {
    submitAction.value = action;
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
    printDocument(page?.props?.flash?.print_url);
};

const cleanupPrintWindow = () => {
};

const notifySound = (type) => {
    const fileName = type === 'success' ? '/notify_sounds/filling-your-inbox.mp3' : '/notify_sounds/glass-breaking.mp3';
    const sound = new Audio(fileName);
    sound.play().catch((error) => console.error('Error playing sound:', error));
};

const isRowEnabled = (index) => {
    if (!form.selected_ledger) return false;
    // A row that already holds an item stays usable whatever sits above it.
    if (form.items[index]?.selected_item) return true;
    for (let i = 0; i < index; i += 1) {
        if (!form.items[i]?.selected_item) return false;
    }
    return true;
};

const buildRowKey = (row) => {
    const measureId = row?.selected_measure?.id
        || (typeof row?.selected_measure === 'object' ? (row?.selected_measure?.name || row?.selected_measure?.unit) : row?.selected_measure)
        || '';
    return [
        (row.item_id || row.selected_item?.id || '').toString(),
        (row.variant_id || row.selected_item_variant?.id || '').toString(),
        (row.batch || '').toString().trim().toLowerCase(),
        (row.expire_date || '').toString().trim(),
        measureId.toString(),
    ].join('|');
};

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
    // An emptied row in the middle is just a gap: close it.
    if (index < form.items.length - 1) {
        form.items.splice(index, 1);
        ensureMinimumRows();
        return;
    }
    form.items[index] = buildEmptyRow();
};

const duplicateToast = ref(null);

const hasDuplicateRows = computed(() => {
    const seen = new Set();
    for (const row of form.items) {
        if (!row?.selected_item || !row?.selected_measure) continue;
        const key = buildRowKey(row);
        if (seen.has(key)) return true;
        seen.add(key);
    }
    return false;
});

watch(hasDuplicateRows, (hasDuplicates) => {
    if (!hasDuplicates && duplicateToast.value) {
        duplicateToast.value.dismiss();
        duplicateToast.value = null;
    }
});

const notifyIfDuplicate = (index) => {
    if (isDuplicateRow(index)) {
        const item = form.items[index];
        const batchText = item.batch ? t('general.batch_value', { batch: item.batch }) : t('general.no_batch');
        const expiryText = item.expire_date ? t('general.expiry_value', { expiry: item.expire_date }) : t('general.no_expiry');
        duplicateToast.value = toast({
            title: t('general.duplicate_item_detected'),
            description: t('general.duplicate_item_detected_description', { batchText, expiryText }),
            variant: 'destructive',
            class: 'bg-pink-600 text-white',
            duration: Infinity,
            action: h(ToastAction, {
                altText: t('general.unselect'),
                onClick: () => resetRow(index),
            }, { default: () => t('general.unselect') }),
        });
    }
};

// ---------------------------------------------------------------------------
// Automatic item discounts
//
// Same arrangement as the create form, with one difference: every line that
// arrived from the saved sale starts out overruled, so re-opening an invoice
// never rewrites figures the customer has already been given.
// ---------------------------------------------------------------------------
const lineDiscounts = useLineDiscounts();

const applyRuleDiscount = (row) => {
    if (!row?.item_id || row.discount_touched) return;

    const { rule, amount } = lineDiscounts.discountFor(row.item_id, row.quantity, row.unit_price);
    row.discount_rule = rule;
    row.item_discount = amount > 0 ? Number(amount.toFixed(decimalPlaces.value)) : '';
};

/** The box is the salesperson's from the first keystroke. */
const setLineDiscount = (row, value) => {
    row.item_discount = value;
    row.discount_touched = true;
};

/** The rule an overruled line would go back to, if there is one. */
const ruleWaitingFor = (row) => (
    row?.item_id ? lineDiscounts.ruleFor(row.item_id, row.quantity) : null
);

/** Hand the line back to the rules. */
const restoreRuleDiscount = (row) => {
    row.discount_touched = false;
    applyRuleDiscount(row);
};

watch(
    () => form.items.map((row) => `${row?.item_id || ''}:${row?.quantity || ''}:${row?.unit_price || ''}`).join('|'),
    () => form.items.forEach(applyRuleDiscount),
);

const handleItemChange = async (index, selectedItem) => {
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
    row.reserved_out = selectedItem.reserved_out;
    row.reserved_in = selectedItem.reserved_in;
    row.available = selectedItem.available;
    row.selected_batch = null;
    row.batch = '';
    row.expire_date = '';
    row.quantity = '';
    row.item_discount = '';
    row.free = '';
    row.tax = '';

    row.item_variants = selectedItem.item_variants || [];
    row.selected_item_variant = pickDefaultVariant(row.item_variants);
    row.variant_id = row.selected_item_variant?.id || null;

    // What we sell it for, not what it cost us.
    row.base_unit_price = resolveVariantUnitPrice(selectedItem, row.selected_item_variant, 'sale');

    const baseUnit = Number(selectedItem.unitMeasure?.unit) || 1;
    const selectedUnit = Number(row.selected_measure?.unit) || baseUnit;
    row.unit_price = priceInLineUnit(toNum(row.base_unit_price, 0), selectedItem, row.selected_measure, form.rate);
    row.price_touched = false;
    row.selected_item = selectedItem;

    // A newly picked item is not saved history, so the rules may speak.
    row.discount_touched = false;
    row.discount_rule = null;
    await lineDiscounts.load([selectedItem.id], {
        customerId: form.customer_id || null,
        date: form.date || null,
    });
    applyRuleDiscount(row);
    notifyIfVariantNotInWarehouse(row);

    if (index === form.items.length - 1) form.items.push(buildEmptyRow());
    notifyIfDuplicate(index);
};

const handleItemVariantChange = (index, variant) => {
    const row = form.items[index];
    if (!row) return;
    row.selected_item_variant = variant ?? null;
    row.variant_id = variant?.id || null;
    // Switching variant moves the price with it — that is what pricing a
    // variant separately is for.
    repriceRow(row, variant, 'sale')
    const baseUnit = Number(row.selected_item?.unitMeasure?.unit) || 1
    const selectedUnit = Number(row.selected_measure?.unit) || baseUnit
    row.unit_price = priceInLineUnit(row.base_unit_price, row.selected_item, row.selected_measure, form.rate)
    row.price_touched = false
    syncRowExpiry(row);
    notifyIfVariantNotInWarehouse(row);
    notifyIfDuplicate(index);
};


// A variant the selected warehouse holds none of: say so the moment it is
// picked, rather than leaving a bare zero in the on-hand column to be noticed.
const notifyIfVariantNotInWarehouse = (row) => {
    const variant = row?.selected_item_variant;
    if (!row?.selected_item || !variant) return;
    if (variantStockFigure(row, variant, 'on_hand') !== 0) return;

    toast({
        title: t('general.variant_not_in_warehouse_title'),
        description: t('general.variant_not_in_warehouse', {
            variant: variant.display_name || row.selected_item.name,
            item: row.selected_item.name,
            warehouse: form.selected_warehouse?.name || '',
        }),
        variant: 'destructive',
    });
};

// --- Expiry date ---------------------------------------------------------
// Chosen from the dates the selected warehouse holds, never typed. Left empty,
// the posting takes the nearest expiry first by itself.
const expiryOptions = (row) => expiryOptionsForRow(row, row?.selected_item_variant, row?.expire_date || '');
const selectedExpiry = (row) => (
    row?.expire_date ? expiryOptions(row).find((o) => o.expire_date === row.expire_date) || null : null
);
// After the batch or variant moves: keep a date that is still on offer, take
// the only one there is when a batch pins it down, otherwise leave it open.
const syncRowExpiry = (row, { autoPick = false } = {}) => {
    const options = expiryOptionsForRow(row, row?.selected_item_variant);
    if (options.some((o) => o.expire_date === row.expire_date)) return;
    row.expire_date = autoPick && options.length === 1 ? options[0].expire_date : '';
};
const handleExpiryChange = (index, option) => {
    const row = form.items[index];
    if (!row) return;
    row.expire_date = option?.expire_date || '';
    notifyIfDuplicate(index);
};

const handleBatchChange = (index, batch) => {
    const row = form.items[index];
    row.selected_batch = batch ?? null;
    row.batch = batch?.batch ?? '';
    syncRowExpiry(row, { autoPick: !!batch });
    row.on_hand = batch?.on_hand ?? row.selected_item?.on_hand ?? '';
    notifyIfDuplicate(index);
};

const onhand = (index) => {
    const item = form.items[index];
    if (!item || !item.selected_item) return '';

    // Per warehouse: a variant with nothing in the selected warehouse reads
    // zero instead of borrowing the item's total (the other variants' stock).
    const variantOnHand = variantStockFigure(item, item.selected_item_variant, 'on_hand');
    // A chosen expiry date narrows the line to that lot's stock.
    const onHandBase = Number(selectedExpiry(item)?.on_hand ?? variantOnHand ?? item.selected_batch?.on_hand ?? item.selected_item?.on_hand ?? item.on_hand) || 0;
    // A draft only reserves its stock, so what it already holds was never
    // taken off the on-hand figure and must not be added back to it.
    const currentQuantityBase = quantityOutInBaseUnits({
        quantity: item.quantity,
        free: item.free,
        selectedMeasure: item.selected_measure,
        selectedItem: item.selected_item,
    });
    const adjustedOnHandBase = onHandBase - currentQuantityBase;
    // Stock is stored in the base unit to 4 decimals; shown to 2 so a
    // converted 230.0004 reads 230, as on the create form.
    return stockInLineUnit(item, adjustedOnHandBase);
};

const reservedOut = (index) => {
    const item = form.items[index];
    if (!item || !item.selected_item) return 0;
    const variantReserved = variantStockFigure(item, item.selected_item_variant, 'reserved_out');
    // In the line's unit, like the on-hand figure beside it.
    return stockInLineUnit(item, variantReserved ?? item.selected_batch?.reserved_out ?? item.selected_item?.reserved_out ?? item.reserved_out);
};

const reservedIn = (index) => {
    const item = form.items[index];
    if (!item || !item.selected_item) return 0;
    return stockInLineUnit(item, item.selected_batch?.reserved_in ?? item.selected_item?.reserved_in ?? item.reserved_in);
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

// See repriceRowsForRate: a currency change converts every price, a rate
// change leaves the ones typed by hand. Rate and currency are watched together
// so one callback sees both and knows which of the two it was.
const setLinePrice = (row, value) => {
    row.unit_price = value;
    row.price_touched = true;
    row.base_unit_price = linePriceToHome(row, form.rate);
};
watch(() => [form.rate, form.currency_id], ([rate, currencyId], [, previousCurrencyId]) => {
    if (!Array.isArray(form.items)) return;
    repriceRowsForRate(form.items, rate, currencyId !== previousCurrencyId);
});

const displayedAverageCost = (row) => {
    if (!row?.selected_item) return 0;

    const baseUnit = Number(row.selected_item?.unitMeasure?.unit) || 1;
    const selectedUnit = Number(row.selected_measure?.unit) || baseUnit;
    // A selected variant is costed at its own average — the same figure the
    // posting relieves inventory at — so the below-cost warning cannot fire
    // against a blended item average the sale will never actually use.
    const avgCost = resolveVariantAverageCost(row.selected_item, row.selected_item_variant);
    return priceInLineUnit(avgCost, row.selected_item, row.selected_measure, form.rate);
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
// Each line at cents, the sum at cents: the server's formula, so the form and
// the posted bill agree (49 kg at 71.4286 is 3,500.00, not 3,500.0014).
const goodsTotal = computed(() => Number(form.items.reduce((acc, item) => acc + Number((toNum(item.unit_price, 0) * toNum(item.quantity, 0)).toFixed(2)), 0).toFixed(2)));
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
// What the customer owes for the whole bill: goods, less every discount.
const billNetTotal = computed(() => Number((goodsTotal.value - totalDiscount.value + totalTax.value).toFixed(decimalPlaces.value)));
const totalRowTotal = computed(() => Number(form.items.reduce((acc, item) => {
    return acc + (Number((toNum(item.unit_price, 0) * toNum(item.quantity, 0)).toFixed(2)) - toNum(item.item_discount, 0) + toNum(item.tax, 0));
}, 0).toFixed(decimalPlaces.value)));
const totalQuantity = computed(() => Number(form.items.reduce((acc, item) => acc + toNum(item.quantity, 0), 0).toFixed(decimalPlaces.value)));
const totalFree = computed(() => Number(form.items.reduce((acc, item) => acc + toNum(item.free, 0), 0).toFixed(decimalPlaces.value)));

const transactionSummary = computed(() => {
    const saleType = form.selected_sale_type?.id;
    const paid = toNum(form.payment?.amount, 0);
    let cashReceived = 0;
    if (saleType === 'cash') {
        cashReceived = goodsTotal.value - totalDiscount.value + totalTax.value; // the whole bill, after discount
    } else if (saleType === 'credit') {
        cashReceived = paid; // on loan = 0, default
    }
    else {
        cashReceived = 0;
    }
    const oldBalance = toNum(form?.selected_ledger?.statement?.balance, 0);
    const balanceNature = form?.selected_ledger?.statement?.balance_nature;
    const hasSelectedItem = Array.isArray(form.items) && form.items.some((row) => !!row.selected_item);
    const netAmount = goodsTotal.value - totalDiscount.value + totalTax.value;
    const receivableDelta = netAmount - cashReceived;
    const signedOldBalance = balanceNature === 'dr' ? oldBalance : -oldBalance;
    const signedBalance = hasSelectedItem ? signedOldBalance + receivableDelta : signedOldBalance;

    return {
        valueOfGoods: Number(goodsTotal.value.toFixed(decimalPlaces.value)),
        billDiscountPercent: billDiscountPercent.value,
        billDiscount: billDiscountCurrency.value,
        itemDiscount: totalItemDiscount.value,
        cashReceived: cashReceived,
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
            title: t('general.please_add_items'),
            description: t('general.please_add_at_least_one_item_to_update_sale'),
            variant: 'destructive',
            class: 'bg-yellow-600 text-white',
        });
        return;
    }

    form.item_list = selectedItems.map((item) => ({
        item_id: item.item_id,
        variant_id: item.variant_id || item.selected_item_variant?.id || null,
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

    // Only the lean `item_list` needs to reach the server — `items` and the
    // `selected_*` fields carry full nested objects (item catalog data,
    // batches, etc.) purely for UI binding and must never be POSTed, or
    // Laravel's wildcard validation on item_list.* can exhaust memory
    // flattening them (Arr::dot runs once per item_list.* rule).
    form.transform((data) => {
        const { items, selected_ledger, selected_currency, selected_sale_type, selected_bank_account, selected_warehouse, ...rest } = data
        return { ...rest, save_and_print: saveAndPrint }
    }).put(route('sales.update', saleRecord.id), {
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
        onError: (errors) => {
            cleanupPrintWindow();
            notifySound('error');
            toast({
                title: t('general.error'),
                description: firstServerError(errors) ?? t('general.update_error', { name: t('sale.sale') }),
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

useFormGuard(form)

/* Barcode scan / item search, as on the create form: scan or type to add a
 * line or bump the quantity of the one already there. F2 / Alt+B jumps here. */
const barcodeRef = ref(null);
const focusBarcode = () => barcodeRef.value?.focus?.();

const handleScannedItem = async (item) => {
    if (!item) return;
    // A plain (single-variant) line for the same item just increments.
    const existing = form.items.find((row) => row.selected_item && row.item_id === item.id && (row.item_variants?.length || 0) <= 1);
    if (existing) {
        existing.quantity = toNum(existing.quantity, 0) + 1;
    } else {
        let index = form.items.findIndex((row) => !row.selected_item);
        if (index === -1) {
            form.items.push(buildEmptyRow());
            index = form.items.length - 1;
        }
        form.items[index].selected_item = item;
        await handleItemChange(index, item);
        form.items[index].quantity = 1;
    }
    notifySound('success');
    focusBarcode();
};

const handleScanNotFound = (code) => {
    notifySound('error');
    toast({ title: t('general.barcode_not_found', { code }), variant: 'destructive' });
    focusBarcode();
};

const handleScanError = () => {
    notifySound('error');
    toast({ title: t('general.barcode_lookup_failed'), variant: 'destructive' });
    focusBarcode();
};

const handleGlobalKeydown = (event) => {
    if (event.key === 'F2' || (event.altKey && (event.code === 'KeyB' || event.key === 'b' || event.key === 'B'))) {
        event.preventDefault();
        focusBarcode();
    }
};
onMounted(() => window.addEventListener('keydown', handleGlobalKeydown));
onUnmounted(() => window.removeEventListener('keydown', handleGlobalKeydown));

// Enter walks a line: item -> variant -> quantity -> price -> next line.
const { onLineEnter, onCustomerEnter, onGridArrow, onNumberBeforeInput, onNumberPaste, onNumberKeydown } = useLineEnterNavigation({
    hasItem: (row) => !!form.items[row]?.selected_item,
    ensureRow: (row) => { while (form.items.length <= row) form.items.push(buildEmptyRow()); },
    focusBarcode,
    clearRow: (row) => resetRow(row),
});

// Ctrl+S, then Enter, saves. Until Ctrl+S, Enter belongs to the lines.
// With "confirm before save" on, Ctrl+S opens that confirmation and Enter
// answers it; with it off, Ctrl+S steps onto the Save button and Enter presses it.
const focusSaveButton = () => {
    const button = saveFormRef.value?.querySelector?.('button[type="submit"]');
    if (!button || button.disabled) return;
    const confirms = usePage().props?.user_preferences?.confirmations?.sale ?? true;
    if (confirms) button.click();
    else button.focus();
};
const saveFormRef = useSaveShortcut({ form, onSave: focusSaveButton });
</script>
<template>
    <AppLayout :title="t('general.edit', { name: t('sale.sale') })" :sidebar-collapsed="true">
        <FormPageToolbar
            back-route="sales.index"
            module="sales"
            guide="sales"
            :show-preferences="true"
            @preferences="showPreferencesPanel = true"
        />
        <FormPreferencesPanel module="sale"
            v-model:open="showPreferencesPanel"
            pref-group="sale"
            :prefs="salePrefs"
            :title="t('preferences.tabs.sale')"
        />
        <form ref="saveFormRef" @submit.prevent="handleSubmitAction('update')"
              @beforeinput.capture="onNumberBeforeInput"
              @paste.capture="onNumberPaste"
              @keydown.capture="onNumberKeydown">
            <div class="mb-5 rounded-xl border border-violet-500 p-4 shadow-sm relative">
                <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
                    {{ t('general.edit', { name: t('sale.sale') }) }}
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                    <div @keydown.enter="onCustomerEnter">
                    <NextSelect
                        :loading="lazyLoading"
                        :options="ledgers?.data || []"
                        v-model="form.selected_ledger"
                        @update:modelValue="(value) => handleSelectChange('customer_id', value)"
                        label-key="name"
                        value-key="id"
                        :reduce="(ledger) => ledger"
                        :floating-text="t('ledger.customer.customer')"
                        is-required
                        :error="form.errors?.customer_id"
                        :searchable="true"
                        resource-type="ledgers"
                        :search-fields="['name', 'email', 'phone_no']"
                        :search-options="{ type: 'customer' }"
                    />
                    </div>
                    <NextInput
                        is-required
                        v-if="generalFields.number"
                        v-model="form.number"
                        :placeholder="t('general.number')"
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
                            label-key="display_name"
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
                            :placeholder="t('general.rate')"
                            :error="form.errors?.rate"
                            type="number"
                            step="any"
                            :disabled="form.selected_currency?.is_base_currency === true"
                            :label="t('general.rate')"
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
                        is-required
                        :error="form.errors?.warehouse_id"
                        resource-type="warehouses"
                        :search-fields="['name', 'code', 'address']"
                    />
                    <NextTextarea
                        v-if="generalFields.description !== false"
                        v-model="form.description"
                        :label="t('general.description')"
                        :placeholder="t('general.enter', { text: t('general.description') })"
                        :error="form.errors?.description"
                    />
                </div>
            </div>

            <!-- Barcode scanner / item search: scan or type to add or increment a line -->
            <div class="mb-2 flex flex-wrap items-center gap-3">
                <ScanBarcode class="h-5 w-5 shrink-0 text-violet-500" />
                <div class="w-full max-w-xs">
                    <BarcodeSearchInput
                        ref="barcodeRef"
                        :warehouse-id="form.warehouse_id"
                        :disabled="!form.selected_ledger"
                        :disabled-hint="t('general.select_ledger_first')"
                        @select="handleScannedItem"
                        @not-found="handleScanNotFound"
                        @error="handleScanError"
                    />
                </div>
                <span class="hidden text-xs text-muted-foreground sm:inline">{{ t('general.sale_keyboard_hint') }}</span>
            </div>
            <div class="rounded-xl border bg-card shadow-sm border-violet-500">
                <table class="w-full table-fixed min-w-[1200px] sale-table border-separate" @keydown.enter="onLineEnter" @keydown.capture="onGridArrow">
                    <thead class="" :class="form.sale_type === 'cash' ? 'bg-card sticky top-0 z-10' : ''">
                        <tr class="rounded-xl text-muted-foreground font-semibold text-sm text-violet-500">
                            <th class="px-1 py-1 w-5 min-w-5 text-center">#</th>
                            <th class="px-1 py-1 w-40 min-w-64">{{ t('item.item') }} <span class="text-red-500">*</span></th>
                            <th class="px-1 py-1 w-36">{{ t('item.variant') }}</th>
                            <th class="px-1 py-1 w-32" v-if="itemColumns.batch">{{ t(specText) }}</th>
                            <th class="px-1 py-1 w-36" v-if="itemColumns.expiry">{{ t('general.expire_date') }}</th>
                            <th class="px-1 py-1 w-16">{{ t('general.qty') }} <span class="text-red-500">*</span></th>
                            <th class="px-1 py-1 w-24" v-if="itemColumns.on_hand">{{ t('general.on_hand') }}</th>
                            <th class="px-1 py-1 w-24" v-if="itemColumns.reserved_out">{{ t('general.reserved_out') }}</th>
                            <th class="px-1 py-1 w-24" v-if="itemColumns.measure">{{ t('general.unit') }} <span class="text-red-500">*</span></th>
                            <th class="px-1 py-1 w-24">{{ t('general.price') }} <span class="text-red-500">*</span></th>
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
                            <td class="px-1 py-2 align-top w-5 text-center">{{ index + 1 }}</td>
                            <td data-nav="item" :data-row="index" :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                                <NextSelect
                                    :options="itemOptions"
                                    v-model="item.selected_item"
                                    label-key="name"
                                    :placeholder="t('general.search_or_select')"
                                    id="item_id"
                                    :error="form.errors?.[`item_list.${index}.item_id`]"
                                    :show-arrow="false"
                                    :searchable="true"
                                    :has-add-button="false"
                                    resource-type="items-list"
                                    :search-fields="['name', 'code', 'generic_name', 'packing', 'barcode', 'fast_search']"
                                    value-key="id"
                                    :reduce="(itemValue) => itemValue"
                                    :search-options="itemSearchOptions"
                                    @update:modelValue="(value) => handleItemChange(index, value)"
                                />
                            </td>
                            <td data-nav="variant" :data-row="index" :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                                <VariantCell
                                    v-model="item.selected_item_variant"
                                    :item-variants="item.item_variants"
                                    :disabled="!item.selected_item"
                                    :id="`sale_edit_variant_${index}`"
                                    :error="form.errors?.[`item_list.${index}.variant_id`]"
                                    @update:modelValue="(value) => handleItemVariantChange(index, value)"
                                />
                            </td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }" v-if="itemColumns.batch">
                                <NextSelect
                                    :options="batchOptionsForRow(item)"
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
                                <NextSelect
                                    :options="expiryOptions(item)"
                                    :model-value="selectedExpiry(item)"
                                    label-key="label"
                                    value-key="expire_date"
                                    :placeholder="t('general.search_or_select')"
                                    :id="`sale_expiry_${index}`"
                                    :disabled="!item.selected_item"
                                    :error="form.errors?.[`item_list.${index}.expire_date`]"
                                    :show-arrow="false"
                                    :reduce="(expiry) => expiry"
                                    @update:modelValue="(value) => handleExpiryChange(index, value)"
                                />
                            </td>
                            <td data-nav="qty" :data-row="index" :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
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
                            <td class="text-center" v-if="itemColumns.reserved_out">
                                <span class="text-amber-600" :title="t('general.reserved_in') + ': ' + reservedIn(index)">{{ reservedOut(index) }}</span>
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
                                        form.items[index].unit_price = priceInLineUnit(baseUnitPrice, form.items[index]?.selected_item, measure, form.rate);
                                        form.items[index].price_touched = false;
                                        form.items[index].unit_measure_id = measure?.id || '';
                                        notifyIfDuplicate(index);
                                    }"
                                />
                            </td>
                            <td data-nav="price" :data-row="index" :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                                <div class="space-y-1">
                                    <NextInput
                                        :model-value="item.unit_price"
                                        @update:model-value="setLinePrice(item, $event)"
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
                                    :model-value="item.item_discount"
                                    @update:model-value="setLineDiscount(item, $event)"
                                    :disabled="!item?.selected_item"
                                    type="number"
                                    step="any"
                                    inputmode="decimal"
                                    :error="form.errors?.[`item_list.${index}.item_discount`]"
                                />
                                <!-- Why this figure is here, and the way back to it. -->
                                <div
                                    v-if="item.discount_rule && !item.discount_touched"
                                    class="mt-0.5 truncate text-xs text-violet-500"
                                    :title="item.discount_rule.name"
                                >
                                    {{ item.discount_rule.name }}
                                </div>
                                <button
                                    v-else-if="item.discount_touched && ruleWaitingFor(item)"
                                    type="button"
                                    class="mt-0.5 truncate text-xs text-muted-foreground underline hover:text-violet-500"
                                    @click="restoreRuleDiscount(item)"
                                >
                                    {{ t('discount_rule.restore', { name: ruleWaitingFor(item).name }) }}
                                </button>
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
                            <td></td>
                            <td v-if="itemColumns.batch"></td>
                            <td v-if="itemColumns.expiry"></td>
                            <td class="text-center">{{ totalQuantity || 0 }}</td>
                            <td v-if="itemColumns.on_hand"></td>
                            <td v-if="itemColumns.reserved_out"></td>
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
                <!-- Discount Summary -->
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-sm font-semibold mb-3 text-violet-500">{{ t('general.discount_summary') }}</div>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">{{ t('general.total_item_discount') }}</span>
                            <span class="tabular-nums text-sm">{{ fmtMoney(totalItemDiscount) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">{{ t('general.bill_disc') }}</span>
                            <span class="tabular-nums text-sm">{{ fmtMoney(billDiscountCurrency) }}</span>
                        </div>
                        <div class="flex items-center justify-between font-semibold pt-1">
                            <span class="text-sm">{{ t('general.total_discount') }}</span>
                            <span class="tabular-nums text-sm">{{ fmtMoney(totalDiscount) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Adjustments -->
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-sm font-semibold mb-3 text-violet-500">{{ t('general.tax_discount_summary') }}</div>
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-muted-foreground">{{ t('general.total_item_tax') }}</span>
                        <span class="tabular-nums text-sm">{{ fmtMoney(totalTax) }}</span>
                    </div>
                    <div>
                        <div class="text-xs font-medium uppercase tracking-wide text-muted-foreground mb-1">{{ t('general.bill_discount') }} ({{ form.discount_type === 'percentage' ? '%' : form.selected_currency?.symbol }})</div>
                        <DiscountField
                            v-model="form.discount"
                            v-model:discount-type="form.discount_type"
                            label=" "
                            :error="form.errors?.discount"
                        />
                    </div>
                </div>

                <!-- Payment -->
                 <div class="rounded-xl border bg-card p-4 shadow-sm space-y-4">
                    <div class="text-sm font-semibold text-violet-500">{{ t('general.payment') }}</div>
                    <div v-if="generalFields.type">
                        <NextSelect
                            :options="salePurchaseTypes"
                            v-model="form.selected_sale_type"
                            :floating-text="t('general.sale_type')"
                            :clearable="false"
                            @update:modelValue="(value) => handleSelectChange('sale_type', value)"
                            label-key="name"
                            value-key="id"
                            :reduce="salePurchaseType => salePurchaseType"
                            :error="form.errors?.sale_type"
                        />
                    </div>
                    <div v-if="form.sale_type === 'cash'">
                        <NextSelect
                            :options="bankAccounts"
                            :floating-text="t('general.cash_or_account')"
                            is-required
                            :clearable="false"
                            v-model="form.selected_bank_account"
                            @update:modelValue="(value) => handleSelectChange('bank_account_id', value)"
                            label-key="name"
                            :searchable="true"
                            :error="form.errors?.bank_account_id"
                            resource-type="accounts"
                            :search-fields="['name', 'number', 'slug']"
                            value-key="id"
                            :reduce="bankAccount => bankAccount"
                        />
                    </div>
                </div>

                <TransactionSummary
                    :summary="transactionSummary"
                    :balance-nature="form?.selected_ledger?.statement?.balance_nature"
                />
            </div>

            <div v-if="showAttachmentsField" class="mt-4">
                <AttachmentUploader v-model="form.attachments" :existing="existingAttachments" :label="t('general.attachments')" :error="form.errors['attachments.0']" @remove-existing="removeExistingAttachment" />
            </div>

            <SubmitButtons module="sale"
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
                :disabled="hasDuplicateRows"
                @save-and-print="handleSubmitAction('save_and_print')"
                @cancel="() => $inertia.visit(route('sales.index'))"
            />
        </form>

        <PaymentDialog
            :open="showPaymentDialog"
            :bill-total="billNetTotal"
            :payment="form.payment"
            :errors="form.errors"
            :accounts="bankAccounts || []"
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
