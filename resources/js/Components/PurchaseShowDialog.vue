<script setup>
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useToast } from '@/Components/ui/toast/use-toast';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Package2, CheckCircle2, XCircle, FileText, User, Calendar, DollarSign, FileCheck } from 'lucide-vue-next';
import axios from 'axios';

const { t } = useI18n();
const { toast } = useToast();

const props = defineProps({
    open: Boolean,
    purchaseId: String,
});

const emit = defineEmits(['update:open']);

const purchase = ref(null);
const loading = ref(false);
const totalAmount = ref(0);
const totalDiscount = ref(0);
const totalTax = ref(0);
const grandTotal = ref(0);

const form = useForm({
    status: '',
});

const statusBadgeClasses = computed(() => {
    if (!purchase.value) return 'border-border bg-muted text-foreground';
    switch (purchase.value.status) {
        case 'approved':
            return 'border-green-500/30 bg-green-500/10 text-green-700 dark:text-green-300';
        case 'rejected':
            return 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300';
        case 'pending':
            return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300';
        default:
            return 'border-border bg-muted text-foreground';
    }
});

const getStatusLabel = (status) => {
    switch (status) {
        case 'approved':
            return t('general.approve');
        case 'rejected':
            return t('general.reject');
        case 'pending':
            return 'Pending';
        default:
            return status;
    }
};

// Computed properties for formatted numbers
const formattedTotalAmount = computed(() => Number(totalAmount.value || 0).toFixed(2));
const formattedTotalDiscount = computed(() => Number(totalDiscount.value || 0).toFixed(2));
const formattedTotalTax = computed(() => Number(totalTax.value || 0).toFixed(2));
const formattedGrandTotal = computed(() => Number(grandTotal.value || 0).toFixed(2));
const formatLineValue = (value) => Number(value || 0).toFixed(2);

// Fetch purchase data when dialog opens
watch(() => props.open, async (isOpen) => {
    if (isOpen && props.purchaseId) {
        loading.value = true;
        try {
            const response = await axios.get(`/purchases/${props.purchaseId}`);
            purchase.value = response.data.data;
            form.status = purchase.value.status;

            // Calculate totals
            totalAmount.value = 0;
            totalDiscount.value = 0;
            totalTax.value = 0;
            grandTotal.value = 0;

            if (purchase.value.items && purchase.value.items.length > 0) {
                totalAmount.value = Number(purchase.value.items.reduce((sum, item) => sum + Number(item.subtotal || 0), 0));

                let discount = 0;
                discount += Number(purchase.value.items.reduce((sum, item) => sum + Number(item.discount || 0), 0));
                if (purchase.value.discount && purchase.value.discount_type === 'percentage') {
                    discount += Number(totalAmount.value * purchase.value.discount) / 100;
                } else if (purchase.value.discount) {
                    discount += Number(purchase.value.discount);
                }
                totalDiscount.value = Number(discount);

                totalTax.value = Number(purchase.value.items.reduce((sum, item) => sum + Number(item.tax || 0), 0));
                grandTotal.value = Number(totalAmount.value - totalDiscount.value + totalTax.value);
            }
        } catch (error) {
            console.error('Error fetching purchase:', error);
            toast({
                title: t('general.error'),
                description: 'Failed to load purchase details',
                variant: 'destructive',
                class: 'bg-red-600 text-white',
            });
        } finally {
            loading.value = false;
        }
    }
});

const updatePurchaseStatus = (status) => {
    form.status = status;
    form.patch(`/update-purchase-status/${props.purchaseId}/status`, {
        onSuccess: () => {
            purchase.value.status = status;
            const actionText = status === 'approved' ? 'approved' : 'rejected';
            toast({
                title: t('general.success'),
                description: `Purchase ${actionText} successfully`,
                variant: 'success',
                class: 'bg-green-600 text-white',
            });
        },
        onError: () => {
            const actionText = status === 'approved' ? 'approve' : 'reject';
            toast({
                title: t('general.error'),
                description: `Failed to ${actionText} purchase`,
                variant: 'destructive',
                class: 'bg-red-600 text-white',
            });
        }
    });
};

