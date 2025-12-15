<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    supplier: { type: Object, required: true },
    purchases: { type: Object, required: false },
    payments: { type: Object, required: false },
});

const { t } = useI18n();

const supplierData = computed(() => props.supplier?.data ?? props.supplier ?? {});
const statement = computed(() => supplierData.value.statement ?? {});
const openings = computed(() => supplierData.value.openings ?? []);

const purchaseRows = computed(() => props.purchases?.data ?? props.purchases ?? []);
const paymentRows = computed(() => props.payments?.data ?? props.payments ?? []);

const activeMainTab = ref('general');
const activeTxnTab = ref('purchases');

const formatAmount = (value) => {
    if (value === null || value === undefined) return '-';
    return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>

<template>
    <AppLayout :title="`${t('ledger.supplier.supplier')} - ${supplierData.name || ''}`">
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
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <!-- Left: avatar + statement -->
                    <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border p-4 flex flex-col items-center gap-4">
                        <div class="w-20 h-20 rounded-full bg-gradient-to-tr from-emerald-500 to-teal-500 flex items-center justify-center text-white text-2xl font-bold">
                            {{ (supplierData.name || '').charAt(0).toUpperCase() }}
                        </div>
                        <div class="text-center">
                            <div class="text-lg font-semibold text-primary">
                                {{ supplierData.name }}
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                {{ supplierData.code }}
                            </div>
                            <div class="mt-2 text-xs text-gray-400">
                                {{ t('ledger.supplier.supplier') }}
                            </div>
                        </div>

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
                                <div class="font-medium">{{ supplierData.name }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500">{{ t('ledger.contact_person') }}</div>
                                <div class="font-medium">{{ supplierData.contact_person }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500">{{ t('general.phone') }}</div>
                                <div class="font-medium">{{ supplierData.phone_no }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500">{{ t('general.email') }}</div>
                                <div class="font-medium">{{ supplierData.email }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500">{{ t('admin.currency.currency') }}</div>
                                <div class="font-medium">
                                    {{ supplierData.currency?.name || '' }}
                                </div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500">{{ t('general.branch') }}</div>
                                <div class="font-medium">
                                    {{ supplierData.branch?.name || '' }}
                                </div>
                            </div>
                            <div class="md:col-span-2">
                                <div class="text-xs text-gray-500">{{ t('general.address') }}</div>
                                <div class="font-medium">
                                    {{ supplierData.address }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Purchases / Payments tables -->
                <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border p-4">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex gap-4">
                            <button
                                type="button"
                                class="px-3 py-1.5 text-sm rounded-full"
                                :class="activeTxnTab === 'purchases'
                                    ? 'bg-primary text-white'
                                    : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'"
                                @click="activeTxnTab = 'purchases'"
                            >
                                {{ t('purchase.purchases') }}
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

                    <div v-if="activeTxnTab === 'purchases'">
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
                                <tr v-if="!purchaseRows.length">
                                    <td colspan="5" class="py-4 text-center text-gray-400">
                                        {{ t('general.no_data') }}
                                    </td>
                                </tr>
                                <tr
                                    v-for="(row, index) in purchaseRows"
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
                                <tr v-if="!paymentRows.length">
                                    <td colspan="5" class="py-4 text-center text-gray-400">
                                        {{ t('general.no_data') }}
                                    </td>
                                </tr>
                                <tr
                                    v-for="(row, index) in paymentRows"
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

