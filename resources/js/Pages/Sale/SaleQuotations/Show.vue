<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDocumentAction } from '@/composables/useDocumentAction'
import { router, usePage } from '@inertiajs/vue3';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Package2, FileText, User, Calendar, DollarSign, Warehouse, Percent, Clock, CalendarClock } from 'lucide-vue-next';
import { useToast } from '@/Components/ui/toast/use-toast';
import TransactionActionDialog from '@/Components/TransactionActionDialog.vue';
import ConfirmDeleteDialog from '@/Components/next/ConfirmDeleteDialog.vue';
import ShowPageToolbar from '@/Components/ShowPageToolbar.vue';
import { useAuth } from '@/composables/useAuth';

const { t } = useI18n();
const { submit, processing } = useDocumentAction()
const { toast } = useToast();
const page = usePage();
const { can } = useAuth();

const props = defineProps({
    saleQuotation: { type: Object, required: true },
});

const data = computed(() => props.saleQuotation?.data ?? props.saleQuotation ?? {});

const toNumber = (value) => Number(value || 0)
const formatMoney = (value) => toNumber(value).toFixed(2)
const formatQty = (value) => Number(toNumber(value).toFixed(4))
const currencySymbol = computed(() => data.value.currency?.symbol || '');
const isBaseCurrency = computed(() => data.value.currency?.is_base_currency === true);

// What the lines come to before the bill discount: quantity at price, less the
// line discounts. The bill discount is taken on the value of goods, as the form
// and the document made from this one both take it.
const goodsTotal = computed(() => (data.value.items || []).reduce((sum, item) => sum + toNumber(item.quantity) * toNumber(item.unit_price), 0))
const lineDiscountTotal = computed(() => (data.value.items || []).reduce((sum, item) => sum + toNumber(item.discount), 0))
const billDiscountAmount = computed(() => data.value.discount_type === 'percentage'
    ? goodsTotal.value * (toNumber(data.value.discount) / 100)
    : toNumber(data.value.discount))
const grandTotal = computed(() => data.value.quotation_total ?? (goodsTotal.value - lineDiscountTotal.value - billDiscountAmount.value))
const totalQuantity = computed(() => (data.value.items || []).reduce((sum, item) => sum + toNumber(item.quantity), 0))
const totalFree = computed(() => (data.value.items || []).reduce((sum, item) => sum + toNumber(item.free), 0))
const billDiscountLabel = computed(() => data.value.discount_type === 'percentage'
    ? `${formatQty(data.value.discount)}%`
    : `${currencySymbol.value} ${formatMoney(data.value.discount)}`)
const hasBillDiscount = computed(() => toNumber(data.value.discount) > 0)
const showFree = computed(() => totalFree.value > 0)
const showLineDiscount = computed(() => lineDiscountTotal.value > 0)

const statusBadgeClasses = computed(() => {
    switch (data.value.status) {
        case 'draft': return 'border-gray-500/30 bg-gray-500/10 text-gray-700 dark:text-gray-300';
        case 'posted': return 'border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-300';
        case 'cancelled': return 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300';
        default: return 'border-border bg-muted text-foreground';
    }
});

const postDialogOpen = ref(false);
const cancelDialogOpen = ref(false);

