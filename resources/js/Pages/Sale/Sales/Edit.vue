<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { ref, watch, onMounted } from 'vue';
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
import { useToast } from '@/Components/ui/toast/use-toast'
import NextDate from '@/Components/next/NextDatePicker.vue'
import { Trash2 } from 'lucide-vue-next';
import { Spinner } from "@/components/ui/spinner";
import { Button } from '@/Components/ui/button';

const { t } = useI18n();
const { toast } = useToast()

const props = defineProps({
    ledgers: {type: Object, required: true},
    salePurchaseTypes: {type: Object, required: true},
    currencies: {type: Object, required: true},
    stores: {type: Object, required: true},
    unitMeasures: {type: Object, required: true},
    accounts: {type: Object, required: true},               
    sale: {type: Object, required: true},
})

// Form setup for editing sales
const form = useForm({
    number: props.sale.number,
    customer_id: props.sale.customer_id,
    date: props.sale.date,
    currency_id: props.sale.currency_id,
    rate: props.sale.rate,
    sale_purchase_type_id: props.sale.sale_purchase_type_id,
    selected_currency: props.sale.currency,
    selected_ledger: props.sale.customer,
    selected_sale_purchase_type: props.sale.type,
    discount: props.sale.discount,
    transaction_total: props.sale.transaction_total,
    discount_type: props.sale.discount_type,
    description: props.sale.description,
    payment:{
        method: '',
        amount: '',
        account_id: '',
        note: '',
    },
    status: props.sale.status,
    store_id: props.sale.store_id,
    selected_store: props.sale.store,
    item_list: props.sale.item_list || [],
    items: props.sale.items ? props.sale.items.map(item => ({
        item_id: item.item_id,
        selected_item: item.item,
        quantity: item.quantity,
        unit_measure_id: item.unit_measure_id,
        batch: item.batch,
        expire_date: item.expire_date,
        unit_price: item.unit_price,
        selected_measure: item.unit_measure_name,
        item_discount: item.discount,
        free: item.free,
        tax: item.tax,
        on_hand: 0,
        batches: [],
    })) : [],
})

// Payment dialog visibility
const showPaymentDialog = ref(false)

const handleSelectChange = (field, value) => {
    form[field] = value
}

// Open payment dialog when type is credit
watch(() => form.sale_purchase_type_id, (val) => {
    if (val === 'credit') {
        showPaymentDialog.value = true
    }
})

const handlePaymentDialogConfirm = () => {
    showPaymentDialog.value = false
}

const handlePaymentDialogCancel = () => {
    const types = $page.props.salePurchaseTypes || []
    const cash = types.find(t => t.id === 'cash')
    if (cash) {
        form.selected_sale_purchase_type = cash
        form.sale_purchase_type_id = cash.id
    }
    showPaymentDialog.value = false
}

// Store filtering for items
const selectedStore = ref(props.sale.store_id);
const availableItems = ref([]);

// Load items for selected store on mount
onMounted(async () => {
    if (props.sale.store_id) {
        await loadItemsForStore(props.sale.store_id);
        // Populate existing items with batch information
        await populateExistingItems();
    }
});

// Watch for store changes
watch(() => form.store_id, async (newStoreId) => {
    if (newStoreId && newStoreId !== selectedStore.value) {
        selectedStore.value = newStoreId;
        await loadItemsForStore(newStoreId);
    }
});

// Load items available in selected store
const loadItemsForStore = async (storeId) => {
    try {
        const response = await axios.post('/api/search/items-for-sale', {
            store_id: storeId
        });
        availableItems.value = response.data.data;
    } catch (error) {
        console.error('Error loading items for store:', error);
        availableItems.value = [];
    }
};

// Populate existing items with batch information
const populateExistingItems = async () => {
    for (let i = 0; i < form.items.length; i++) {
        const item = form.items[i];
        const itemData = availableItems.value.find(ai => ai.id === item.item_id);
        if (itemData) {
            item.on_hand = itemData.on_hand;
            item.batches = itemData.batches || [];
        }
    }
};

// Handle item selection
const handleItemSelect = (index, item) => {
    form.items[index].item_id = item.id;
    form.items[index].selected_item = item;
    form.items[index].unit_measure_id = item.unit_measure_id;
    form.items[index].selected_measure = item.unit_measure_name;
    form.items[index].on_hand = item.on_hand;
    form.items[index].batches = item.batches || [];

    // Auto-select first batch if available
    if (item.batches && item.batches.length > 0) {
        const firstBatch = item.batches[0];
        form.items[index].batch = firstBatch.batch;
        form.items[index].expire_date = firstBatch.expire_date;
        form.items[index].unit_price = firstBatch.unit_price || item.rate_c || item.rate_b || item.rate_a || item.sale_price;
    } else {
        form.items[index].unit_price = item.rate_c || item.rate_b || item.rate_a || item.sale_price;
    }
};

// Handle batch selection
const handleBatchSelect = (index, batchInfo) => {
    form.items[index].batch = batchInfo.batch;
    form.items[index].expire_date = batchInfo.expire_date;
    form.items[index].unit_price = batchInfo.unit_price || form.items[index].selected_item.rate_c;
};

