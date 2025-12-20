<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';

const { t } = useI18n();

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    customerId: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['update:open']);

const customer = ref(null);
const sales = ref([]);
const receipts = ref([]);
const payments = ref([]);
const loading = ref(false);

const activeMainTab = ref('general');
const activeTxnTab = ref('sales');

const customerData = computed(() => customer.value ?? {});
const statement = computed(() => customerData.value.statement ?? {});
const openings = computed(() => customerData.value.openings ?? []);

const formatAmount = (value) => {
    if (value === null || value === undefined) return '-';
    return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const loadCustomer = async (id) => {
    console.log('id', id);
    if (!id) return;
    loading.value = true;
    try {
        const response = await axios.get(`/customers/${id}`);
        const data = response.data;
        console.log('data', data);
        customer.value = data.customer?.data ?? data.customer ?? null;
        sales.value = data.sales?.data ?? data.sales ?? [];
        receipts.value = data.receipts?.data ?? data.receipts ?? [];
        payments.value = data.payments?.data ?? data.payments ?? [];
    } catch (error) {
        console.error('Error loading customer:', error);
    } finally {
        loading.value = false;
    }
};
 

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen && props.customerId) {
            await loadCustomer(props.customerId);
        } else if (!isOpen) {
            customer.value = null;
            sales.value = [];
            receipts.value = [];
            payments.value = [];
            activeMainTab.value = 'general';
            activeTxnTab.value = 'sales';
        }
    }
);

