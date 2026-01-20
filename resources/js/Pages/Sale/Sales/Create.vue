<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { h, ref, watch, onMounted, onUnmounted, computed } from 'vue';
import axios from 'axios'
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
import { useSidebar } from '@/Components/ui/sidebar/utils';
import { ToastAction } from '@/Components/ui/toast'
import { useToast } from '@/Components/ui/toast/use-toast'
import NextDate from '@/Components/next/NextDatePicker.vue'
import { Trash2 } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';
const { t } = useI18n();
const showFilter = () => {
    showFilter.value = true;
}
const { toast } = useToast()

const props = defineProps({
    ledgers: {type: Object, required: true},
    salePurchaseTypes: {type: Object, required: true},
    currencies: {type: Object, required: true},
    stores: {type: Object, required: true},
    unitMeasures: {type: Object, required: true},
    accounts: {type: Object, required: true},
    saleNumber: {type: String, required: true},
    items: {type: Object, required: true},
    user_preferences: {type: Object, required: true},
})

const form = useForm({
    number: props.saleNumber,
    customer_id: '',
    date: '',
    currency_id: '',
    rate: '',
    transaction_type_id: '',
    selected_currency: '',
    selected_ledger: '',
    selected_transaction_type: '',
    discount: '',
    transaction_total: 0,
    discount_type: 'percentage',
    description: '',
    is_on_loan: false,
    payment:{
        method: '',
        amount: '',
        account_id: '',
        note: '',
    },
    status: '',
    store_id: '',
    selected_store: '',
    item_list:[],
    items: [
        {
            item_id: '',
            selected_item: '',
            quantity: '',
            unit_measure_id: '',
            batches: [],
            selected_batch: '',
            expire_date: '',
            unit_price: '',
            selected_measure: '',
            item_discount: '',
            free: '',
            tax: '',
        },
        {
            item_id: '',
            selected_item: '',
            quantity: '',
            unit_measure_id: '',
            batches: [],
            selected_batch: '',
            expire_date: '',
            unit_price: '',
            selected_measure: '',
            item_discount: '',
            free: '',
            tax: '',
        },
        {
            item_id: '',
            selected_item: '',
            quantity: '',
            unit_measure_id: '',
            batches: [],
            selected_batch: '',
            expire_date: '',
            unit_price: '',
            selected_measure: '',
            item_discount: '',
            free: '',
            tax: '',
        },
        {
            item_id: '',
            selected_item: '',
            quantity: '',
            unit_measure_id: '',
            batches: [],
            selected_batch: '',
            expire_date: '',
            unit_price: '',
            selected_measure: '',
            item_discount: '',
            free: '',
            tax: '',
        },
        {
            item_id: '',
            selected_item: '',
            quantity: '',
            unit_measure_id: '',
            batches: [],
            selected_batch: '',
            expire_date: '',
            unit_price: '',
            selected_measure: '',
            item_discount: '',
            free: '',
            tax: '',
        },
    ],
})

const itemOptions = ref([])

const itemSearchOptions = computed(() => {
    const additionalParams = {}
    if (form.store_id) {
        additionalParams.store_id = form.store_id
    }
    return { additionalParams, limit: 200 }
})

const loadItemOptions = async (storeId = form.store_id) => {
    if (!storeId) {
        itemOptions.value = []
        return
    }
    try {
        const response = await axios.get(route('search.items-for-sale'), {
            params: {
                store_id: storeId,
                limit: 50,
            }
        })
        itemOptions.value = response.data?.data || []
    } catch (error) {
        console.error('Failed to load items', error)
        itemOptions.value = []
    }
}

// Watch for purchaseNumber prop changes and update form.number
watch(() => props.saleNumber, (newPurchaseNumber) => {
    if (newPurchaseNumber) {
        form.number = newPurchaseNumber;
    }
}, { immediate: true });

watch(() => props.ledgers?.data, (ledgers) => {
    if (ledgers && !form.selected_ledger) {
        const baseLedger = ledgers.find(c => c.code === 'CASH-CUST');
        if (baseLedger) {
            form.selected_ledger = baseLedger;
            form.customer_id = baseLedger.id;
        }
    }
}, { immediate: true });

