<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
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
import { Package2, FileText, User, Calendar, DollarSign, FileCheck } from 'lucide-vue-next';

const { t } = useI18n();
const { toast } = useToast();

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    saleId: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['update:modelValue']);

const sale = ref(null);
const loading = ref(false);
const totalAmount = ref(0);
const totalDiscount = ref(0);
const totalTax = ref(0);
const grandTotal = ref(0);

const statusBadgeClasses = computed(() => {
    if (!sale.value) return 'border-border bg-muted text-foreground';
    switch (sale.value.status) {
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

const formattedTotalAmount = computed(() => Number(totalAmount.value || 0).toFixed(2));
const formattedTotalDiscount = computed(() => Number(totalDiscount.value || 0).toFixed(2));
const formattedTotalTax = computed(() => Number(totalTax.value || 0).toFixed(2));
const formattedGrandTotal = computed(() => Number(grandTotal.value || 0).toFixed(2));

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

const resetSale = () => {
    sale.value = null;
    totalAmount.value = 0;
    totalDiscount.value = 0;
    totalTax.value = 0;
    grandTotal.value = 0;
};

const calculateTotals = () => {
    totalAmount.value = 0;
    totalDiscount.value = 0;
    totalTax.value = 0;
    grandTotal.value = 0;

    if (!sale.value?.items?.length) {
        return;
    }

    totalAmount.value = Number(sale.value.items.reduce((sum, item) => sum + Number(item.subtotal || 0), 0));

    let discount = 0;
    discount += Number(sale.value.items.reduce((sum, item) => sum + Number(item.discount || 0), 0));
    if (sale.value.discount && sale.value.discount_type === 'percentage') {
        discount += Number(totalAmount.value * sale.value.discount) / 100;
    } else if (sale.value.discount) {
        discount += Number(sale.value.discount);
    }
    totalDiscount.value = Number(discount);

    totalTax.value = Number(sale.value.items.reduce((sum, item) => sum + Number(item.tax || 0), 0));
    grandTotal.value = Number(totalAmount.value - totalDiscount.value + totalTax.value);
};

const loadSale = async (id) => {
    if (!id) return;

    loading.value = true;
    try {
        const response = await axios.get(`/sales/${id}`);
        sale.value = response.data.data;
        calculateTotals();
    } catch (error) {
        console.error('Error loading sale:', error);
        toast({
            title: t('general.error'),
            description: 'Failed to load sale details',
            variant: 'destructive',
            class: 'bg-red-600 text-white',
        });
    } finally {
        loading.value = false;
    }
};

watch(
    () => props.saleId,
    async (newId) => {
        if (newId && props.modelValue) {
            await loadSale(newId);
        }
    }
);

watch(
    () => props.modelValue,
    async (isOpen) => {
        if (isOpen && props.saleId) {
            await loadSale(props.saleId);
        } else if (!isOpen) {
            resetSale();
        }
    }
);

const closeDialog = () => {
    emit('update:modelValue', false);
};
</script>

<template>
    <Dialog :open="modelValue" @update:open="emit('update:modelValue', $event)">
        <DialogContent class="max-w-6xl overflow-hidden p-0">
            <div class="w-full rounded-2xl bg-background text-foreground shadow-2xl">
                <DialogHeader class="border-b border-border bg-gradient-to-r from-violet-400/10 via-background to-background px-6 py-5 text-left dark:from-violet-900/30">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <Package2 class="h-6 w-6 text-violet-500" />
                            <DialogTitle class="text-xl font-semibold text-foreground">
                                {{ t('sale.sale') }} #{{ sale?.number }}
                            </DialogTitle>
                        </div>
                        <Badge :class="statusBadgeClasses">
                            {{ getStatusLabel(sale?.status) }}
                        </Badge>
                    </div>
                    <DialogDescription v-if="sale?.description" class="text-muted-foreground">
                        {{ sale.description }}
                    </DialogDescription>
                </DialogHeader>

                <div class="max-h-[calc(90vh-8.5rem)] space-y-6 overflow-y-auto px-6 py-5">
                    <div v-if="loading" class="flex items-center justify-center py-8">
                        <div class="text-muted-foreground">{{ t('general.loading') }}...</div>
                    </div>

                    <div v-else-if="sale" class="space-y-6">
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
                                    <div class="text-sm font-medium text-foreground">{{ sale.date }}</div>
                                </div>
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <User class="h-3 w-3" />
                                        {{ t('ledger.customer.customer') }}
                                    </div>
                                    <div class="text-sm font-medium text-foreground">{{ sale.customer_name || '-' }}</div>
                                </div>
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <FileCheck class="h-3 w-3" />
                                        {{ t('general.type') }}
                                    </div>
                                    <div class="text-sm font-medium text-foreground">{{ sale.type || '-' }}</div>
                                </div>
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <DollarSign class="h-3 w-3" />
                                        {{ t('general.amount') }}
                                    </div>
                                    <div class="text-sm font-medium text-foreground">
                                        {{ sale.transaction?.currency?.symbol || '' }} {{ formattedGrandTotal }}
                                    </div>
                                </div>
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <User class="h-3 w-3" />
                                        {{ t('general.created_by') }}
                                    </div>
                                    <div class="text-sm font-medium text-foreground">{{ sale.created_by?.name || '-' }}</div>
                                </div>
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <User class="h-3 w-3" />
                                        {{ t('general.updated_by') }}
                                    </div>
                                    <div class="text-sm font-medium text-foreground">{{ sale.updated_by?.name || '-' }}</div>
                                </div>
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <Calendar class="h-3 w-3" />
                                        {{ t('general.updated_at') }}
                                    </div>
                                    <div class="text-sm font-medium text-foreground">{{ sale.updated_at || '-' }}</div>
                                </div>
                                <div class="space-y-1.5" v-if="sale.raw_type === 'credit'">
                                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <DollarSign class="h-3 w-3" />
                                        {{ t('general.paid_total') }}
                                    </div>
                                    <div class="text-sm font-medium text-foreground">
                                        {{ sale.transaction?.currency?.symbol || '' }} {{ formattedGrandTotal - sale.receivable_amount }}
                                    </div>

                                </div>
                                <div class="space-y-1.5" v-if="sale.raw_type === 'credit'">
                                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <DollarSign class="h-3 w-3" />
                                        {{ t('general.receivable_amount') }}
                                    </div>
                                    <div class="text-sm font-medium text-foreground">
                                        {{ sale.transaction?.currency?.symbol || '' }} {{ sale.receivable_amount }}
                                    </div>

                                </div>
                            </div>
                        </div>

                        <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                            <div class="border-b border-border bg-muted/30 px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <Package2 class="h-5 w-5 text-violet-500" />
                                    <h3 class="text-base font-semibold text-foreground">{{ t('item.item') }}</h3>
                                </div>
                            </div>
                            <div class="overflow-x-auto">
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
                                            v-for="(item, index) in sale.items"
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
                                                {{ sale.items?.reduce((sum, item) => sum + parseFloat(item.quantity || 0), 0) }}
                                            </td>
                                            <td class="px-3 py-4"></td>
                                            <td class="px-3 py-4 text-right text-sm font-semibold text-foreground">{{ formattedTotalAmount }}</td>
                                            <td class="px-3 py-4 text-right text-sm font-semibold text-foreground">{{ formattedTotalDiscount }}</td>
                                            <td class="px-3 py-4 text-right text-sm font-semibold text-foreground">
                                                {{ sale.items?.reduce((sum, item) => sum + parseFloat(item.free || 0), 0) }}
                                            </td>
                                            <td class="px-3 py-4 text-right text-sm font-semibold text-foreground">{{ formattedTotalTax }}</td>
                                            <td class="px-3 py-4 text-right text-lg font-bold text-violet-600 dark:text-violet-400">
                                                {{ sale.transaction?.currency?.symbol || '' }} {{ formattedGrandTotal }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter class="border-t border-border bg-background/95 px-6 py-4">
                    <div class="flex w-full items-center justify-end gap-3">
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
