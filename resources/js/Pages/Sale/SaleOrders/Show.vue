<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { router, usePage } from '@inertiajs/vue3';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Package2, FileText, User, Calendar, DollarSign, FileCheck, Truck } from 'lucide-vue-next';
import { useToast } from '@/Components/ui/toast/use-toast';
import TransactionActionDialog from '@/Components/TransactionActionDialog.vue';
import ConfirmDeleteDialog from '@/Components/next/ConfirmDeleteDialog.vue';
import ShowPageToolbar from '@/Components/ShowPageToolbar.vue';
import { useAuth } from '@/composables/useAuth';

const { t } = useI18n();
const { toast } = useToast();
const page = usePage();
const { can } = useAuth();

const props = defineProps({
    saleOrder: { type: Object, required: true },
});

const orderData = computed(() => props.saleOrder?.data ?? props.saleOrder ?? {});

const totalAmount = computed(() =>
    Number(orderData.value.items?.reduce((sum, item) => sum + Number(item.line_total || 0), 0) || 0)
);
const totalQuantity = computed(() =>
    Number(orderData.value.items?.reduce((sum, item) => sum + Number(item.quantity || 0), 0) || 0)
);
const formattedTotalAmount = computed(() => totalAmount.value.toFixed(2));
const formatLineValue = (value) => Number(value || 0).toFixed(2);
const currencySymbol = computed(() => orderData.value.currency?.symbol || '');

const statusBadgeClasses = computed(() => {
    switch (orderData.value.status) {
        case 'draft': return 'border-gray-500/30 bg-gray-500/10 text-gray-700 dark:text-gray-300';
        case 'posted': return 'border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-300';
        case 'completed': return 'border-green-500/30 bg-green-500/10 text-green-700 dark:text-green-300';
        case 'cancelled': return 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300';
        default: return 'border-border bg-muted text-foreground';
    }
});

const postDialogOpen = ref(false);
const cancelDialogOpen = ref(false);

const postSaleOrder = () => {
    router.post(route('sale-orders.post', orderData.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            const flashError = page.props.flash?.error;
            if (flashError) {
                toast({ title: t('general.error') ?? 'Error', description: flashError, variant: 'destructive' });
                return;
            }
            postDialogOpen.value = false;
        },
    });
};

const cancelSaleOrder = () => {
    router.post(route('sale-orders.cancel', orderData.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => { cancelDialogOpen.value = false },
    });
};

const canCancel = computed(() => orderData.value.status === 'draft' && can('sale_orders.update'));
</script>