// Set base currency as default
watch(() => props.currencies?.data, (currencies) => {
    if (currencies && !form.currency_id) {
        const baseCurrency = currencies.find(c => c.is_base_currency);
        if (baseCurrency) {
            form.selected_currency = baseCurrency;
            form.rate = baseCurrency.exchange_rate;
            form.currency_id = baseCurrency.id;
        }
    }
}, { immediate: true });


watch(() => props.salePurchaseTypes, (salePurchaseTypes) => {
    if (salePurchaseTypes && !form.selected_transaction_type) {
        const baseSalePurchaseType = salePurchaseTypes.find(c => c.id === 'cash');
        if (baseSalePurchaseType) {
            form.selected_transaction_type = baseSalePurchaseType;
            form.transaction_type_id = baseSalePurchaseType.id;
        }
    }
}, { immediate: true });

watch(() => props.stores.data, (stores) => {
    if (stores && !form.selected_store) {
        const baseStore = stores.find(c => c.is_main === true);
        if (baseStore) {
            form.selected_store = baseStore;
            form.store_id = baseStore.id;
        }
    }
}, { immediate: true });

watch(() => form.store_id, (storeId) => {
    if (!storeId) {
        itemOptions.value = []
        return
    }
    loadItemOptions(storeId)
}, { immediate: true });

// Payment dialog state
const showPaymentDialog = ref(false);

// Watch for sale/purchase type changes and show payment dialog for credit transactions
watch(() => form.selected_transaction_type, (newType) => {
    if (newType && newType === 'credit') {
        showPaymentDialog.value = true;
    }
});

let disabled = (false);
const submitAction = ref(null);

const handleSubmitAction = (createAndNew = false) => {
    submitAction.value = createAndNew ? 'create_and_new' : 'create';
    handleSubmit(createAndNew);
};

const createLoading = computed(() => form.processing && submitAction.value === 'create');
const createAndNewLoading = computed(() => form.processing && submitAction.value === 'create_and_new');

const handleResetPayment = () => {
    form.payment = {
        method: '',
        amount: '',
        account_id: '',
        note: '',
    }
}
const handleSelectChange = (field, value) => {
    if(field === 'currency_id') {
        form.rate = value?.exchange_rate;
    }
    if(field === 'transaction_type_id' && value === 'cash') {
        handleResetPayment();
    }
    if(field === 'transaction_type_id') {
        console.log('this is value',value)
        form.transaction_type_id = value;
    }
    else{
        form[field] = value.id;
    }
};

const storeChange = (value) => {
    loadItemOptions(value);
    form.store_id = value;
    form.items.forEach(item => {
        item.selected_item = '';
        item.selected_batch = '';
        item.expire_date = '';
        item.quantity = '';
        item.unit_price = '';
        item.selected_measure = '';
        item.item_discount = '';
    });
}