const handleApprove = () => {
    updatePurchaseStatus('approved');
};

const handleReject = () => {
    updatePurchaseStatus('rejected');
};

const closeDialog = () => {
    emit('update:open', false);
    purchase.value = null;
    totalAmount.value = 0;
    totalDiscount.value = 0;
    totalTax.value = 0;
    grandTotal.value = 0;
};
</script>

<template>
    <Dialog :open="open" @update:open="closeDialog">
        <DialogContent class="max-w-6xl overflow-hidden p-0">
            <div class="w-full rounded-2xl bg-background text-foreground shadow-2xl">
                <DialogHeader class="border-b border-border bg-gradient-to-r from-violet-400/10 via-background to-background px-6 py-5 text-left dark:from-violet-900/30">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-3">
                            <Package2 class="h-6 w-6 text-violet-500" />
                            <DialogTitle class="text-xl font-semibold text-foreground">
                                {{ t('purchase.purchase') }} #{{ purchase?.number }}
                            </DialogTitle>
                        </div>
                        <Badge :class="statusBadgeClasses" class="w-fit">
                            {{ getStatusLabel(purchase?.status) }}
                        </Badge>
                    </div>
                    <DialogDescription v-if="purchase?.description" class="text-muted-foreground">
                        {{ purchase.description }}
                    </DialogDescription>
                </DialogHeader>

                <div class="max-h-[calc(90vh-8.5rem)] space-y-6 overflow-y-auto px-6 py-5">
                    <div v-if="loading" class="flex items-center justify-center py-8">
                        <div class="text-muted-foreground">{{ t('general.loading') }}...</div>
                    </div>

                    <div v-else-if="purchase" class="space-y-6">
                        <!-- General Information Card -->
                        <div class="rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm">
                            <div class="mb-4 flex items-center gap-2">
                                <FileText class="h-5 w-5 text-violet-500" />
                                <h3 class="text-base font-semibold text-foreground">{{ t('general.info') }}</h3>
                            </div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <Calendar class="h-3 w-3" />
                                        {{ t('general.date') }}
                                    </div>
                                    <div class="text-sm font-medium text-foreground">{{ purchase.date }}</div>
                                </div>
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <User class="h-3 w-3" />
                                        {{ t('ledger.supplier.supplier') }}
                                    </div>
                                    <div class="text-sm font-medium text-foreground">{{ purchase.supplier_name || '-' }}</div>
                                </div>
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <FileCheck class="h-3 w-3" />
                                        {{ t('general.type') }}
                                    </div>
                                    <div class="text-sm font-medium text-foreground">{{ purchase.type || '-' }}</div>
                                </div>
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <DollarSign class="h-3 w-3" />
                                        {{ t('general.total') }}
                                    </div>
                                    <div class="text-sm font-medium text-foreground">
                                        {{ purchase.transaction?.currency?.symbol || '' }} {{ formattedGrandTotal }}
                                    </div>
                                </div>
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <User class="h-3 w-3" />
                                        {{ t('general.created_by') }}
                                    </div>
                                    <div class="text-sm font-medium text-foreground">{{ purchase.created_by?.name || '-' }}</div>
                                </div>
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <User class="h-3 w-3" />
                                        {{ t('general.updated_by') }}
                                    </div>
                                    <div class="text-sm font-medium text-foreground">{{ purchase.updated_by?.name || '-' }}</div>
                                </div>
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <Calendar class="h-3 w-3" />
                                        {{ t('general.updated_at') }}
                                    </div>
                                    <div class="text-sm font-medium text-foreground">{{ purchase.updated_at || '-' }}</div>
                                </div>
                                <div class="space-y-1.5" v-if="purchase.raw_type === 'credit'">
                                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <DollarSign class="h-3 w-3" />
                                        {{ t('general.paid_total') }}
                                    </div>
                                    <div class="text-sm font-medium text-foreground">
                                        {{ purchase.transaction?.currency?.symbol || '' }} {{ formattedGrandTotal - purchase.payable_amount }}
                                    </div>

                                </div>
                                <div class="space-y-1.5" v-if="purchase.raw_type === 'credit'">
                                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <DollarSign class="h-3 w-3" />
                                        {{ t('general.payable_amount') }}
                                    </div>
                                    <div class="text-sm font-medium text-foreground">
                                        {{ purchase.transaction?.currency?.symbol || '' }} {{ purchase.payable_amount }}
                                    </div>

                                </div>
                            </div>
                        </div>

                        <!-- Items Table -->
                        <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                            <div class="border-b border-border bg-muted/30 px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <Package2 class="h-5 w-5 text-violet-500" />
                                    <h3 class="text-base font-semibold text-foreground">{{ t('item.item') }}</h3>
                                </div>
                            </div>
                            <div class="space-y-3 p-4 md:hidden">
                                <div
                                    v-for="(item, index) in purchase.items"
                                    :key="item.id"
                                    class="rounded-xl border border-border bg-background/70 p-4"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                                #{{ index + 1 }}
                                            </div>
                                            <div class="truncate text-sm font-semibold text-foreground">
                                                {{ item.item_name }}
                                            </div>
                                            <div class="text-xs text-muted-foreground">
                                                {{ item.item_code || '-' }}
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-xs text-muted-foreground">{{ t('general.total') }}</div>
                                            <div class="text-sm font-semibold text-violet-600 dark:text-violet-400">
                                                {{ purchase.transaction?.currency?.symbol || '' }} {{ formatLineValue(item.subtotal) }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                        <div>
                                            <div class="text-xs text-muted-foreground">{{ t('general.batch') }}</div>
                                            <div class="font-medium text-foreground">{{ item.batch || '-' }}</div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-muted-foreground">{{ t('general.expire_date') }}</div>
                                            <div class="font-medium text-foreground">{{ item.expire_date || '-' }}</div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-muted-foreground">{{ t('general.qty') }}</div>
                                            <div class="font-medium text-foreground">{{ item.quantity }}</div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-muted-foreground">{{ t('general.unit') }}</div>
                                            <div class="font-medium text-foreground">{{ item.unit_measure_name || '-' }}</div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-muted-foreground">{{ t('general.price') }}</div>
                                            <div class="font-medium text-foreground">{{ formatLineValue(item.unit_price) }}</div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-muted-foreground">{{ t('general.discount') }}</div>
                                            <div class="font-medium text-foreground">{{ formatLineValue(item.discount) }}</div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-muted-foreground">{{ t('general.free') }}</div>
                                            <div class="font-medium text-foreground">{{ formatLineValue(item.free) }}</div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-muted-foreground">{{ t('general.tax') }}</div>
                                            <div class="font-medium text-foreground">{{ formatLineValue(item.tax) }}</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-xl border border-border bg-muted/20 p-4">
                                    <div class="space-y-2 text-sm">
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="text-muted-foreground">{{ t('general.qty') }}</span>
                                            <span class="font-semibold text-foreground">
                                                {{ purchase.items?.reduce((sum, item) => sum + parseFloat(item.quantity || 0), 0) }}
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="text-muted-foreground">{{ t('general.amount') }}</span>
                                            <span class="font-semibold text-foreground">{{ formattedTotalAmount }}</span>
                                        </div>
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="text-muted-foreground">{{ t('general.discount') }}</span>
                                            <span class="font-semibold text-foreground">{{ formattedTotalDiscount }}</span>
                                        </div>
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="text-muted-foreground">{{ t('general.free') }}</span>
                                            <span class="font-semibold text-foreground">
                                                {{ purchase.items?.reduce((sum, item) => sum + parseFloat(item.free || 0), 0) }}
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="text-muted-foreground">{{ t('general.tax') }}</span>
                                            <span class="font-semibold text-foreground">{{ formattedTotalTax }}</span>
                                        </div>
                                        <div class="flex items-center justify-between gap-3 border-t border-border pt-2">
                                            <span class="text-muted-foreground">{{ t('general.total') }}</span>
                                            <span class="text-base font-bold text-violet-600 dark:text-violet-400">
                                                {{ purchase.transaction?.currency?.symbol || '' }} {{ formattedGrandTotal }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="hidden overflow-x-auto md:block">
                                <table class="w-full text-sm">
                                    <thead class="border-b border-border bg-muted/40">
                                        <tr>
                                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">#</th>
                                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('item.item') }}</th>
                                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('general.batch') }}</th>
                                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('general.expire_date') }}</th>
                                            <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('general.qty') }}</th>
                                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('general.unit') }}</th>
                                            <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('general.price') }}</th>
                                            <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('general.discount') }}</th>
                                            <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('general.free') }}</th>
                                            <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('general.tax') }}</th>
                                            <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('general.total') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border">
                                        <tr
                                            v-for="(item, index) in purchase.items"
                                            :key="item.id"
                                            class="bg-background/40 transition-colors hover:bg-muted/40"
                                        >
                                            <td class="px-3 py-3 text-foreground">{{ index + 1 }}</td>
                                            <td class="px-3 py-3 text-foreground">
                                                <div>
                                                    <div class="font-medium">{{ item.item_name }}</div>
                                                    <div class="text-xs text-muted-foreground">{{ item.item_code }}</div>
                                                </div>
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
                                            <td class="px-3 py-4 text-right text-sm font-semibold text-foreground">
                                                {{ purchase.items?.reduce((sum, item) => sum + parseFloat(item.quantity || 0), 0) }}
                                            </td>
                                            <td class="px-3 py-4"></td>
                                            <td class="px-3 py-4 text-right text-sm font-semibold text-foreground">{{ formattedTotalAmount }}</td>
                                            <td class="px-3 py-4 text-right text-sm font-semibold text-foreground">{{ formattedTotalDiscount }}</td>
                                            <td class="px-3 py-4 text-right text-sm font-semibold text-foreground">
                                                {{ purchase.items?.reduce((sum, item) => sum + parseFloat(item.free || 0), 0) }}
                                            </td>
                                            <td class="px-3 py-4 text-right text-sm font-semibold text-foreground">{{ formattedTotalTax }}</td>
                                            <td class="px-3 py-4 text-right text-lg font-bold text-violet-600 dark:text-violet-400">
                                                {{ purchase.transaction?.currency?.symbol || '' }} {{ formattedGrandTotal }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <DialogFooter class="border-t border-border bg-background/95 px-6 py-4">
                    <div class="flex w-full flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div v-if="purchase?.status === 'pending'" class="flex flex-col gap-2 sm:flex-row">
                            <Button
                                @click="handleReject"
                                :disabled="form.processing"
                                variant="destructive"
                                class="flex items-center gap-2"
                            >
                                <XCircle class="h-4 w-4" />
                                {{ t('general.reject') }}
                            </Button>
                            <Button
                                @click="handleApprove"
                                :disabled="form.processing"
                                class="flex items-center gap-2 bg-green-600 text-white hover:bg-green-700"
                            >
                                <CheckCircle2 class="h-4 w-4" />
                                {{ t('general.approve') }}
                            </Button>
                        </div>
                        <Button variant="outline" @click="closeDialog">
                            {{ t('general.close') }}
                        </Button>
                    </div>
                </DialogFooter>
            </div>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
td, th {
    white-space: nowrap;
}
</style>
