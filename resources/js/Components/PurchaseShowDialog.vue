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
    if (!purchase.value) return 'bg-gray-100 text-gray-800 border-gray-300';
    switch (purchase.value.status) {
        case 'approved':
            return 'bg-green-100 text-green-800 border-green-300';
        case 'rejected':
            return 'bg-red-100 text-red-800 border-red-300';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800 border-yellow-300';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-300';
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
        <DialogContent class="max-w-6xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <Package2 class="w-6 h-6 text-violet-500" />
                        <DialogTitle class="text-xl">
                            {{ t('purchase.purchase') }} #{{ purchase?.number }}
                        </DialogTitle>
                    </div>
                    <Badge :class="statusBadgeClasses">
                        {{ getStatusLabel(purchase?.status) }}
                    </Badge>
                </div>
                <DialogDescription v-if="purchase?.description">
                    {{ purchase.description }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="loading" class="flex items-center justify-center py-8">
                <div class="text-gray-500">{{ t('general.loading') }}...</div>
            </div>

            <div v-else-if="purchase" class="space-y-6">
                <!-- General Information Card -->
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <div class="flex items-center gap-2 mb-3">
                        <FileText class="w-5 h-5 text-violet-500" />
                        <h3 class="text-base font-semibold text-gray-900">{{ t('general.info') }}</h3>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-gray-500">
                                <Calendar class="w-3 h-3" />
                                {{ t('general.date') }}
                            </div>
                            <div class="text-sm font-medium text-gray-900">{{ purchase.date }}</div>
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-gray-500">
                                <User class="w-3 h-3" />
                                {{ t('ledger.supplier.supplier') }}
                            </div>
                            <div class="text-sm font-medium text-gray-900">{{ purchase.supplier_name || '-' }}</div>
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-gray-500">
                                <FileCheck class="w-3 h-3" />
                                {{ t('general.type') }}
                            </div>
                            <div class="text-sm font-medium text-gray-900">{{ purchase.type || '-' }}</div>
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-gray-500">
                                <DollarSign class="w-3 h-3" />
                                {{ t('general.amount') }}
                            </div>
                            <div class="text-sm font-medium text-gray-900">
                                {{ purchase.transaction?.currency?.symbol || '' }} {{ formattedGrandTotal }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="border border-gray-200 rounded-lg overflow-hidden">
                    <div class="bg-gray-50 px-4 py-2 border-b border-gray-200">
                        <div class="flex items-center gap-2">
                            <Package2 class="w-5 h-5 text-violet-500" />
                            <h3 class="text-base font-semibold text-gray-900">{{ t('item.item') }}s</h3>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">#</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">{{ t('item.item') }}</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">{{ t('general.batch') }}</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">{{ t('general.expire_date') }}</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">{{ t('general.qty') }}</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">{{ t('general.unit') }}</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">{{ t('general.price') }}</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">{{ t('general.discount') }}</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">{{ t('general.free') }}</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">{{ t('general.tax') }}</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">{{ t('general.total') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="(item, index) in purchase.items" :key="item.id" class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-gray-900">{{ index + 1 }}</td>
                                    <td class="px-3 py-2 text-gray-900">
                                        <div>
                                            <div class="font-medium">{{ item.item_name }}</div>
                                            <div class="text-xs text-gray-500">{{ item.item_code }}</div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-gray-900">{{ item.batch || '-' }}</td>
                                    <td class="px-3 py-2 text-gray-900">{{ item.expire_date || '-' }}</td>
                                    <td class="px-3 py-2 text-gray-900 text-right">{{ item.quantity }}</td>
                                    <td class="px-3 py-2 text-gray-900">{{ item.unit_measure_name }}</td>
                                    <td class="px-3 py-2 text-gray-900 text-right">{{ item.unit_price }}</td>
                                    <td class="px-3 py-2 text-gray-900 text-right">{{ item.discount || 0 }}</td>
                                    <td class="px-3 py-2 text-gray-900 text-right">{{ item.free || 0 }}</td>
                                    <td class="px-3 py-2 text-gray-900 text-right">{{ item.tax || 0 }}</td>
                                    <td class="px-3 py-2 font-semibold text-gray-900 text-right">{{ item.subtotal ? Number(item.subtotal).toFixed(2) : '0.00' }}</td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                                <tr>
                                    <td colspan="4" class="px-3 py-2 text-sm font-semibold text-gray-900 text-right">{{ t('general.total') }}:</td>
                                    <td class="px-3 py-2 text-sm font-semibold text-gray-900 text-right">
                                        {{ purchase.items?.reduce((sum, item) => sum + parseFloat(item.quantity || 0), 0) }}
                                    </td>
                                    <td class="px-3 py-2"></td>
                                    <td class="px-3 py-2 text-sm font-semibold text-gray-900 text-right">{{ formattedTotalAmount }}</td>
                                    <td class="px-3 py-2 text-sm font-semibold text-gray-900 text-right">{{ formattedTotalDiscount }}</td>
                                    <td class="px-3 py-2 text-sm font-semibold text-gray-900 text-right">
                                        {{ purchase.items?.reduce((sum, item) => sum + parseFloat(item.free || 0), 0) }}
                                    </td>
                                    <td class="px-3 py-2 text-sm font-semibold text-gray-900 text-right">{{ formattedTotalTax }}</td>
                                    <td class="px-3 py-2 text-lg font-bold text-violet-600 text-right">
                                        {{ purchase.transaction?.currency?.symbol || '' }} {{ formattedGrandTotal }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <DialogFooter>
                <div class="flex items-center justify-between w-full">
                    <div v-if="purchase?.status === 'pending'" class="flex gap-2">
                        <Button
                            @click="handleReject"
                            :disabled="form.processing"
                            variant="destructive"
                            class="flex items-center gap-2"
                        >
                            <XCircle class="w-4 h-4" />
                            {{ t('general.reject') }}
                        </Button>
                        <Button
                            @click="handleApprove"
                            :disabled="form.processing"
                            class="flex items-center gap-2 bg-green-600 hover:bg-green-700"
                        >
                            <CheckCircle2 class="w-4 h-4" />
                            {{ t('general.approve') }}
                        </Button>
                    </div>
                    <Button variant="outline" @click="closeDialog">
                        {{ t('general.close') }}
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
td, th {
    white-space: nowrap;
}
</style>