function handleSubmit(createAndNew = false) {

    if(form.items[0]?.selected_item === '' || form.items[0]?.selected_item === null) {
        notifySound('error');
        toast({
            title: t('general.please_add_items'),
            description: t('general.please_add_at_least_one_item_to_create_sale'),
            variant: 'destructive',
            class:'bg-yellow-600 text-white',
        })
        return;
    }

    else{
        const FormItems = form.items.filter(item => item.selected_item && item.item_id);
        form.item_list = FormItems;
        form.transaction_total = toNum(goodsTotal.value - totalDiscount.value + totalTax.value);
        // Filter out empty items and set unit_measure_id
        form.item_list.forEach(item => {
            item.unit_measure_id = item.selected_measure.id;
        });
    }
    if (createAndNew) {
        form.transform((data) => ({ ...data, create_and_new: true })).post(route('sales.store'), {
            onSuccess: () => {
                form.reset();
                notifySound('success');
                const currentNumber = Number(form.number ?? props.saleNumber ?? 0);
                const nextNumber = isNaN(currentNumber) ? 0 : currentNumber + 1;
                form.number = (nextNumber);
                // Re-initialize currency field with default
                if (props.currencies?.data) {
                    const baseCurrency = props.currencies.data.find(c => c.is_base_currency);
                    if (baseCurrency) {
                        form.selected_currency = baseCurrency;
                        form.rate = baseCurrency.exchange_rate;
                        form.currency_id = baseCurrency.id;
                    }
                }
                if(props.ledgers?.data) {
                    const baseLedger = props.ledgers.data.find(c => c.code === 'CASH-CUST');
                    if (baseLedger) {
                        form.selected_ledger = baseLedger;
                        form.customer_id = baseLedger.id;
                    }
                }

                form.date = new Date().toISOString().split('T')[0];
                // Re-initialize sale_purchase_type with default
                if (props.salePurchaseTypes) {
                    const baseSalePurchaseType = props.salePurchaseTypes.find(c => c.id === 'cash');
                    if (baseSalePurchaseType) {
                        form.selected_transaction_type = baseSalePurchaseType;
                        form.transaction_type_id = baseSalePurchaseType.id;
                    }
                }
                // Re-initialize store with default
                if (props.stores?.data) {
                    const baseStore = props.stores.data.find(c => c.is_main === true);
                    if (baseStore) {
                        form.selected_store = baseStore;
                        form.store_id = baseStore.id;
                    }
                }
                toast({
                    title: t('general.success'),
                    description: t('general.create_success', { name: t('sale.sale') }),
                    variant: 'success',
                    class:'bg-green-600 text-white',
                })
            },
            onError: () => {
                notifySound('error');
                toast({
                    title: t('general.error'),
                    description: t('general.create_error', { name: t('sale.sale') }),
                    variant: 'destructive',
                    class:'bg-pink-600 text-white',
                })
            }
        })
        } else {
            form.post(route('sales.store'), {
            onSuccess: () => {
                notifySound('success');
                toast({
                    title: t('general.success'),
                    description: t('general.create_success', { name: t('sale.sale') }),
                    variant: 'success',
                    class:'bg-green-600 text-white',
                })
            },
            onError: () => {
                notifySound('error');
                toast({
                        title: t('general.error'),
                    description: t('general.create_error', { name: t('sale.sale') }),
                    variant: 'destructive',
                    class:'bg-pink-600 text-white',
                })
            }
        })
    }
}


// Payment dialog handlers
const handlePaymentDialogConfirm = () => {
    // Payment data is already updated in the form.payment object via the dialog's update:payment event
    showPaymentDialog.value = false;
};

const handlePaymentDialogCancel = () => {
    // Reset the sale/purchase type back to debit when dialog is cancelled
    if (props.salePurchaseTypes) {
        const debitType = props.salePurchaseTypes.find(type => type.id === 'cash');
        if (debitType) {
            form.selected_transaction_type = debitType;
        }
    }
    showPaymentDialog.value = false;
};



// Recalculate item unit prices when currency rate changes
watch(() => form.rate, (newRate) => {
    if (!Array.isArray(form.items)) return
    form.items.forEach((row) => {
        if (!row || !row.selected_item) return
        const baseUnit = Number(row.selected_item?.unitMeasure?.unit) || 1
        const selectedUnit = Number(row.selected_measure?.unit) || baseUnit
        const baseUnitPrice = Number(row.base_unit_price ?? row.selected_item?.unit_price ?? row.selected_item?.sale_price ?? 0)
        row.unit_price = (baseUnitPrice / (selectedUnit || 1)) * (Number(newRate) || 0)
    })
})

const notifySound = (type) => {
    if(type === 'success') {
        const sound = new Audio('/notify_sounds/filling-your-inbox.mp3');
        sound.play().catch(error => console.error('Error playing sound:', error));
    }
    else {
        const sound = new Audio('/notify_sounds/glass-breaking.mp3');
        sound.play().catch(error => console.error('Error playing sound:', error));
    }
}

