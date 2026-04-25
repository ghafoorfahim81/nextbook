<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Package2, FileText, User, Calendar, DollarSign, FileCheck, TrendingUp, ArrowLeft, Printer } from 'lucide-vue-next';

const { t } = useI18n();

const props = defineProps({
    sale: { type: Object, required: true },
});

const saleData = computed(() => props.sale?.data ?? props.sale ?? {});

const totalAmount = computed(() =>
    Number(saleData.value.items?.reduce((sum, item) => sum + Number(item.subtotal || 0), 0) || 0)
);

const totalDiscount = computed(() => {
    let discount = Number(saleData.value.items?.reduce((sum, item) => sum + Number(item.discount || 0), 0) || 0);
    if (saleData.value.discount && saleData.value.discount_type === 'percentage') {
        discount += Number(totalAmount.value * saleData.value.discount) / 100;
    } else if (saleData.value.discount) {
        discount += Number(saleData.value.discount);
    }
    return Number(discount);
});

const totalTax = computed(() =>
    Number(saleData.value.items?.reduce((sum, item) => sum + Number(item.tax || 0), 0) || 0)
);

const grandTotal = computed(() => Number(totalAmount.value - totalDiscount.value + totalTax.value));
const totalProfit = computed(() => Number((saleData.value.total_profit ?? saleData.value.items?.reduce((sum, item) => sum + Number(item.line_profit || 0), 0)) || 0));
const isProfitable = computed(() => totalProfit.value >= 0);

const formattedTotalAmount = computed(() => totalAmount.value.toFixed(2));
const formattedTotalDiscount = computed(() => totalDiscount.value.toFixed(2));
const formattedTotalTax = computed(() => totalTax.value.toFixed(2));
const formattedGrandTotal = computed(() => grandTotal.value.toFixed(2));
const formattedTotalProfit = computed(() => totalProfit.value.toFixed(2));
const formatLineValue = (value) => Number(value || 0).toFixed(2);

const statusBadgeClasses = computed(() => {
    switch (saleData.value.status) {
        case 'approved': return 'border-green-500/30 bg-green-500/10 text-green-700 dark:text-green-300';
        case 'rejected': return 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300';
        case 'pending': return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300';
        default: return 'border-border bg-muted text-foreground';
    }
});

const getStatusLabel = (status) => {
    switch (status) {
        case 'approved': return t('general.approve');
        case 'rejected': return t('general.reject');
        case 'pending': return 'Pending';
        default: return status;
    }
};

const currencySymbol = computed(() => saleData.value.transaction?.currency?.symbol || '');
</script>