// Add new item row
const addItem = () => {
    form.items.push({
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
        on_hand: 0,
        batches: [],
    });
};

// Remove item row
const removeItem = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
        calculateTotals();
    }
};

// Calculate totals
const calculateTotals = () => {
    let total = 0;
    form.items.forEach(item => {
        if (item.quantity && item.unit_price) {
            const itemTotal = (parseFloat(item.quantity) * parseFloat(item.unit_price));
            const discount = item.item_discount ? parseFloat(item.item_discount) : 0;
            const tax = item.tax ? parseFloat(item.tax) : 0;
            total += itemTotal - discount + tax;
        }
    });
    form.transaction_total = total;
};

// Watch for changes in items to recalculate totals
watch(() => form.items, () => {
    calculateTotals();
}, { deep: true });

// Submit form
const submit = () => {
    form.item_list = form.items.filter(item => item.item_id && item.quantity);

    form.put(`/sales/${props.sale.id}`, {
        onSuccess: () => {
            toast({
                title: t('general.success'),
                description: t('sale.sale_updated_successfully'),
            });
        },
        onError: (errors) => {
            toast({
                title: t('general.error'),
                description: Object.values(errors).flat().join(', '),
                variant: 'destructive',
            });
        }
    });
};

// ----- Totals and Summary (parity with Purchase) -----
const toNum = (v, d = 0) => Number(v ?? d)
const goodsTotal = computed(() => form.items.reduce((acc, r) => acc + (toNum(r.unit_price, 0) * toNum(r.quantity, 0)), 0))
const totalItemDiscount = computed(() => form.items.reduce((acc, r) => acc + toNum(r.discount ?? r.item_discount, 0), 0))
const totalTax = computed(() => form.items.reduce((acc, r) => acc + toNum(r.tax, 0), 0))
const billDiscountCurrency = computed(() => {
    const billDisc = toNum(form.discount, 0)
    if (form.discount_type === 'percentage') return goodsTotal.value * (billDisc / 100)
    return billDisc
})
const billDiscountPercent = computed(() => {
    const billDisc = toNum(form.discount, 0)
    if (form.discount_type === 'percentage') return billDisc
    const gt = goodsTotal.value
    return gt > 0 ? (billDisc / gt) * 100 : 0
})
const totalDiscount = computed(() => billDiscountCurrency.value + totalItemDiscount.value)
const transactionSummary = computed(() => {
    const paid = toNum(form.payment.amount, 0)
    const oldBalance = toNum(form?.selected_ledger?.statement?.balance, 0)
    const nature = form?.selected_ledger?.statement?.balance_nature
    const hasSelectedItem = Array.isArray(form.items) && form.items.some(r => !!r.selected_item)
    const netAmount = goodsTotal.value - totalDiscount.value + totalTax.value
    const grandTotal = netAmount - paid
    const balance = hasSelectedItem
        ? (nature === 'dr' ? (grandTotal + oldBalance) : (grandTotal - oldBalance))
        : 0
    return {
        valueOfGoods: goodsTotal.value,
        billDiscountPercent: billDiscountPercent.value,
        billDiscount: billDiscountCurrency.value,
        itemDiscount: totalItemDiscount.value,
        cashReceived: paid,
        balance,
        grandTotal,
        oldBalance,
        balanceNature: nature,
        currencySymbol: form.selected_currency?.symbol,
    }
})
</script>