const handleItemChange = async (index, selectedItem) => {
    const row = form.items[index]
    if (!row || !selectedItem){
        row.available_measures = []
        row.selected_measure = ''
        row.unit_price = ''
        row.quantity = ''
        row.batch = ''
        row.selected_batch = ''
        row.expire_date = ''
        row.discount = ''
        row.free = ''
        row.tax = ''
        return
    }
    row.batches = selectedItem.batches || []
    // Build available measures robustly by matching quantity id
    const selUM = selectedItem?.unitMeasure || {}
    const selectedQuantityId = selUM.quantity_id ?? selUM.quantity?.id
    const selectedQuantityName = (selUM.quantity?.name || selUM.quantity?.code || '').toString().toLowerCase()
    row.available_measures = (props.unitMeasures?.data || []).filter(unit => {
        const unitQtyId = unit?.quantity_id ?? unit?.quantity?.id
        const unitQtyName = (unit?.quantity?.name || unit?.quantity?.code || '').toString().toLowerCase()
        return (selectedQuantityId && unitQtyId === selectedQuantityId) || (!!selectedQuantityName && unitQtyName === selectedQuantityName)
    })
    row.selected_measure = selectedItem.unitMeasure
    row.item_id = selectedItem.id
    row.on_hand = (selectedItem.on_hand * selectedItem.unitMeasure?.unit)/(selectedItem.unitMeasure?.unit) - row.quantity;
    // Set the base unit price - this is the price per base unit
    row.base_unit_price = selectedItem.unit_price ?? selectedItem.sale_price ?? 0

    // Set the initial unit_price based on the base unit measure
    const baseUnit = Number(selectedItem.unitMeasure?.unit) || 1
    row.unit_price = (row.base_unit_price * Number(row.selected_measure.unit)*form.rate)/baseUnit;

    // Add a new empty row only when selecting into the last row
    if (index === form.items.length - 1) {
        addRow()
    }

    notifyIfDuplicate(index)
}

const handleBatchChange = (index, batch) => {
    const row = form.items[index]
    row.batch = batch?.batch
    row.expire_date = batch?.expire_date
}
const isRowEnabled = (index) => {
    if (!form.selected_ledger) return false
    for (let i = 0; i < index; i++) {
        if (!form.items[i]?.selected_item) return false
    }
    return true
}

const buildRowKey = (r) => {
    const measureId = r?.selected_measure?.id
        || (typeof r?.selected_measure === 'object' ? (r?.selected_measure?.name || r?.selected_measure?.unit) : r?.selected_measure)
        || ''
    return [
        (r.item_id || r.selected_item?.id || '').toString(),
        (r.batch || '').toString().trim().toLowerCase(),
        (r.expire_date || '').toString().trim(),
        (r?.selected_measure?.id || r?.selected_measure?.id || '').toString()
    ].join('|')
}

const isDuplicateRow = (index) => {
    const r = form.items[index]
    if (!r || !r.selected_item || !r.selected_measure) return false
    const key = buildRowKey(r)
    let count = 0
    for (let i = 0; i < form.items.length; i++) {
        const x = form.items[i]
        const xkey = buildRowKey(x)
        if (key === xkey) count++
        if (count > 1) return true
    }
    return false
}

const resetRow = (index) => {
    const r = form.items[index]
    if (!r) return
    r.selected_item = ''
    r.item_id = ''
    r.selected_measure = ''
    r.available_measures = []
    r.batch = ''
    r.expire_date = ''
    r.quantity = ''
    r.unit_price = ''
    r.base_unit_price = ''
    disabled =false;
}

const notifyIfDuplicate = (index) => {
    if (isDuplicateRow(index)) {
        const item = form.items[index]
        const batchText = item.batch ? `Batch: ${item.batch}` : 'No batch'
        const expiryText = item.expire_date ? `Expiry: ${item.expire_date}` : 'No expiry'
        disabled =true;
        toast({
            title: 'Duplicate item detected',
            description: `Same item with ${batchText} and ${expiryText} already exists.`,
            variant: 'destructive',
            class:'bg-pink-600 text-white',
            duration: Infinity,
            action: h(ToastAction, { altText: 'Unselect', onClick: () => resetRow(index) }, { default: () => 'Unselect' }),
        })
    }
}