<template>
    <AppLayout :title="`${t('sale_order.sale_order')} #${orderData.number}`">
        <div class="space-y-6">
            <ShowPageToolbar
                back-route="sale-orders.index"
                :status="orderData.status"
                :edit-route="orderData.id ? route('sale-orders.edit', orderData.id) : null"
                edit-permission="sale_orders.update"
                @post="postDialogOpen = true"
            >
                <Button v-if="canCancel" variant="destructive" size="sm" @click="cancelDialogOpen = true">
                    {{ t('sale_order.cancel_sale_order') }}
                </Button>
            </ShowPageToolbar>

            <TransactionActionDialog
                v-model:open="postDialogOpen"
                type="post"
                :title="t('sale_order.post_sale_order')"
                :description="t('sale_order.post_sale_order_description')"
                @confirm="postSaleOrder"
            />
            <ConfirmDeleteDialog
                v-model:open="cancelDialogOpen"
                :title="t('sale_order.cancel_sale_order')"
                :description="t('sale_order.cancel_sale_order_description')"
                :cancel-text="t('general.cancel')"
                :continue-text="t('sale_order.cancel_sale_order')"
                @confirm="cancelSaleOrder"
            />

            <!-- Info card -->
            <fieldset class="rounded-xl border border-border bg-card px-5 pb-5 pt-3 text-card-foreground shadow-sm">
                <legend class="px-2 flex items-center gap-1.5">
                    <span class="text-sm font-semibold text-violet-500">{{ t('sale_order.sale_order') }} #{{ orderData.number }}</span>
                    <Badge :class="statusBadgeClasses">{{ orderData.status_label }}</Badge>
                    <a v-if="orderData.sale_id" :href="route('sales.show', orderData.sale_id)">
                        <Badge class="border-violet-500/30 bg-violet-500/10 text-violet-700 dark:text-violet-300 hover:underline">
                            {{ t('sale.sale') }} #{{ orderData.sale_number }}
                        </Badge>
                    </a>
                </legend>
                <div class="mb-4 flex items-center gap-2">
                    <FileText class="h-5 w-5 text-violet-500" />
                    <h3 class="text-base font-semibold text-foreground">{{ t('general.details') }}</h3>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <Calendar class="h-3 w-3" />{{ t('general.date') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ orderData.date }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <Truck class="h-3 w-3" />{{ t('sale_order.delivery_date') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ orderData.delivery_date || '-' }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <User class="h-3 w-3" />{{ t('ledger.customer.customer') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ orderData.customer_name || '-' }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <DollarSign class="h-3 w-3" />{{ t('general.amount') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ currencySymbol }} {{ formattedTotalAmount }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <FileCheck class="h-3 w-3" />{{ t('sale_order.linked_sale') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">
                            <a v-if="orderData.sale_id" :href="route('sales.show', orderData.sale_id)" class="text-violet-600 underline dark:text-violet-400">
                                #{{ orderData.sale_number }}
                            </a>
                            <span v-else>-</span>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <User class="h-3 w-3" />{{ t('general.created_by') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ orderData.created_by?.name || '-' }}</div>
                    </div>
                </div>
                <div v-if="orderData.note" class="mt-4 text-sm text-muted-foreground">
                    {{ orderData.note }}
                </div>

                <div v-if="orderData.status === 'posted' && can('sales.create')" class="flex justify-end mt-4">
                    <Button variant="outline" size="sm" @click="router.visit(route('sales.create', { sale_order_id: orderData.id }))">
                        {{ t('sale_order.convert_to_sale') }}
                    </Button>
                </div>
            </fieldset>

            <!-- Items table -->
            <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="border-b border-border bg-muted/30 px-4 py-3 flex items-center gap-2">
                    <Package2 class="h-5 w-5 text-violet-500" />
                    <h3 class="text-base font-semibold text-foreground">{{ t('item.item') }}</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-border bg-muted/40">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">#</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('item.item') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('general.batch') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('general.qty') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('general.unit') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('general.price') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('general.total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="(item, index) in orderData.items" :key="item.id"
                                class="bg-background/40 transition-colors hover:bg-muted/40">
                                <td class="px-3 py-3 text-foreground">{{ index + 1 }}</td>
                                <td class="px-3 py-3 text-foreground">
                                    <div class="font-medium">{{ item.item_name }}</div>
                                    <div class="text-xs text-muted-foreground">{{ item.item_code }}</div>
                                </td>
                                <td class="px-3 py-3 text-foreground">{{ item.batch || '-' }}</td>
                                <td class="px-3 py-3 text-right text-foreground">{{ item.quantity }}</td>
                                <td class="px-3 py-3 text-foreground">{{ item.unit_measure_name }}</td>
                                <td class="px-3 py-3 text-right text-foreground">{{ formatLineValue(item.unit_price) }}</td>
                                <td class="px-3 py-3 text-right font-semibold text-foreground">{{ formatLineValue(item.line_total) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t border-border bg-muted/30">
                            <tr>
                                <td colspan="3" class="px-3 py-4 text-right text-sm font-semibold text-foreground">{{ t('general.total') }}:</td>
                                <td class="px-3 py-4 text-right text-sm font-semibold text-foreground">{{ totalQuantity }}</td>
                                <td class="px-3 py-4"></td>
                                <td class="px-3 py-4 text-right text-lg font-bold text-violet-600 dark:text-violet-400">{{ currencySymbol }} {{ formattedTotalAmount }}</td>
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
