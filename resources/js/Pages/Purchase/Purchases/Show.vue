<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { router, useForm } from '@inertiajs/vue3';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Package2, FileText, User, Calendar, DollarSign, FileCheck, CheckCircle2, XCircle } from 'lucide-vue-next';
import { useToast } from '@/Components/ui/toast/use-toast';
import TransactionActionDialog from '@/Components/TransactionActionDialog.vue';
import ShowPageToolbar from '@/Components/ShowPageToolbar.vue';

const { t } = useI18n();
const { toast } = useToast();

const props = defineProps({
    purchase: { type: Object, required: true },
    reversal: { type: Object, default: null },
    originalDoc: { type: Object, default: null },
});

const purchaseData = computed(() => props.purchase?.data ?? props.purchase ?? {});

const totalAmount = computed(() =>
    Number(purchaseData.value.items?.reduce((sum, item) => sum + Number(item.subtotal || 0), 0) || 0)
);

const totalDiscount = computed(() => {
    let discount = Number(purchaseData.value.items?.reduce((sum, item) => sum + Number(item.discount || 0), 0) || 0);
    if (purchaseData.value.discount && purchaseData.value.discount_type === 'percentage') {
        discount += Number(totalAmount.value * purchaseData.value.discount) / 100;
    } else if (purchaseData.value.discount) {
        discount += Number(purchaseData.value.discount);
    }
    return Number(discount);
});

const totalTax = computed(() =>
    Number(purchaseData.value.items?.reduce((sum, item) => sum + Number(item.tax || 0), 0) || 0)
);

const grandTotal = computed(() => Number(totalAmount.value - totalDiscount.value + totalTax.value));
const hasDeferredBalance = computed(() => ['credit', 'on_loan'].includes(String(purchaseData.value.raw_type || '').toLowerCase()));
const remainingAmount = computed(() => Number(purchaseData.value.remaining_amount ?? purchaseData.value.payable_amount ?? 0));
const paidAmount = computed(() => Math.max(0, grandTotal.value - remainingAmount.value));
const documentVoucherNumber = computed(() => purchaseData.value.transaction?.voucher_number || `Purchase #${purchaseData.value.number}`);
const originalVoucherNumber = computed(() => props.originalDoc?.voucher_number || documentVoucherNumber.value);
const reversalVoucherNumber = computed(() => props.reversal?.voucher_number || documentVoucherNumber.value);

const formattedTotalAmount = computed(() => totalAmount.value.toFixed(2));
const formattedTotalDiscount = computed(() => totalDiscount.value.toFixed(2));
const formattedTotalTax = computed(() => totalTax.value.toFixed(2));
const formattedGrandTotal = computed(() => grandTotal.value.toFixed(2));
const formattedPaidAmount = computed(() => paidAmount.value.toFixed(2));
const formattedRemainingAmount = computed(() => remainingAmount.value.toFixed(2));
const formatLineValue = (value) => Number(value || 0).toFixed(2);

const statusBadgeClasses = computed(() => {
    switch (purchaseData.value.status) {
        case 'draft': return 'border-gray-500/30 bg-gray-500/10 text-gray-700 dark:text-gray-300';
        case 'posted': return 'border-green-500/30 bg-green-500/10 text-green-700 dark:text-green-300';
        case 'reversed': return 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300';
        case 'approved': return 'border-green-500/30 bg-green-500/10 text-green-700 dark:text-green-300';
        case 'rejected': return 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300';
        case 'pending': return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300';
        default: return 'border-border bg-muted text-foreground';
    }
});

const getStatusLabel = (status) => {
    switch (status) {
        case 'draft': return 'Draft';
        case 'posted': return 'Posted';
        case 'reversed': return 'Reversed';
        case 'approved': return t('general.approve');
        case 'rejected': return t('general.reject');
        case 'pending': return 'Pending';
        default: return status;
    }
};

const currencySymbol = computed(() => purchaseData.value.transaction?.currency?.symbol || '');
const postDialogOpen = ref(false);
const reverseDialogOpen = ref(false);

const form = useForm({ status: purchaseData.value.status || '' });

const updateStatus = (status) => {
    form.status = status;
    form.patch(route('purchases.update-purchase-status', purchaseData.value.id), {
        onSuccess: () => {
            toast({
                title: t('general.success'),
                description: `Purchase ${status} successfully`,
                variant: 'success',
                class: 'bg-green-600 text-white',
            });
        },
        onError: () => {
            toast({
                title: t('general.error'),
                description: `Failed to ${status} purchase`,
                variant: 'destructive',
                class: 'bg-red-600 text-white',
            });
        },
    });
};