<template>
    <AppLayout :title="t('sale.edit_sale')">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold">{{ t('sale.edit_sale') }}</h1>
                <div class="flex gap-2">
                    <Button variant="outline" @click="$inertia.visit('/sales')">
                        {{ t('general.cancel') }}
                    </Button>
                    <Button @click="submit" :disabled="form.processing">
                        <Spinner v-if="form.processing" class="mr-2 h-4 w-4" />
                        {{ t('general.update') }}
                    </Button>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Basic Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <NextInput
                        v-model="form.number"
                        :label="t('general.number')"
                        :error="form.errors.number"
                        readonly
                    />

                    <NextSelect
                        :options="$page.props.ledgers"
                        v-model="form.selected_ledger"
                        @update:modelValue="(value) => handleSelectChange('customer_id', value.id)"
                        label-key="name"
                        value-key="id"
                        :reduce="ledger => ledger"
                        :label="t('ledger.customer.customer')"
                        :error="form.errors.customer_id"
                        :searchable="true"
                        resource-type="ledgers"
                        :search-fields="['name', 'email', 'phone_no']"
                        :search-options="{ type: 'customer' }"
                    />

                    <NextDate
                        v-model="form.date"
                        :label="t('general.date')"
                        :error="form.errors.date"
                    />

                    <NextSelect
                        v-model="form.store_id"
                        :label="t('administration.store.store')"
                        :options="$page.props.stores"
                        option-value="id"
                        option-label="name"
                        :error="form.errors.store_id"
                        searchable
                    />

                    <NextSelect
                        :options="$page.props.currencies"
                        v-model="form.selected_currency"
                        label-key="code"
                        value-key="id"
                        @update:modelValue="(value) => handleSelectChange('currency_id', value.id)"
                        :reduce="currency => currency"
                        :label="t('admin.currency.currency')"
                        :error="form.errors.currency_id"
                        :searchable="true"
                        resource-type="currencies"
                        :search-fields="['name', 'code', 'symbol']"
                    />
                    <NextInput
                        v-model="form.rate"
                        :label="t('general.rate')"
                        type="number"
                        step="any"
                        :error="form.errors.rate"
                    />

                    <NextSelect
                        :options="$page.props.salePurchaseTypes"
                        v-model="form.selected_sale_purchase_type"
                        @update:modelValue="(value) => handleSelectChange('sale_purchase_type_id', value.id || value)"
                        label-key="name"
                        value-key="id"
                        :reduce="type => type"
                        :label="t('general.type')"
                        :error="form.errors.sale_purchase_type_id"
                        :searchable="false"
                    />
                </div>

                <!-- Items Section -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold">{{ t('inventory.items') }}</h3>

                    <div v-for="(item, index) in form.items" :key="index" class="border rounded-lg p-4 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <!-- Item Selection -->
                            <NextSelect
                                v-model="item.selected_item"
                                :label="t('inventory.item.item')"
                                :options="availableItems"
                                option-value="id"
                                option-label="name"
                                :error="form.errors[`item_list.${index}.item_id`]"
                                searchable
                                @update:model-value="handleItemSelect(index, $event)"
                            />

                            <!-- Batch Selection -->
                            <NextSelect
                                v-if="item.batches && item.batches.length > 0"
                                v-model="item.batch"
                                :label="t('general.batch')"
                                :options="item.batches.map(b => ({ value: b.batch, label: `${b.batch} (${b.available_quantity} available)` }))"
                                option-value="value"
                                option-label="label"
                                :error="form.errors[`item_list.${index}.batch`]"
                                @update:model-value="batch => {
                                    const batchInfo = item.batches.find(b => b.batch === batch);
                                    if (batchInfo) handleBatchSelect(index, batchInfo);
                                }"
                            />

                            <!-- Quantity -->
                            <NextInput
                                v-model="item.quantity"
                                :label="t('general.quantity')"
                                type="number"
                                :max="item.on_hand"
                                :error="form.errors[`item_list.${index}.quantity`]"
                            />

                            <!-- Unit Price -->
                            <NextInput
                                v-model="item.unit_price"
                                :label="t('general.unit_price')"
                                type="number"
                                :error="form.errors[`item_list.${index}.unit_price`]"
                            />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Expire Date (read-only) -->
                            <NextInput
                                v-model="item.expire_date"
                                :label="t('general.expire_date')"
                                readonly
                            />

                            <!-- On Hand (read-only) -->
                            <NextInput
                                :model-value="item.on_hand"
                                :label="t('general.on_hand')"
                                readonly
                            />

                            <!-- Unit Measure (read-only) -->
                            <NextInput
                                :model-value="item.selected_measure"
                                :label="t('administration.unit_measure.unit_measure')"
                                readonly
                            />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <NextInput
                                v-model="item.item_discount"
                                :label="t('general.discount')"
                                type="number"
                            />

                            <NextInput
                                v-model="item.free"
                                :label="t('general.free')"
                                type="number"
                            />

                            <NextInput
                                v-model="item.tax"
                                :label="t('general.tax')"
                                type="number"
                            />
                        </div>

                        <!-- Remove Item Button -->
                        <div class="flex justify-end">
                            <Button
                                type="button"
                                variant="destructive"
                                size="sm"
                                @click="removeItem(index)"
                                :disabled="form.items.length === 1"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    <!-- Add Item Button -->
                    <Button type="button" variant="outline" @click="addItem">
                        {{ t('general.add_item') }}
                    </Button>
                </div>

                <!-- Summary Section -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <DiscountSummary :total-item-discount="totalItemDiscount" :bill-discount="billDiscountCurrency" :total-discount="totalDiscount" />
                    <TaxSummary :total-item-tax="totalTax" />
                    <TransactionSummary :summary="transactionSummary" />
                </div>

                <!-- Payment and Additional Fields -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <DiscountField
                        v-model:discount="form.discount"
                        v-model:discount-type="form.discount_type"
                        :error="form.errors.discount"
                    />

                    <NextTextarea
                        v-model="form.description"
                        :label="t('general.description')"
                        :error="form.errors.description"
                    />
                </div>

                <!-- Payment Dialog -->
                <PaymentDialog
                    :open="showPaymentDialog"
                    :payment="form.payment"
                    :errors="form.errors"
                    :accounts="$page.props.accounts || []"
                    @update:open="(value) => showPaymentDialog = value"
                    @confirm="handlePaymentDialogConfirm"
                    @cancel="handlePaymentDialogCancel"
                    @update:payment="(payment) => form.payment = payment"
                />
            </form>
        </div>
    </AppLayout>
</template>
