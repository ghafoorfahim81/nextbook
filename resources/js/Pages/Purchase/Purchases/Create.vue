<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import DataTable from '@/Components/DataTable.vue';
import { h, ref, watch, onMounted, onUnmounted, computed } from 'vue';
import axios from 'axios'
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
import { Spinner } from "@/components/ui/spinner";
const { t } = useI18n();
const showFilter = () => {
    showFilter.value = true;
}


const { toast } = useToast()

const props = defineProps({
    ledgers: {type: Object, required: true},
    salePurchaseTypes: {type: Object, required: true},
    currencies: {type: Object, required: true},
    items: {type: Object, required: true},
    stores: {type: Object, required: true},
    unitMeasures: {type: Object, required: true},
    accounts: {type: Object, required: true},
    purchaseNumber: {type: String, required: true},
})

const form = useForm({
    number: props.purchaseNumber,
    supplier_id: '',
    date: '',
    currency_id: '',
    rate: '',
    sale_purchase_type_id: '',
    selected_currency: '',
    selected_ledger: '',
    selected_sale_purchase_type: '',
    discount: '',
    transaction_total: 0,
    discount_type: 'percentage',
    description: '',
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
            batch: '',
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
            batch: '',
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
            batch: '',
            expire_date: '',
            unit_price: '',
            selected_measure: '',
            item_discount: '',
            free: '',
            tax: '',
        },
    ],
})