const postPurchase = () => {
    router.post(route('purchases.post', purchaseData.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => { postDialogOpen.value = false },
    });
};

const reversePurchase = (reason) => {
    router.post(route('purchases.reverse', purchaseData.value.id), { reason }, {
        preserveScroll: true,
        onSuccess: () => { reverseDialogOpen.value = false },
    });
};
</script>

<template>
    <AppLayout :title="`${t('purchase.purchase')} #${purchaseData.number}`">
        <div class="space-y-6">
            <!-- Page header -->
            <ShowPageToolbar
                back-route="purchases.index"
                :status="purchaseData.status"
                :edit-route="purchaseData.id ? route('purchases.edit', purchaseData.id) : null"
                edit-permission="purchases.update"
                :export-url="route('purchases.export', purchaseData.id)"
                @post="postDialogOpen = true"
                @reverse="reverseDialogOpen = true"
            >
                <template v-if="purchaseData.status === 'pending'">
                    <Button
                        variant="destructive"
                        size="sm"
                        :disabled="form.processing"
                        @click="updateStatus('rejected')"
                    >
                        <XCircle class="h-4 w-4 ltr:mr-1 rtl:ml-1" />{{ t('general.reject') }}
                    </Button>
                    <Button
                        size="sm"
                        class="bg-green-600 text-white hover:bg-green-700"
                        :disabled="form.processing"
                        @click="updateStatus('approved')"
                    >
                        <CheckCircle2 class="h-4 w-4 ltr:mr-1 rtl:ml-1" />{{ t('general.approve') }}
                    </Button>
                </template>
            </ShowPageToolbar>

            <TransactionActionDialog
                v-model:open="postDialogOpen"
                type="post"
                :title="t('purchase.post_purchase')"
                :description="t('purchase.post_purchase_description')"
                @confirm="postPurchase"
            />
            <TransactionActionDialog
                v-model:open="reverseDialogOpen"
                type="reverse"
                :title="t('purchase.reverse_purchase')"
                :description="t('purchase.reverse_purchase_description')"
                @confirm="reversePurchase"
            />

            <div v-if="originalDoc" class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-700 dark:text-amber-300">
                {{ t('general.reversal_of_transaction', { number: originalVoucherNumber }) }}.
            </div>
            <div v-if="purchaseData.status === 'reversed' && reversal" class="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-300">
                {{ t('general.reversed_by') }} {{ reversalVoucherNumber }}.
            </div>

            <!-- Info card -->
            <fieldset class="rounded-xl border border-border bg-card px-5 pb-5 pt-3 text-card-foreground shadow-sm">
                <legend class="px-2 flex items-center gap-1.5">
                    <span class="text-sm font-semibold text-violet-500">{{ t('purchase.purchase') }} #{{ purchaseData.number }}</span>
                    <Badge :class="statusBadgeClasses">{{ getStatusLabel(purchaseData.status) }}</Badge>
                </legend>
                <div class="mb-4 flex items-center gap-2">
                    <FileText class="h-5 w-5 text-violet-500" />
                    <h3 class="text-base font-semibold text-foreground">{{ t('general.details') }}</h3>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground"><Calendar class="h-3 w-3" />{{ t('general.date') }}</div>
                        <div class="text-sm font-medium text-foreground">{{ purchaseData.date }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground"><User class="h-3 w-3" />{{ t('ledger.supplier.supplier') }}</div>
                        <div class="text-sm font-medium text-foreground">{{ purchaseData.supplier_name || '-' }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground"><FileCheck class="h-3 w-3" />{{ t('general.type') }}</div>
                        <div class="text-sm font-medium text-foreground">{{ purchaseData.type || '-' }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground"><DollarSign class="h-3 w-3" />{{ t('general.total') }}</div>
                        <div class="text-sm font-medium text-foreground">{{ currencySymbol }} {{ formattedGrandTotal }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground"><User class="h-3 w-3" />{{ t('general.created_by') }}</div>
                        <div class="text-sm font-medium text-foreground">{{ purchaseData.created_by?.name || '-' }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground"><User class="h-3 w-3" />{{ t('general.updated_by') }}</div>
                        <div class="text-sm font-medium text-foreground">{{ purchaseData.updated_by?.name || '-' }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground"><Calendar class="h-3 w-3" />{{ t('general.updated_at') }}</div>
                        <div class="text-sm font-medium text-foreground">{{ purchaseData.updated_at || '-' }}</div>
                    </div>
                    <div v-if="hasDeferredBalance" class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground"><DollarSign class="h-3 w-3" />{{ t('general.paid_amount') }}</div>
                        <div class="text-sm font-medium text-foreground">{{ currencySymbol }} {{ formattedPaidAmount }}</div>
                    </div>
                    <div v-if="hasDeferredBalance" class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground"><DollarSign class="h-3 w-3" />{{ t('general.remaining_amount') }}</div>
                        <div class="text-sm font-medium text-foreground">{{ currencySymbol }} {{ formattedRemainingAmount }}</div>
                    </div>
                </div>
            </fieldset>

            <!-- Items table -->
            <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="border-b border-border bg-muted/30 px-4 py-3 flex items-center gap-2">
                    <Package2 class="h-5 w-5 text-violet-500" />
                    <h3 class="text-base font-semibold text-foreground">{{ t('item.item') }}</h3>
                </div>

                <!-- Mobile cards -->
                <div class="space-y-3 p-4 md:hidden">
                    <div v-for="(item, index) in purchaseData.items" :key="item.id"
                        class="rounded-xl border border-border bg-background/70 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">#{{ index + 1 }}</div>
                                <div class="truncate text-sm font-semibold text-foreground">{{ item.item_name }}</div>
                                <div class="text-xs text-muted-foreground">{{ item.item_code || '-' }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs text-muted-foreground">{{ t('general.total') }}</div>
                                <div class="text-sm font-semibold text-violet-600 dark:text-violet-400">{{ currencySymbol }} {{ formatLineValue(item.subtotal) }}</div>
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
                        </div>
                    </div>
                    <!-- Mobile totals -->
                    <div class="rounded-xl border border-border bg-muted/20 p-4">
                        <div class="space-y-2 text-sm">
                            <div class="flex items-center justify-between gap-3"><span class="text-muted-foreground">{{ t('general.qty') }}</span><span class="font-semibold text-foreground">{{ purchaseData.items?.reduce((s, i) => s + parseFloat(i.quantity || 0), 0) }}</span></div>
                            <div class="flex items-center justify-between gap-3"><span class="text-muted-foreground">{{ t('general.amount') }}</span><span class="font-semibold text-foreground">{{ formattedTotalAmount }}</span></div>
                            <div class="flex items-center justify-between gap-3"><span class="text-muted-foreground">{{ t('general.discount') }}</span><span class="font-semibold text-foreground">{{ formattedTotalDiscount }}</span></div>
                            <div class="flex items-center justify-between gap-3"><span class="text-muted-foreground">{{ t('general.tax') }}</span><span class="font-semibold text-foreground">{{ formattedTotalTax }}</span></div>
                            <div class="flex items-center justify-between gap-3 border-t border-border pt-2">
                                <span class="text-muted-foreground">{{ t('general.total') }}</span>
                                <span class="text-base font-bold text-violet-600 dark:text-violet-400">{{ currencySymbol }} {{ formattedGrandTotal }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Desktop table -->
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-sm">
                        <thead class="border-b border-border bg-muted/40">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">#</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('item.item') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('general.batch') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('general.expire_date') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('general.qty') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('general.unit') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('general.price') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('general.discount') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('general.free') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('general.tax') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('general.total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="(item, index) in purchaseData.items" :key="item.id"
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
                            </tr>
                        </tbody>
                        <tfoot class="border-t border-border bg-muted/30">
                            <tr>
                                <td colspan="4" class="px-3 py-4 text-right text-sm font-semibold text-foreground">{{ t('general.total') }}:</td>
                                <td class="px-3 py-4 text-right text-sm font-semibold text-foreground">{{ purchaseData.items?.reduce((s, i) => s + parseFloat(i.quantity || 0), 0) }}</td>
                                <td class="px-3 py-4"></td>
                                <td class="px-3 py-4 text-right text-sm font-semibold text-foreground">{{ formattedTotalAmount }}</td>
                                <td class="px-3 py-4 text-right text-sm font-semibold text-foreground">{{ formattedTotalDiscount }}</td>
                                <td class="px-3 py-4 text-right text-sm font-semibold text-foreground">{{ purchaseData.items?.reduce((s, i) => s + parseFloat(i.free || 0), 0) }}</td>
                                <td class="px-3 py-4 text-right text-sm font-semibold text-foreground">{{ formattedTotalTax }}</td>
                                <td class="px-3 py-4 text-right text-lg font-bold text-violet-600 dark:text-violet-400">{{ currencySymbol }} {{ formattedGrandTotal }}</td>
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