const onhand = (index) => {
    const item = form.items[index]
    if (!item || !item.selected_item) return ''
    const baseUnit = Number(item.selected_item?.unitMeasure?.unit) || 1
    const selectedUnit = Number(item.selected_measure?.unit) || baseUnit
    const onHand = item.selected_batch ? Number(item.selected_batch.on_hand) : Number(item.on_hand) || 0
    const converted = (onHand * baseUnit) / selectedUnit
    const free = Number(item.free) || 0
    const qty = Number(item.quantity) || 0
    return converted - free - qty;
}

const toNum = (v, d = 0) => {
    const n = Number(v)
    return isNaN(n) ? d : n
}

const rowTotal = (index) => {
    const item = form.items[index]
    if (!item || !item.selected_item) return ''
    const qty = toNum(item.quantity, 0)
    const price = toNum(item.unit_price, 0)
    const disc = toNum(item.item_discount, 0)
    const tax = toNum(item.tax, 0)
    return qty * price - disc + tax
}

const deleteRow = (index) => {
    if(form.items.length === 1) return;
    form.items.splice(index, 1)
}

const totalRows = computed(() => form.items.length)
const totalItemDiscount = computed(() => form.items.reduce((acc, item) => acc + toNum(item.item_discount, 0), 0))
const totalTax = computed(() => form.items.reduce((acc, item) => acc + toNum(item.tax, 0), 0))
// Value of goods (sum of qty * price)
const goodsTotal = computed(() => form.items.reduce((acc, item) => acc + (toNum(item.unit_price, 0) * toNum(item.quantity, 0)), 0))
// Bill discount currency and percent
const billDiscountCurrency = computed(() => {
    const billDisc = toNum(form.discount, 0)
    if (form.discount_type === 'percentage') {
        return goodsTotal.value * (billDisc / 100)
    }
    return billDisc
})
const billDiscountPercent = computed(() => {
    const billDisc = toNum(form.discount, 0)
    if (form.discount_type === 'percentage') {
        return billDisc
    }
    const gt = goodsTotal.value
    return gt > 0 ? (billDisc / gt) * 100 : 0
})
const totalDiscount = computed(() => billDiscountCurrency.value + totalItemDiscount.value)
const totalRowTotal = computed(() => form.items.reduce((acc, item) => acc + (toNum(item.unit_price, 0) * toNum(item.quantity, 0) - toNum(item.item_discount, 0) + toNum(item.tax, 0)), 0))
const totalQuantity = computed(() => form.items.reduce((acc, item) => acc + toNum(item.quantity, 0), 0))
const totalFree = computed(() => form.items.reduce((acc, item) => acc + toNum(item.free, 0), 0))

// Transaction summary for card (spec-compliant)
const transactionSummary = computed(() => {
    const paid = toNum(form.payment.amount, 0)
    const oldBalance = toNum(form?.selected_ledger?.statement?.balance, 0)
    const nature = form?.selected_ledger?.statement?.balance_nature // 'Dr' | 'Cr'
    const hasSelectedItem = Array.isArray(form.items) && form.items.some(r => !!r.selected_item)
    const netAmount = goodsTotal.value - totalDiscount.value + totalTax.value
    const grandTotal = netAmount - paid;
    const balance = hasSelectedItem
        ? (nature === 'dr' ? (grandTotal + oldBalance) : (grandTotal - oldBalance))
        : 0
    return {
        valueOfGoods: goodsTotal.value,
        billDiscountPercent: billDiscountPercent.value,
        billDiscount: billDiscountCurrency.value,
        itemDiscount: totalItemDiscount.value,
        cashReceived: paid,
        balance: balance,
        grandTotal: grandTotal,
        oldBalance: oldBalance,
        balanceNature: nature,
        currencySymbol: form.selected_currency?.symbol,
    }
})

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
    })
}