// Watch for purchaseNumber prop changes and update form.number
watch(() => props.purchaseNumber, (newPurchaseNumber) => {
    if (newPurchaseNumber) {
        form.number = newPurchaseNumber;
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

// Watch for currency changes and automatically update rate
// watch(() => form.currency_id, (newCurrencyId) => {
//     if (newCurrencyId && props.currencies?.data) {
//         const selectedCurrency = props.currencies.data.find(currency => currency.id === newCurrencyId);
//         if (selectedCurrency && selectedCurrency.exchange_rate) {
//             form.rate = selectedCurrency.exchange_rate;
//             form.currency_id = selectedCurrency.id;
//             form.selected_currency = selectedCurrency;
//         }
//     }
// });

watch(() => props.salePurchaseTypes, (salePurchaseTypes) => {
    if (salePurchaseTypes && !form.selected_sale_purchase_type) {
        const baseSalePurchaseType = salePurchaseTypes.find(c => c.id === 'cash');
        if (baseSalePurchaseType) {
            form.selected_sale_purchase_type = baseSalePurchaseType;
            form.sale_purchase_type_id = baseSalePurchaseType.id;
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

// Payment dialog state
const showPaymentDialog = ref(false);

// Watch for sale/purchase type changes and show payment dialog for credit transactions
watch(() => form.selected_sale_purchase_type, (newType) => {
    if (newType && newType === 'credit') {
        showPaymentDialog.value = true;
    }
});

let disabled = (false);

const handleSelectChange = (field, value) => {
    console.log('value 123', value);
    if(field === 'currency_id') {
        form.rate = value.exchange_rate;
    }

    form[field] = value;
};


function handleSubmit(createAndNew = false) {
    if(form.items[0]?.selected_item === '' || form.items[0]?.selected_item === null) {
        notifySound('error');
        toast({
            title: 'Please add items',
            description: 'Please add at least one item to create a purchase',
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
        form.transform((data) => ({ ...data, create_and_new: true })).post(route('purchases.store'), {
            onSuccess: () => {
                form.reset();
                notifySound('success');
                const currentNumber = Number(form.number ?? props.purchaseNumber ?? 0);
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
                // Re-initialize sale_purchase_type with default
                if (props.salePurchaseTypes) {
                    const baseSalePurchaseType = props.salePurchaseTypes.find(c => c.id === 'cash');
                    if (baseSalePurchaseType) {
                        form.selected_sale_purchase_type = baseSalePurchaseType;
                        form.sale_purchase_type_id = baseSalePurchaseType.id;
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
                    title: 'Success',
                    description: 'Purchase created successfully',
                    variant: 'success',
                    class:'bg-green-600 text-white',
                })
            },
            onError: () => {
                notifySound('error');
                toast({
                    title: 'Error creating purchase',
                    description: 'Error creating purchase',
                    variant: 'destructive',
                    class:'bg-pink-600 text-white',
                })
            }
        })
        } else {
            form.post(route('purchases.store'), {
            onSuccess: () => {
                notifySound('success');

                toast({
                    title: 'Purchase created successfully',
                    description: 'Purchase created successfully',
                    variant: 'success',
                    class:'bg-green-600 text-white',
                })
            },
            onError: () => {
                notifySound('error');
                toast({
                    title: 'Error creating purchase',
                    description: 'Error creating purchase',
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

        const debitType = props.salePurchaseTypes.find(type => type.id === 'debit');
        if (debitType) {
            form.selected_sale_purchase_type = debitType;
        }
    }
    showPaymentDialog.value = false;
};

// Collapse sidebar while on this page, restore on leave (safe if provider missing)
let sidebar = null
try {
    sidebar = useSidebar()
} catch (e) {
    sidebar = null
}
const prevSidebarOpen = ref(true)
onMounted(() => {
    if (sidebar) {
        prevSidebarOpen.value = sidebar.open.value
        sidebar.setOpen(false)
    }
    // Auto-generate bill number: latest + 1
    ;(async () => {
        // try {
        //     const { data } = await axios.get('/purchases/latest-number')
        //     const latest = Number((data && (data.number ?? data.latest ?? data.data)) ?? 0)
        //     if (!isNaN(latest) && latest >= 0) {
        //         form.number = String(latest + 1)
        //     }
        // } catch (e) {
        //     // ignore if endpoint not available
        // }
    })()
})
onUnmounted(() => {
    if (sidebar) {
        sidebar.setOpen(prevSidebarOpen.value)
    }
})

// Recalculate item unit prices when currency rate changes
watch(() => form.rate, (newRate) => {
    if (!Array.isArray(form.items)) return
    form.items.forEach((row) => {
        if (!row || !row.selected_item) return
        const baseUnit = Number(row.selected_item?.unitMeasure?.unit) || 1
        const selectedUnit = Number(row.selected_measure?.unit) || baseUnit
        const baseUnitPrice = Number(row.base_unit_price ?? row.selected_item?.unit_price ?? row.selected_item?.purchase_price ?? 0)
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
        row.expire_date = ''
        row.discount = ''
        row.free = ''
        row.tax = ''
        // do not add a new row on deselect
        return
    }

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
    row.on_hand = selectedItem.on_hand

    // Set the base unit price - this is the price per base unit
    row.base_unit_price = selectedItem.unit_price ?? selectedItem.purchase_price ?? 0

    // Set the initial unit_price based on the base unit measure
    const baseUnit = Number(selectedItem.unitMeasure?.unit) || 1
    row.unit_price = row.base_unit_price / baseUnit*form.rate;

    // Add a new empty row only when selecting into the last row
    if (index === form.items.length - 1) {
        addRow()
    }

    notifyIfDuplicate(index)
    // Duplicate check after selection
    // if (isDuplicateRow(index)) {
    //     notifyIfDuplicate(index)
    //     // Automatically clear the duplicate item
    //     resetRow(index)
    //     return
    // }
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
    const onHand = Number(item.on_hand) || 0
    const converted = (onHand * baseUnit) / selectedUnit
    const free = Number(item.free) || 0
    return converted + free;
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
const totalPurchasePrice = computed(() => form.items.reduce((acc, item) => acc + (toNum(item.unit_price, 0)), 0))
 
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
</script>

<template>
    <AppLayout :title="t('general.create', { name: t('purchase.purchase') })" :sidebar-collapsed="true">
         <form @submit.prevent="handleSubmit">
            <div class="mb-5 rounded-xl border border-violet-500 p-4 shadow-sm relative ">
            <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">{{ t('general.create', { name: t('purchase.purchase') }) }}</div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                <NextSelect
                    :options="ledgers.data"
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
                <NextDate v-model="form.date" :current-date="true" :error="form.errors?.date" :placeholder="t('general.enter', { text: t('general.date') })" :label="t('general.date')" />
                <div class="grid grid-cols-2 gap-2">
                    <NextSelect
                    :options="currencies.data"
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
                <NextInput placeholder="Rate" :error="form.errors?.rate" type="number" step="any" v-model="form.rate" :label="t('general.rate')"/>
                </div>

               <div class="grid grid-cols-1 gap-2">
                <NextSelect
                    :options="salePurchaseTypes"
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
                    :options="stores.data"
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
                    <thead class=" " :class="form.sale_purchase_type_id === 'cash' ? 'bg-card sticky top-0 z-[200]' : ''">
                        <tr class="rounded-xltext-muted-foreground font-semibold text-sm text-violet-500">
                            <th class="px-1 py-1 w-5 min-w-5">#</th>
                            <th class="px-1 py-1 w-40 min-w-64">{{ t('item.item') }}</th>
                            <th class="px-1 py-1 w-32">{{ t('general.batch') }}</th>
                            <th class="px-1 py-1 w-36">{{ t('general.expire_date') }}</th>
                            <th class="px-1 py-1 w-16">{{ t('general.qty') }}</th>
                            <th class="px-1 py-1 w-24">{{ t('general.on_hand') }}</th>
                            <th class="px-1 py-1 w-24">{{ t('general.unit') }}</th>
                            <th class="px-1 py-1 w-24">{{ t('general.price') }}</th>
                            <th class="px-1 py-1 w-24">{{ t('general.discount') }}</th>
                            <th class="px-1 py-1 w-16">{{ t('general.free') }}</th>
                            <th class="px-1 py-1 w-16">{{ t('general.tax') }}</th>
                            <th class="px-1 py-1 w-16">{{ t('general.total') }}</th>
                            <th class="px-1 py-1 w-10  ">
                                <Trash2 class="w-4 h-4 text-red-500 inline" />
                            </th>
                        </tr>
                    </thead>
                    <tbody class="p-2">
                        <tr v-for="(item, index) in form.items" :key="item.id" class="hover:bg-muted/40 transition-colors">
                            <td class="px-1 py-2 align-top w-5">{{ index + 1 }}</td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                                <NextSelect
                                    :options="items.data"
                                    v-model="item.selected_item"
                                    label-key="name"
                                    :placeholder="t('general.search_or_select')"
                                    id="item_id"
                                    :error="form.errors?.item_id"
                                    :show-arrow="false"
                                    :searchable="true"
                                    resource-type="items"
                                    :search-fields="['name', 'code', 'generic_name', 'packing', 'barcode','fast_search']"
                                    value-key="id"
                                    :reduce="item => item"
                                    @update:modelValue=" value => { handleItemChange(index, value); }"
                                />
                            </td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                                <NextInput
                                    v-model="item.batch"
                                    :disabled="!item.selected_item"
                                    :error="form.errors?.[`item_list.${index}.batch`]"
                                    @input="notifyIfDuplicate(index)"
                                />
                            </td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none relative relative wq': !isRowEnabled(index) }">
                                <NextDate v-model="item.expire_date"
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
                            <td class="text-center">
                                 <span :title="String(onhand(index))">{{ Number(onhand(index)).toFixed(1) }}</span>
                            </td>
                            <td :class="{ 'opacity-50 pointer-events-none select-none': !isRowEnabled(index) }">
                                <NextSelect
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

                                        // Calculate unit price for the selected measure
                                        // unit_price = base_unit_price / selected_unit
                                        form.items[index].unit_price = baseUnitPrice / selectedUnit*form.rate;

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
                                 {{ rowTotal(index) }} {{ item.selected_item?transactionSummary?.currencySymbol:'' }}
                            </td>
                            <td class="w-10 text-center">
                                <Trash2 class="w-4 h-4 cursor-pointer text-fuchsia-500 inline" @click="deleteRow(index)" />
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="sticky bottom-0 bg-card">
                        <tr class="bg-muted/40">
                            <!-- #: blank to align -->
                            <td></td>
                            <!-- Item total centered across item column -->
                            <td class="text-center">{{ totalRows }}</td>
                            <!-- Batch, Expiry blank -->
                            <td></td>
                            <td></td>
                            <!-- Qty total centered -->
                            <td class="text-center">{{ totalQuantity || 0 }}</td>
                            <!-- On hand blank -->
                            <td></td>
                            <!-- Unit blank -->
                            <td></td>
                            <!-- Value of goods (qty*price) total centered -->
                            <td class="text-center">{{ totalPurchasePrice || 0 }}</td>
                            <!-- Discount total centered -->
                            <td class="text-center">{{ totalItemDiscount || 0 }}</td>
                            <!-- Free total centered -->
                            <td class="text-center">{{ totalFree || 0 }}</td>
                            <!-- Tax total centered -->
                            <td class="text-center">{{ totalTax || 0 }}</td>
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

            <div class="mt-4 flex gap-2">
                <button type="submit" class="btn btn-primary px-4 py-2 rounded-md bg-primary text-white disabled:bg-gray-300" :disabled="disabled">{{ t('general.create') }}</button>
                <button type="button" class="btn btn-primary px-4 py-2 rounded-md bg-primary border text-white disabled:bg-gray-300" :disabled="disabled"
                 @click="() => handleSubmit(true)">
                 {{ t('general.create') }} & {{ t('general.new') }}
                 <Spinner v-show="submitting" />
                </button>
                <button type="button" class="btn px-4 py-2 rounded-md border" @click="() => $inertia.visit(route('purchases.index'))">{{ t('general.cancel') }}</button>
            </div>

         </form>

         <!-- Payment Dialog for Credit Transactions -->
         <PaymentDialog
             :open="showPaymentDialog"
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