const closeDialog = () => {
    emit('update:open', false);
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-5xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>
                    {{ t('ledger.customer.customer') }}
                    <span v-if="customerData.name"> - {{ customerData.name }}</span>
                </DialogTitle>
            </DialogHeader>

            <div v-if="loading" class="flex justify-center items-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"></div>
            </div>

            <div v-else-if="customerData && customerData.id" class="space-y-4">
                <!-- Top tabs: General / Opening -->
                <div class="border-b flex gap-4">
                    <button
                        type="button"
                        class="px-4 py-2 -mb-px border-b-2"
                        :class="activeMainTab === 'general'
                            ? 'border-primary text-primary font-semibold'
                            : 'border-transparent text-gray-500 hover:text-gray-700'"
                        @click="activeMainTab = 'general'"
                    >
                        {{ t('general.general') }}
                    </button>
                    <button
                        type="button"
                        class="px-4 py-2 -mb-px border-b-2"
                        :class="activeMainTab === 'opening'
                            ? 'border-primary text-primary font-semibold'
                            : 'border-transparent text-gray-500 hover:text-gray-700'"
                        @click="activeMainTab = 'opening'"
                    >
                        {{ t('item.opening') }}
                    </button>
                </div>

                <!-- GENERAL TAB -->
                <div v-if="activeMainTab === 'general'" class="space-y-4">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                        <!-- Left: avatar + summary -->
                        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border p-4 flex flex-col items-center gap-4">
                            <div class="w-16 h-16 rounded-full bg-gradient-to-tr from-blue-500 to-indigo-500 flex items-center justify-center text-white text-xl font-bold">
                                {{ (customerData.name || '').charAt(0).toUpperCase() }}
                            </div>
                            <div class="text-center">
                                <div class="text-base font-semibold text-primary">
                                    {{ customerData.name }}
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    {{ customerData.code }}
                                </div>
                                <div class="mt-2 text-xs text-gray-400">
                                    {{ t('ledger.customer.customer') }}
                                </div>
                            </div>

                            <!-- Statement summary -->
                            <div class="w-full bg-white border rounded-xl overflow-hidden mt-4">
                                <div class="flex flex-col divide-y">
                                    <div class="flex items-center px-5 py-2">
                                        <div class="flex-1 text-base text-black dark:text-white">{{ t('general.credit') }}</div>
                                        <div class="text-base font-medium text-green-600">
                                            {{ formatAmount(statement.total_credit) }}
                                        </div>
                                    </div>
                                    <div class="flex items-center px-5 py-2 mt-1">
                                        <div class="flex-1 text-base text-black dark:text-white">{{ t('general.debit') }}</div>
                                        <div class="text-base font-medium text-green-600">
                                            {{ formatAmount(statement.total_debit) }}
                                        </div>
                                    </div>
                                    <div class="flex items-center px-5 py-2">
                                        <div class="flex-1 text-base text-black dark:text-white">{{ t('general.balance') }}</div>
                                        <div
                                            class="text-base font-medium"
                                            :class="statement.balance_nature === 'cr' ? 'text-green-600' : 'text-red-600'"
                                        >
                                            {{ formatAmount(statement.balance) }} {{statement.balance > 0 ? (statement.balance_nature === 'cr' ? t('general.owe_to') : t('general.owe_you')) : '' }}
                                        </div> 
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right: basic info -->
                        <div class="lg:col-span-2 bg-white dark:bg-gray-900 rounded-xl shadow-sm border p-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <div class="text-xs text-gray-500">{{ t('general.name') }}</div>
                                    <div class="font-medium">{{ customerData.name }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">{{ t('ledger.contact_person') }}</div>
                                    <div class="font-medium">{{ customerData.contact_person }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">{{ t('general.phone') }}</div>
                                    <div class="font-medium">{{ customerData.phone_no }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">{{ t('general.email') }}</div>
                                    <div class="font-medium">{{ customerData.email }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">{{ t('admin.currency.currency') }}</div>
                                    <div class="font-medium">
                                        {{ customerData.currency?.name || '' }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">{{ t('admin.branch.branch') }}</div>
                                    <div class="font-medium">
                                        {{ customerData.branch?.name || '' }}
                                    </div>
                                </div>
                                <div class="md:col-span-2">
                                    <div class="text-xs text-gray-500">{{ t('general.address') }}</div>
                                    <div class="font-medium">
                                        {{ customerData.address }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sales / Receipts tables -->
                    <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border p-4">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex gap-4">
                                <button
                                    type="button"
                                    class="px-3 py-1.5 text-sm rounded-full"
                                    :class="activeTxnTab === 'sales'
                                        ? 'bg-primary text-white'
                                        : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'"
                                    @click="activeTxnTab = 'sales'"
                                >
                                    {{ t('sales.sales') }}
                                </button>
                                <button
                                    type="button"
                                    class="px-3 py-1.5 text-sm rounded-full"
                                    :class="activeTxnTab === 'receipts'
                                        ? 'bg-primary text-white'
                                        : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'"
                                    @click="activeTxnTab = 'receipts'"
                                >
                                    {{ t('receipt.receipts') }}
                                </button>
                                <button
                                    type="button"
                                    class="px-3 py-1.5 text-sm rounded-full"
                                    :class="activeTxnTab === 'payments'
                                        ? 'bg-primary text-white'
                                        : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'"
                                    @click="activeTxnTab = 'payments'"
                                >
                                    {{ t('payment.payments') }}
                                </button>
                            </div>
                        </div>

                        <div v-if="activeTxnTab === 'sales'">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="border-b text-left rtl:text-right text-gray-500">
                                        <th class="py-2 pr-4">#</th>
                                        <th class="py-2 pr-4">{{ t('general.number') }}</th>
                                        <th class="py-2 pr-4">{{ t('general.type') }}</th>
                                        <th class="py-2 pr-4">{{ t('general.date') }}</th>
                                        <th class="py-2 pr-4">{{ t('general.amount') }}</th>
                                        <th class="py-2 pr-4">{{ t('admin.currency.currency') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="!sales.length">
                                        <td colspan="5" class="py-4 text-center text-gray-400">
                                            {{ t('general.no_data_found') }}
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="(row, index) in sales"
                                        :key="row.id"
                                        class="border-b last:border-b-0"
                                    >
                                        <td class="py-2 pr-4">{{  index + 1 }}</td>
                                        <td class="py-2 pr-4">{{ row.number }}</td>
                                        <td class="py-2 pr-4 capitalize">{{ row.type }}</td>
                                        <td class="py-2 pr-4">{{ row.date }}</td>
                                        <td class="py-2 pr-4">{{ formatAmount(row.amount) }}</td>
                                        <td class="py-2 pr-4">
                                            {{ row.transaction?.currency?.code || row.transaction?.currency?.name || '' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-else-if="activeTxnTab === 'receipts'">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="border-b text-left rtl:text-right text-gray-500">
                                        <th class="py-2 pr-4">#</th>
                                        <th class="py-2 pr-4">{{ t('general.number') }}</th>
                                        <th class="py-2 pr-4">{{ t('general.type') }}</th>
                                        <th class="py-2 pr-4">{{ t('general.date') }}</th>
                                        <th class="py-2 pr-4">{{ t('general.amount') }}</th>
                                        <th class="py-2 pr-4">{{ t('admin.currency.currency') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="!receipts.length">
                                        <td colspan="5" class="py-4 text-center text-gray-400">
                                            {{ t('general.no_data_found') }}
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="(row, index) in receipts"
                                        :key="row.id"
                                        class="border-b last:border-b-0"
                                    >
                                        <td class="py-2 pr-4">{{   index + 1 }}</td>
                                        <td class="py-2 pr-4">{{ row.number }}</td>
                                        <td class="py-2 pr-4 capitalize">{{ row.receive_transaction?.type }}</td>
                                        <td class="py-2 pr-4">{{ row.date }}</td>
                                        <td class="py-2 pr-4">{{ formatAmount(row.amount) }}</td>
                                        <td class="py-2 pr-4">
                                            {{ row.receive_transaction?.currency?.code || row.receive_transaction?.currency?.name || '' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else-if="activeTxnTab === 'payments'">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="border-b text-left rtl:text-right text-gray-500">
                                        <th class="py-2 pr-4">#</th>
                                        <th class="py-2 pr-4">{{ t('general.number') }}</th>
                                        <th class="py-2 pr-4">{{ t('general.type') }}</th>
                                        <th class="py-2 pr-4">{{ t('general.date') }}</th>
                                        <th class="py-2 pr-4">{{ t('general.amount') }}</th>
                                        <th class="py-2 pr-4">{{ t('admin.currency.currency') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="!payments.length">
                                        <td colspan="5" class="py-4 text-center text-gray-400">
                                            {{ t('general.no_data_found') }}
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="(row, index) in payments"
                                        :key="row.id"
                                        class="border-b last:border-b-0"
                                    >
                                        <td class="py-2 pr-4">{{   index + 1 }}</td>
                                        <td class="py-2 pr-4">{{ row.number }}</td>
                                        <td class="py-2 pr-4 capitalize">{{ row.payment_transaction?.type }}</td>
                                        <td class="py-2 pr-4">{{ row.date }}</td>
                                        <td class="py-2 pr-4">{{ formatAmount(row.amount) }}</td>
                                        <td class="py-2 pr-4">
                                            {{ row.payment_transaction?.currency?.code || row.payment_transaction?.currency?.name || '' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div> 
                    </div>
                </div>

                <!-- OPENING TAB -->
                <div v-else class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border p-4">
                    <div class="text-sm font-semibold mb-3">
                        {{ t('item.opening') }}
                    </div>
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b text-left rtl:text-right text-gray-500">
                                <th class="py-2 pr-4">{{ t('admin.currency.currency') }}</th>
                                <th class="py-2 pr-4">{{ t('general.amount') }}</th>
                                <th class="py-2 pr-4">{{ t('general.rate') }}</th>
                                <th class="py-2 pr-4">{{ t('general.type') }}</th>
                                <th class="py-2 pr-4">{{ t('general.date') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!openings.length">
                                <td colspan="5" class="py-4 text-center text-gray-400">
                                    {{ t('general.no_data_found') }}
                                </td>
                            </tr>
                            <tr
                                v-for="opening in openings"
                                :key="opening.id"
                                class="border-b last:border-b-0"
                            >
                                <td class="py-2 pr-4">
                                    {{ opening.currency?.name || '' }}
                                </td>
                                <td class="py-2 pr-4">
                                    {{ formatAmount(opening.amount) }}
                                </td>
                                <td class="py-2 pr-4">
                                    {{ opening.rate }}
                                </td>
                                <td class="py-2 pr-4 capitalize">
                                    {{ opening.type }}
                                </td>
                                <td class="py-2 pr-4">
                                    {{ opening.date }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex justify-end mt-4">
                <Button variant="outline" @click="closeDialog">
                    {{ t('general.close') }}
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>

