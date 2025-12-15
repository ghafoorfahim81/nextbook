<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    customer: { type: Object, required: true },
    sales: { type: Object, required: false },
    receipts: { type: Object, required: false },
});

const { t } = useI18n();

const customerData = computed(() => props.customer?.data ?? props.customer ?? {});
const statement = computed(() => customerData.value.statement ?? {});
const openings = computed(() => customerData.value.openings ?? []);

const salesRows = computed(() => props.sales?.data ?? props.sales ?? []);
const receiptRows = computed(() => props.receipts?.data ?? props.receipts ?? []);

const activeMainTab = ref('general');
const activeTxnTab = ref('sales');

const formatAmount = (value) => {
    if (value === null || value === undefined) return '-';
    return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>

<template>
    <AppLayout :title="`${t('ledger.customer.customer')} - ${customerData.name || ''}`">
        <div class="space-y-4">
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
                <!-- Header cards -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <!-- Left: avatar + summary -->
                    <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border p-4 flex flex-col items-center gap-4">
                        <div class="w-20 h-20 rounded-full bg-gradient-to-tr from-blue-500 to-indigo-500 flex items-center justify-center text-white text-2xl font-bold">
                            {{ (customerData.name || '').charAt(0).toUpperCase() }}
                        </div>
                        <div class="text-center">
                            <div class="text-lg font-semibold text-primary">
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
                        <div class="w-full grid grid-cols-3 gap-2 mt-4">
                            <div class="border rounded-lg px-3 py-2 text-center">
                                <div class="text-xs text-gray-500">{{ t('general.credit') }}</div>
                                <div class="text-sm font-semibold text-emerald-600">
                                    {{ formatAmount(statement.total_credit) }}
                                </div>
                            </div>
                            <div class="border rounded-lg px-3 py-2 text-center">
                                <div class="text-xs text-gray-500">{{ t('general.debit') }}</div>
                                <div class="text-sm font-semibold text-blue-600">
                                    {{ formatAmount(statement.total_debit) }}
                                </div>
                            </div>
                            <div class="border rounded-lg px-3 py-2 text-center">
                                <div class="text-xs text-gray-500">{{ t('general.balance') }}</div>
                                <div class="text-sm font-semibold" :class="statement.balance_nature === 'cr' ? 'text-emerald-600' : 'text-blue-600'">
                                    {{ formatAmount(statement.balance) }}{{ statement.balance_nature }}
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
                                <div class="text-xs text-gray-500">{{ t('general.branch') }}</div>
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
                                {{ t('sale.sales') }}
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
                        </div>
                    </div>

                    <div v-if="activeTxnTab === 'sales'">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-gray-500">
                                    <th class="py-2 pr-4">#</th>
                                    <th class="py-2 pr-4">{{ t('general.type') }}</th>
                                    <th class="py-2 pr-4">{{ t('general.date') }}</th>
                                    <th class="py-2 pr-4">{{ t('general.amount') }}</th>
                                    <th class="py-2 pr-4">{{ t('admin.currency.currency') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!salesRows.length">
                                    <td colspan="5" class="py-4 text-center text-gray-400">
                                        {{ t('general.no_data') }}
                                    </td>
                                </tr>
                                <tr
                                    v-for="(row, index) in salesRows"
                                    :key="row.id"
                                    class="border-b last:border-b-0"
                                >
                                    <td class="py-2 pr-4">{{ row.reference_id || row.id || index + 1 }}</td>
                                    <td class="py-2 pr-4 capitalize">{{ row.type }}</td>
                                    <td class="py-2 pr-4">{{ row.date }}</td>
                                    <td class="py-2 pr-4">{{ formatAmount(row.amount) }}</td>
                                    <td class="py-2 pr-4">
                                        {{ row.currency?.code || row.currency?.name || '' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-else>
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-gray-500">
                                    <th class="py-2 pr-4">#</th>
                                    <th class="py-2 pr-4">{{ t('general.type') }}</th>
                                    <th class="py-2 pr-4">{{ t('general.date') }}</th>
                                    <th class="py-2 pr-4">{{ t('general.amount') }}</th>
                                    <th class="py-2 pr-4">{{ t('admin.currency.currency') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!receiptRows.length">
                                    <td colspan="5" class="py-4 text-center text-gray-400">
                                        {{ t('general.no_data') }}
                                    </td>
                                </tr>
                                <tr
                                    v-for="(row, index) in receiptRows"
                                    :key="row.id"
                                    class="border-b last:border-b-0"
                                >
                                    <td class="py-2 pr-4">{{ row.reference_id || row.id || index + 1 }}</td>
                                    <td class="py-2 pr-4 capitalize">{{ row.type }}</td>
                                    <td class="py-2 pr-4">{{ row.date }}</td>
                                    <td class="py-2 pr-4">{{ formatAmount(row.amount) }}</td>
                                    <td class="py-2 pr-4">
                                        {{ row.currency?.code || row.currency?.name || '' }}
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
                        <tr class="border-b text-left text-gray-500">
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
                                {{ t('general.no_data') }}
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
    </AppLayout>
</template>

