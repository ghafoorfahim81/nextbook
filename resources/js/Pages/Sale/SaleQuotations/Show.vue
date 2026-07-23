<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { router, usePage } from '@inertiajs/vue3';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Package2, FileText, User, Calendar, DollarSign, CalendarClock } from 'lucide-vue-next';
import { useToast } from '@/Components/ui/toast/use-toast';
import TransactionActionDialog from '@/Components/TransactionActionDialog.vue';
import ConfirmDeleteDialog from '@/Components/next/ConfirmDeleteDialog.vue';
import ShowPageToolbar from '@/Components/ShowPageToolbar.vue';
import { useAuth } from '@/composables/useAuth';

import { useColors } from '@/composables/useColors';

const { t } = useI18n();
const { resolveColor } = useColors();
const { toast } = useToast();
const page = usePage();
const { can } = useAuth();

const props = defineProps({
    saleQuotation: { type: Object, required: true },
});

const quotationData = computed(() => props.saleQuotation?.data ?? props.saleQuotation ?? {});

const totalAmount = computed(() =>
    Number(quotationData.value.items?.reduce((sum, item) => sum + Number(item.line_total || 0), 0) || 0)
);
const totalQuantity = computed(() =>
    Number(quotationData.value.items?.reduce((sum, item) => sum + Number(item.quantity || 0), 0) || 0)
);
const formattedTotalAmount = computed(() => totalAmount.value.toFixed(2));
const formatLineValue = (value) => Number(value || 0).toFixed(2);
const currencySymbol = computed(() => quotationData.value.currency?.symbol || '');

const statusBadgeClasses = computed(() => {
    switch (quotationData.value.status) {
        case 'draft': return 'border-gray-500/30 bg-gray-500/10 text-gray-700 dark:text-gray-300';
        case 'posted': return 'border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-300';
        case 'cancelled': return 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300';
        default: return 'border-border bg-muted text-foreground';
    }
});

const postDialogOpen = ref(false);
const cancelDialogOpen = ref(false);

const postSaleQuotation = () => {
    router.post(route('sale-quotations.post', quotationData.value.id), {}, {
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
    router.post(route('sale-quotations.cancel', quotationData.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => { cancelDialogOpen.value = false },
    });
};

const canCancel = computed(() => quotationData.value.status !== 'cancelled' && can('sale_quotations.update'));
const printUrl = computed(() => quotationData.value.id ? route('sale-quotations.print', quotationData.value.id) : null);
</script>

<template>
    <AppLayout :title="`${t('sale_quotation.sale_quotation')} #${quotationData.number}`">
        <div class="space-y-6">
            <ShowPageToolbar
                back-route="sale-quotations.index"
                :status="quotationData.status"
                :edit-route="quotationData.id ? route('sale-quotations.edit', quotationData.id) : null"
                edit-permission="sale_quotations.update"
                :print-url="printUrl"
                @post="postDialogOpen = true"
            >
                <Button v-if="canCancel" variant="destructive" size="sm" @click="cancelDialogOpen = true">
                    {{ t('sale_quotation.cancel_sale_quotation') }}
                </Button>
            </ShowPageToolbar>

            <TransactionActionDialog
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
                    <span class="text-sm font-semibold text-violet-500">{{ t('sale_quotation.sale_quotation') }} #{{ quotationData.number }}</span>
                    <Badge :class="statusBadgeClasses">{{ quotationData.status_label }}</Badge>
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
                        <div class="text-sm font-medium text-foreground">{{ quotationData.date }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <CalendarClock class="h-3 w-3" />{{ t('sale_quotation.valid_until') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ quotationData.valid_until || '-' }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <User class="h-3 w-3" />{{ t('ledger.customer.customer') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ quotationData.customer_name || '-' }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <DollarSign class="h-3 w-3" />{{ t('general.amount') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ currencySymbol }} {{ formattedTotalAmount }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <User class="h-3 w-3" />{{ t('general.created_by') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ quotationData.created_by?.name || '-' }}</div>
                    </div>
                </div>
                <div v-if="quotationData.note" class="mt-4 text-sm text-muted-foreground">
                    {{ quotationData.note }}
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
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('item.color') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('item.size') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('general.qty') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('general.unit') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('general.price') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('general.total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="(item, index) in quotationData.items" :key="item.id"
                                class="bg-background/40 transition-colors hover:bg-muted/40">
                                <td class="px-3 py-3 text-foreground">{{ index + 1 }}</td>
                                <td class="px-3 py-3 text-foreground">
                                    <div class="font-medium">{{ item.item_name }}</div>
                                    <div class="text-xs text-muted-foreground">{{ item.item_code }}</div>
                                </td>
                                <td class="px-3 py-3 text-foreground">{{ item.batch || '-' }}</td>
                                <td class="px-3 py-3 text-foreground">
                                    <span v-if="resolveColor(item.color)" class="flex items-center gap-1.5">
                                        <span class="h-3 w-3 shrink-0 rounded-full border border-muted-foreground/40" :style="{ backgroundColor: resolveColor(item.color).hex }" />
                                        {{ resolveColor(item.color).name }}
                                    </span>
                                    <span v-else>-</span>
                                </td>
                                <td class="px-3 py-3 text-foreground">{{ item.size_name || '-' }}</td>
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