const user_preferences = computed(() => props.user_preferences?.data ?? props.user_preferences ?? [])
const general_fields = computed(() =>  user_preferences.value?.sale.general_fields ?? user_preferences.value.sale.general_fields ?? []).value
const item_columns = computed(() => user_preferences.value?.sale.item_columns ?? user_preferences.value.sale.item_columns ?? []).value
const sale_preferences = computed(() => user_preferences.value?.sale ?? user_preferences.value.sale ?? []).value
const item_management = computed(() => user_preferences.value?.item_management ?? user_preferences.value.item_management ?? []).value
const spec_text = computed(() => item_management?.spec_text ?? item_management?.spec_text ?? 'batch').value

</script>

<template>
    <AppLayout :title="t('general.create', { name: t('sale.sale') })" :sidebar-collapsed="true">
         <form @submit.prevent="handleSubmitAction">
            <div class="mb-5 rounded-xl border border-violet-500 p-4 shadow-sm relative ">
            <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">{{ t('general.create', { name: t('sale.sale') }) }}</div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                <NextSelect
                    :options="ledgers.data"
                    v-model="form.selected_ledger"
                    @update:modelValue="(value) => handleSelectChange('customer_id', value)"
                    label-key="name"
                    value-key="id"
                    :reduce="ledger => ledger"
                    :floating-text="t('ledger.customer.customer')"
                    :error="form.errors?.customer_id"
                    :searchable="true"
                    resource-type="ledgers"
                    :search-fields="['name', 'email', 'phone_no']"
                    :search-options="{ type: 'supplier' }"
                />

                <NextInput placeholder="Number"  v-if="general_fields.number" :error="form.errors?.number" type="number" v-model="form.number" :label="t('general.bill_number')" />
                <NextDate v-if="general_fields.date" v-model="form.date" :current-date="true" :error="form.errors?.date" :placeholder="t('general.enter', { text: t('general.date') })" :label="t('general.date')" />
                <NextSelect
                    v-if="general_fields.currency"
                    :options="currencies.data"
                    v-model="form.selected_currency"
                    label-key="code"
                    value-key="id"
                    :clearable="false"
                    @update:modelValue="(value) => handleSelectChange('currency_id', value)"
                    :reduce="currency => currency"
                   :floating-text="t('admin.currency.currency')"
                    :error="form.errors?.currency_id"
                    :searchable="true"
                    resource-type="currencies"
                    :search-fields="['name', 'code', 'symbol']"
                />
                <NextInput placeholder="Rate" v-if="general_fields.currency" :error="form.errors?.rate" type="number" step="any" v-model="form.rate" :label="t('general.rate')"/>
                <NextSelect
                    :options="salePurchaseTypes"
                    v-if="general_fields.type"
                    v-model="form.selected_transaction_type"
                    :clearable="false"
                    @update:modelValue="(value) => { handleSelectChange('transaction_type_id', value) }"
                    label-key="name"
                    value-key="id"
                    :reduce="salePurchaseType => salePurchaseType.id"
                    :floating-text="t('general.payment_type')"
                    :error="form.errors?.transaction_type_id"
                />
                <NextSelect
                    v-if="general_fields.store"
                    :options="stores.data"
                    v-model="form.selected_store"
                    :clearable="false"
                    @update:modelValue="(value) => storeChange(value)"
                    label-key="name"
                    value-key="id"
                    :reduce="store => store.id"
                    :floating-text="t('admin.store.store')"
                    :error="form.errors?.store_id"
                    :searchable="true"
                    resource-type="stores"
                    :search-fields="['name', 'code', 'address']"
                />
            </div>
            </div>
            <div class="rounded-xl border bg-card shadow-sm overflow-x-auto max-h-80">
                <table class="w-full table-fixed min-w-[1000px] purchase-table border-separate">
                    <thead class=" " :class="form.transaction_type_id === 'cash' ? 'bg-card sticky top-0 z-[200]' : ''">
                        <tr class="rounded-xltext-muted-foreground font-semibold text-sm text-violet-500">
                            <th class="px-1 py-1 w-5 min-w-5">#</th>
                            <th class="px-1 py-1 w-40 min-w-64">{{ t('item.item') }}</th>
                            <th class="px-1 py-1 w-32" v-if="item_columns.batch">{{ t(spec_text) }}</th>
                            <th class="px-1 py-1 w-36" v-if="item_columns.expiry">{{ t('general.expire_date') }}</th>
                            <th class="px-1 py-1 w-16">{{ t('general.qty') }}</th>
                            <th class="px-1 py-1 w-24" v-if="item_columns.on_hand">{{ t('general.on_hand') }}</th>
                            <th class="px-1 py-1 w-24" v-if="item_columns.measure">{{ t('general.unit') }}</th>
                            <th class="px-1 py-1 w-24">{{ t('general.price') }}</th>
                            <th class="px-1 py-1 w-24" v-if="item_columns.discount">{{ t('general.discount') }}</th>
                            <th class="px-1 py-1 w-16" v-if="item_columns.free">{{ t('general.free') }}</th>
                            <th class="px-1 py-1 w-16" v-if="item_columns.tax">{{ t('general.tax') }}</th>
                            <th class="px-1 py-1 w-16">{{ t('general.total') }}</th>
                            <th class="px-1 py-1 w-10  ">
                                <Trash2 class="w-4 h-4 text-fuchsia-700 inline" />
                            </th>
                        </tr>
                    </thead>
                    <tbody class="p-2 ">
                        <tr v-for="(item, index) in form.items" :key="item.id" class="hover:bg-muted/40 transition-colors">
                            <td class="px-1 py-2 align-top w-5">{{ index + 1 }}</td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                                <NextSelect
                                    :options="itemOptions"
                                    v-model="item.selected_item"
                                    label-key="name"
                                    :placeholder="t('general.search_or_select')"
                                    id="item_id"
                                    :error="form.errors?.item_id"
                                    :show-arrow="false"
                                    :searchable="true"
                                    resource-type="items-for-sale"
                                    :search-fields="['name', 'code', 'generic_name', 'packing', 'barcode','fast_search']"
                                    :search-options="itemSearchOptions"
                                    value-key="id"
                                    :reduce="item => item"
                                    @update:modelValue=" value => { handleItemChange(index, value) }"
                                />
                            </td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }" v-if="item_columns.batch">
                                <NextSelect
                                    :options="item.selected_item?.batches"
                                    v-model="item.selected_batch"
                                    label-key="batch"
                                    :placeholder="t('general.search_or_select')"
                                    id="batch_id"
                                    :error="form.errors?.[`item_list.${index}.batch`]"
                                    :show-arrow="false"
                                    value-key="batch"
                                    :reduce="batch => batch"
                                    @update:modelValue=" value => { handleBatchChange(index, value); }"
                                />
                            </td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none relative relative wq': !isRowEnabled(index) }" v-if="item_columns.expiry">
                                <NextDate v-model="item.expire_date"
                                disabled='true'
                                :popover="popover"
                                :error="form.errors?.[`item_list.${index}.expire_date`]"   />
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
                            <td class="text-center" v-if="item_columns.on_hand">
                                 <span :title="String(onhand(index))">{{ Number(onhand(index)).toFixed(1) }}</span>
                            </td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                                <NextSelect
                                    v-if="item_columns.measure"
                                    :options="item.available_measures"
                                    v-model="item.selected_measure"
                                    label-key="name"
                                    :error="form.errors?.[`item_list.${index}.unit_measure_id`]"
                                    value-key="id"
                                    :show-arrow="false"
                                    :reduce="unit => unit"
                                    @update:modelValue="(measure) => {
                                        const baseUnit = Number(form.items[index]?.selected_item?.unitMeasure?.unit) || 1
                                        const selectedUnit = Number(measure?.unit) || baseUnit
                                        const baseUnitPrice = Number(form.items[index]?.base_unit_price) || 0
                                        form.items[index].unit_price = (baseUnitPrice * selectedUnit*form.rate)/baseUnit;

                                        notifyIfDuplicate(index)
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
                            <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }" v-if="item_columns.discount">
                                <NextInput
                                    v-model="item.item_discount"
                                    :disabled="!item.selected_item"
                                    type="number" step="any"
                                    inputmode="decimal"
                                    :error="form.errors?.[`item_list.${index}.item_discount`]"
                                />
                            </td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }" v-if="item_columns.free">
                                <NextInput
                                    v-model="item.free"
                                    :disabled="!item.selected_item"
                                    type="number" step="any"
                                    inputmode="decimal"
                                    :error="form.errors?.[`item_list.${index}.free`]"
                                />
                            </td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }" v-if="item_columns.tax">
                                <NextInput
                                    v-model="item.tax"
                                    :disabled="!item.selected_item"
                                    type="number" step="any"
                                    inputmode="decimal"
                                    :error="form.errors?.[`item_list.${index}.tax`]"
                                />
                            </td>
                            <td class="text-center">
                                 {{ rowTotal(index) }} {{ item.selected_item?transactionSummary?.currencySymbol:'' }}
                            </td>
                            <td class="w-10 text-center">
                                <Trash2 class="w-4 h-4 cursor-pointer text-fuchsia-500 inline" @click="deleteRow(index)" />
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="sticky bottom-0 bg-card">
                        <tr class="bg-violet-500/10 hover:bg-violet-500/30 transition-colors">
                            <!-- #: blank to align -->
                            <td></td>
                            <!-- Item total centered across item column -->
                            <td class="text-center">{{ totalRows }}</td>
                            <!-- Batch, Expiry blank -->
                            <td v-if="item_columns.batch"></td>
                            <td v-if="item_columns.expiry"></td>
                            <!-- Qty total centered -->
                            <td class="text-center">{{ totalQuantity || 0 }}</td>
                            <!-- On hand blank -->
                            <td v-if="item_columns.on_hand"></td>
                            <!-- Unit blank -->
                            <td v-if="item_columns.measure"></td>
                            <!-- Value of goods (qty*price) total centered -->
                            <td class="text-center">{{ goodsTotal || 0 }}</td>
                            <!-- Discount total centered -->
                            <td class="text-center" v-if="item_columns.discount">{{ totalItemDiscount || 0 }}</td>
                            <!-- Free total centered -->
                            <td class="text-center" v-if="item_columns.free">{{ totalFree || 0 }}</td>
                            <!-- Tax total centered -->
                            <td class="text-center" v-if="item_columns.tax" >{{ totalTax || 0 }}</td>
                            <!-- Grand total centered -->
                            <td class="text-center">{{ totalRowTotal || 0 }}</td>
                            <!-- Delete column blank -->
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="mt-5 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-2 items-start">
                <DiscountSummary :summary="form.summary" :total-item-discount="totalItemDiscount" :bill-discount="billDiscountCurrency" :total-discount="totalDiscount" />
                <TaxSummary :summary="form.summary" :total-item-tax="totalTax" />
                <div class="rounded-xl p-4">
                    <div class="text-sm font-semibold mb-3 text-violet-500 text-sm">{{t('general.bill_discount')}}</div>
                    <DiscountField
                        v-model="form.discount"
                        v-model:discount-type="form.discount_type"
                        :error="form.errors?.discount"
                    />
                </div>
                <TransactionSummary :summary="transactionSummary" :balance-nature="form?.selected_ledger?.statement?.balance_nature" />
            </div>

            <SubmitButtons
                :create-label="t('general.create')"
                :create-and-new-label="t('general.create_and_new')"
                :cancel-label="t('general.cancel')"
                :creating-label="t('general.creating', { name: t('sale.sale') })"
                :create-loading="createLoading"
                :create-and-new-loading="createAndNewLoading"
                :disabled="disabled"
                @create-and-new="handleSubmitAction(true)"
                @cancel="() => $inertia.visit(route('sales.index'))"
            />

         </form>

         <!-- Payment Dialog for Credit Transactions -->
         <PaymentDialog
                 :open="showPaymentDialog"
                 :payment="form.payment"
                 :errors="form.errors"
                 :accounts="props.accounts?.data || []"
                 :submitting="false"
                :billTotal="transactionSummary.valueOfGoods"
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