const postSaleQuotation = () => {
    submit(route('sale-quotations.post', data.value.id), {}, {
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

const cancelSaleQuotation = () => {
    submit(route('sale-quotations.cancel', data.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => { cancelDialogOpen.value = false },
    });
};

const canCancel = computed(() => data.value.status === 'draft' && can('sale_quotations.update'));
const printUrl = computed(() => data.value.id ? route('sale-quotations.print', data.value.id) : null);
</script>

<template>
    <AppLayout :title="`${t('sale_quotation.sale_quotation')} #${data.number}`">
        <div class="space-y-6">
            <ShowPageToolbar
                back-route="sale-quotations.index"
                :status="data.status"
                :edit-route="data.id ? route('sale-quotations.edit', data.id) : null"
                edit-permission="sale_quotations.update"
                :print-url="printUrl"
                @post="postDialogOpen = true"
                @reverse="cancelDialogOpen = true"
            >
                <Button v-if="canCancel" variant="destructive" size="sm" @click="cancelDialogOpen = true">
                    {{ t('sale_quotation.cancel_sale_quotation') }}
                </Button>
            </ShowPageToolbar>

            <TransactionActionDialog
                :processing="processing"
                v-model:open="postDialogOpen"
                type="post"
                :title="t('sale_quotation.post_sale_quotation')"
                :description="t('sale_quotation.post_sale_quotation_description')"
                @confirm="postSaleQuotation"
            />
            <ConfirmDeleteDialog
                v-model:open="cancelDialogOpen"
                :title="t('sale_quotation.cancel_sale_quotation')"
                :description="t('sale_quotation.cancel_sale_quotation_description')"
                :cancel-text="t('general.cancel')"
                :continue-text="t('sale_quotation.cancel_sale_quotation')"
                @confirm="cancelSaleQuotation"
            />

            <!-- Info card -->
            <fieldset class="rounded-xl border border-border bg-card px-5 pb-5 pt-3 text-card-foreground shadow-sm">
                <legend class="px-2 flex items-center gap-1.5">
                    <span class="text-sm font-semibold text-violet-500">{{ t('sale_quotation.sale_quotation') }} #{{ data.number }}</span>
                    <Badge :class="statusBadgeClasses">{{ data.status_label }}</Badge>
                </legend>
                <div class="mb-4 flex items-center gap-2">
                    <FileText class="h-5 w-5 text-violet-500" />
                    <h3 class="text-base font-semibold text-foreground">{{ t('general.details') }}</h3>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <User class="h-3 w-3" />{{ t('ledger.customer.customer') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ data.customer_name || '-' }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <Calendar class="h-3 w-3" />{{ t('general.date') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ data.date }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <CalendarClock class="h-3 w-3" />{{ t('sale_quotation.valid_until') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ data.valid_until || '-' }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <Warehouse class="h-3 w-3" />{{ t('admin.warehouse.warehouse') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ data.warehouse?.name || '-' }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <DollarSign class="h-3 w-3" />{{ t('admin.currency.currency') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">
                            {{ data.currency?.name || data.currency?.code || '-' }}
                            <span v-if="data.rate && !isBaseCurrency" class="text-xs text-muted-foreground">({{ t('general.rate') }}: {{ data.rate }})</span>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <Percent class="h-3 w-3" />{{ t('general.bill_discount') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ hasBillDiscount ? billDiscountLabel : '-' }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <DollarSign class="h-3 w-3" />{{ t('general.amount') }}
                        </div>
                        <div class="text-sm font-semibold text-foreground">{{ currencySymbol }} {{ formatMoney(grandTotal) }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <User class="h-3 w-3" />{{ t('general.created_by') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ data.created_by?.name || '-' }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <Clock class="h-3 w-3" />{{ t('general.created_at') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ data.created_at || '-' }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <User class="h-3 w-3" />{{ t('general.updated_by') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ data.updated_by?.name || '-' }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <Clock class="h-3 w-3" />{{ t('general.updated_at') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ data.updated_at || '-' }}</div>
                    </div>
                </div>
                <div v-if="data.note" class="mt-4 rounded-lg bg-muted/40 p-3 text-sm">
                    <div class="mb-1 text-xs text-muted-foreground">{{ t('general.note') }}</div>
                    <div class="whitespace-pre-line text-foreground">{{ data.note }}</div>
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
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">#</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('item.item') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('item.variant') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('general.batch') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('general.expire_date') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-left">{{ t('general.qty') }}</th>
                                <th v-if="showFree" class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-left">{{ t('general.free') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('general.unit') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-left">{{ t('general.price') }}</th>
                                <th v-if="showLineDiscount" class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-left">{{ t('general.discount') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-left">{{ t('general.total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="(item, index) in data.items" :key="item.id"
                                class="bg-background/40 transition-colors hover:bg-muted/40">
                                <td class="px-3 py-3 text-foreground">{{ index + 1 }}</td>
                                <td class="px-3 py-3 text-foreground">
                                    <div class="font-medium">{{ item.item_name }}</div>
                                    <div class="text-xs text-muted-foreground">{{ item.item_code }}</div>
                                </td>
                                <td class="px-3 py-3 text-foreground">{{ item.variant?.display_name || '-' }}</td>
                                <td class="px-3 py-3 text-foreground">{{ item.batch || '-' }}</td>
                                <td class="px-3 py-3 text-foreground">{{ item.expire_date || '-' }}</td>
                                <td class="px-3 py-3 text-right text-foreground rtl:text-left">{{ formatQty(item.quantity) }}</td>
                                <td v-if="showFree" class="px-3 py-3 text-right text-foreground rtl:text-left">{{ formatQty(item.free) }}</td>
                                <td class="px-3 py-3 text-foreground">{{ item.unit_measure_name || '-' }}</td>
                                <td class="px-3 py-3 text-right text-foreground rtl:text-left">{{ formatMoney(item.unit_price) }}</td>
                                <td v-if="showLineDiscount" class="px-3 py-3 text-right text-foreground rtl:text-left">{{ formatMoney(item.discount) }}</td>
                                <td class="px-3 py-3 text-right font-semibold text-foreground rtl:text-left">{{ formatMoney(item.line_total) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t border-border bg-muted/30">
                            <tr>
                                <td colspan="5" class="px-3 py-3 text-right text-sm font-semibold text-foreground rtl:text-left">{{ t('general.total') }}:</td>
                                <td class="px-3 py-3 text-right text-sm font-semibold text-foreground rtl:text-left">{{ formatQty(totalQuantity) }}</td>
                                <td v-if="showFree" class="px-3 py-3 text-right text-sm font-semibold text-foreground rtl:text-left">{{ formatQty(totalFree) }}</td>
                                <td></td>
                                <td></td>
                                <td v-if="showLineDiscount" class="px-3 py-3 text-right text-sm font-semibold text-foreground rtl:text-left">{{ formatMoney(lineDiscountTotal) }}</td>
                                <td class="px-3 py-3 text-right text-sm font-semibold text-foreground rtl:text-left">{{ formatMoney(goodsTotal - lineDiscountTotal) }}</td>
                            </tr>
                            <tr v-if="hasBillDiscount">
                                <td :colspan="showFree && showLineDiscount ? 10 : showFree || showLineDiscount ? 9 : 8" class="px-3 py-2 text-right text-sm text-muted-foreground rtl:text-left">{{ t('general.bill_discount') }} ({{ billDiscountLabel }}):</td>
                                <td class="px-3 py-2 text-right text-sm text-foreground rtl:text-left">- {{ formatMoney(billDiscountAmount) }}</td>
                            </tr>
                            <tr>
                                <td :colspan="showFree && showLineDiscount ? 10 : showFree || showLineDiscount ? 9 : 8" class="px-3 py-4 text-right text-sm font-semibold text-foreground rtl:text-left">{{ t('sale_quotation.quotation_total') }}:</td>
                                <td class="px-3 py-4 text-right text-lg font-bold text-violet-600 dark:text-violet-400 rtl:text-left">{{ currencySymbol }} {{ formatMoney(grandTotal) }}</td>
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