<template>
    <AppLayout :title="`${t('sale.sale')} #${saleData.number}`">
        <div class="space-y-6">
            <!-- Page header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Button variant="outline" size="sm" @click="router.visit(route('sales.index'))">
                        <ArrowLeft class="h-4 w-4 ltr:mr-1 rtl:ml-1" />
                        {{ t('general.back') }}
                    </Button>
                    <div class="flex items-center gap-2">
                        <Package2 class="h-6 w-6 text-violet-500" />
                        <h1 class="text-xl font-semibold text-foreground">
                            {{ t('sale.sale') }} #{{ saleData.number }}
                        </h1>
                    </div>
                    <Badge :class="statusBadgeClasses">
                        {{ getStatusLabel(saleData.status) }}
                    </Badge>
                </div>
                <Button variant="outline" size="sm" @click="() => window.open(route('sales.print', saleData.id), '_blank')">
                    <Printer class="h-4 w-4 ltr:mr-1 rtl:ml-1" />
                    {{ t('general.print') }}
                </Button>
            </div>

            <!-- Info card -->
            <div class="rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <FileText class="h-5 w-5 text-violet-500" />
                    <h3 class="text-base font-semibold text-foreground">{{ t('general.info') }}</h3>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <Calendar class="h-3 w-3" />{{ t('general.date') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ saleData.date }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <User class="h-3 w-3" />{{ t('ledger.customer.customer') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ saleData.customer_name || '-' }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <FileCheck class="h-3 w-3" />{{ t('general.type') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ saleData.type || '-' }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <DollarSign class="h-3 w-3" />{{ t('general.amount') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ currencySymbol }} {{ formattedGrandTotal }}</div>
                    </div>
                    <!-- Profit highlight -->
                    <div class="space-y-1.5 rounded-lg px-3 py-2"
                        :class="isProfitable ? 'bg-green-500/10 border border-green-500/30' : 'bg-red-500/10 border border-red-500/30'">
                        <div class="flex items-center gap-2 text-xs"
                            :class="isProfitable ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                            <TrendingUp class="h-3 w-3" />{{ t('general.total_profit') }}
                        </div>
                        <div class="text-sm font-bold"
                            :class="isProfitable ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                            {{ currencySymbol }} {{ formattedTotalProfit }}
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <User class="h-3 w-3" />{{ t('general.created_by') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ saleData.created_by?.name || '-' }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <User class="h-3 w-3" />{{ t('general.updated_by') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ saleData.updated_by?.name || '-' }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <Calendar class="h-3 w-3" />{{ t('general.updated_at') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ saleData.updated_at || '-' }}</div>
                    </div>
                    <div v-if="saleData.raw_type === 'credit'" class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <DollarSign class="h-3 w-3" />{{ t('general.paid_total') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">
                            {{ currencySymbol }} {{ (grandTotal - saleData.receivable_amount).toFixed(2) }}
                        </div>
                    </div>
                    <div v-if="saleData.raw_type === 'credit'" class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <DollarSign class="h-3 w-3" />{{ t('general.receivable_amount') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">
                            {{ currencySymbol }} {{ saleData.receivable_amount }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items table -->
            <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="border-b border-border bg-muted/30 px-4 py-3 flex items-center gap-2">
                    <Package2 class="h-5 w-5 text-violet-500" />
                    <h3 class="text-base font-semibold text-foreground">{{ t('item.item') }}</h3>
                </div>

                <!-- Mobile cards -->
                <div class="space-y-3 p-4 md:hidden">
                    <div v-for="(item, index) in saleData.items" :key="item.id"
                        class="rounded-xl border border-border bg-background/70 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">#{{ index + 1 }}</div>
                                <div class="truncate text-sm font-semibold text-foreground">{{ item.item_name }}</div>
                                <div class="text-xs text-muted-foreground">{{ item.item_code || '-' }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs text-muted-foreground">{{ t('general.total') }}</div>
                                <div class="text-sm font-semibold text-violet-600 dark:text-violet-400">
                                    {{ currencySymbol }} {{ formatLineValue(item.subtotal) }}
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div><div class="text-xs text-muted-foreground">{{ t('general.batch') }}</div><div class="font-medium text-foreground">{{ item.batch || '-' }}</div></div>
                            <div><div class="text-xs text-muted-foreground">{{ t('general.expire_date') }}</div><div class="font-medium text-foreground">{{ item.expire_date || '-' }}</div></div>
                            <div><div class="text-xs text-muted-foreground">{{ t('general.qty') }}</div><div class="font-medium text-foreground">{{ item.quantity }}</div></div>
                            <div><div class="text-xs text-muted-foreground">{{ t('general.unit') }}</div><div class="font-medium text-foreground">{{ item.unit_measure_name || '-' }}</div></div>
                            <div><div class="text-xs text-muted-foreground">{{ t('general.price') }}</div><div class="font-medium text-foreground">{{ formatLineValue(item.unit_price) }}</div></div>
                            <div><div class="text-xs text-muted-foreground">{{ t('general.discount') }}</div><div class="font-medium text-foreground">{{ formatLineValue(item.discount) }}</div></div>
                            <div><div class="text-xs text-muted-foreground">{{ t('general.free') }}</div><div class="font-medium text-foreground">{{ formatLineValue(item.free) }}</div></div>
                            <div><div class="text-xs text-muted-foreground">{{ t('general.tax') }}</div><div class="font-medium text-foreground">{{ formatLineValue(item.tax) }}</div></div>
                            <div>
                                <div class="text-xs text-muted-foreground">{{ t('general.line_profit') }}</div>
                                <div class="font-medium" :class="Number(item.line_profit || 0) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                    {{ formatLineValue(item.line_profit) }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Mobile totals -->
                    <div class="rounded-xl border border-border bg-muted/20 p-4">
                        <div class="space-y-2 text-sm">
                            <div class="flex items-center justify-between gap-3"><span class="text-muted-foreground">{{ t('general.qty') }}</span><span class="font-semibold text-foreground">{{ saleData.items?.reduce((s, i) => s + parseFloat(i.quantity || 0), 0) }}</span></div>
                            <div class="flex items-center justify-between gap-3"><span class="text-muted-foreground">{{ t('general.amount') }}</span><span class="font-semibold text-foreground">{{ formattedTotalAmount }}</span></div>
                            <div class="flex items-center justify-between gap-3"><span class="text-muted-foreground">{{ t('general.discount') }}</span><span class="font-semibold text-foreground">{{ formattedTotalDiscount }}</span></div>
                            <div class="flex items-center justify-between gap-3"><span class="text-muted-foreground">{{ t('general.tax') }}</span><span class="font-semibold text-foreground">{{ formattedTotalTax }}</span></div>
                            <div class="flex items-center justify-between gap-3 border-t border-border pt-2">
                                <span class="text-muted-foreground">{{ t('general.total') }}</span>
                                <span class="text-base font-bold text-violet-600 dark:text-violet-400">{{ currencySymbol }} {{ formattedGrandTotal }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3 border-t border-border pt-2">
                                <span class="flex items-center gap-1 text-muted-foreground"><TrendingUp class="h-3 w-3" />{{ t('general.total_profit') }}</span>
                                <span class="text-base font-bold" :class="isProfitable ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                    {{ currencySymbol }} {{ formattedTotalProfit }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Desktop table -->
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-sm">
                        <thead class="border-b border-border bg-muted/40">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">#</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('item.item') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('general.batch') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('general.expire_date') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('general.qty') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('general.unit') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('general.price') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('general.discount') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('general.free') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('general.tax') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('general.total') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('general.line_profit') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="(item, index) in saleData.items" :key="item.id"
                                class="bg-background/40 transition-colors hover:bg-muted/40">
                                <td class="px-3 py-3 text-foreground">{{ index + 1 }}</td>
                                <td class="px-3 py-3 text-foreground">
                                    <div class="font-medium">{{ item.item_name }}</div>
                                    <div class="text-xs text-muted-foreground">{{ item.item_code }}</div>
                                </td>
                                <td class="px-3 py-3 text-foreground">{{ item.batch || '-' }}</td>
                                <td class="px-3 py-3 text-foreground">{{ item.expire_date || '-' }}</td>
                                <td class="px-3 py-3 text-right text-foreground">{{ item.quantity }}</td>
                                <td class="px-3 py-3 text-foreground">{{ item.unit_measure_name }}</td>
                                <td class="px-3 py-3 text-right text-foreground">{{ item.unit_price }}</td>
                                <td class="px-3 py-3 text-right text-foreground">{{ item.discount || 0 }}</td>
                                <td class="px-3 py-3 text-right text-foreground">{{ item.free || 0 }}</td>
                                <td class="px-3 py-3 text-right text-foreground">{{ item.tax || 0 }}</td>
                                <td class="px-3 py-3 text-right font-semibold text-foreground">{{ item.subtotal ? Number(item.subtotal).toFixed(2) : '0.00' }}</td>
                                <td class="px-3 py-3 text-right font-semibold"
                                    :class="Number(item.line_profit || 0) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                    {{ Number(item.line_profit || 0).toFixed(2) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t border-border bg-muted/30">
                            <tr>
                                <td colspan="4" class="px-3 py-4 text-right text-sm font-semibold text-foreground">{{ t('general.total') }}:</td>
                                <td class="px-3 py-4 text-right text-sm font-semibold text-foreground">{{ saleData.items?.reduce((s, i) => s + parseFloat(i.quantity || 0), 0) }}</td>
                                <td class="px-3 py-4"></td>
                                <td class="px-3 py-4 text-right text-sm font-semibold text-foreground">{{ formattedTotalAmount }}</td>
                                <td class="px-3 py-4 text-right text-sm font-semibold text-foreground">{{ formattedTotalDiscount }}</td>
                                <td class="px-3 py-4 text-right text-sm font-semibold text-foreground">{{ saleData.items?.reduce((s, i) => s + parseFloat(i.free || 0), 0) }}</td>
                                <td class="px-3 py-4 text-right text-sm font-semibold text-foreground">{{ formattedTotalTax }}</td>
                                <td class="px-3 py-4 text-right text-lg font-bold text-violet-600 dark:text-violet-400">{{ currencySymbol }} {{ formattedGrandTotal }}</td>
                                <td class="px-3 py-4 text-right text-lg font-bold"
                                    :class="isProfitable ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                    {{ currencySymbol }} {{ formattedTotalProfit }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
td, th { white-space: nowrap; }
</style>
