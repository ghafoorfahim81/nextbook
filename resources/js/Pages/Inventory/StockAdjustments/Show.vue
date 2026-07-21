<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import AttachmentList from '@/Components/AttachmentList.vue'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useColors } from '@/composables/useColors';
import { router, usePage } from '@inertiajs/vue3'
import { Badge } from '@/Components/ui/badge'
import { SlidersHorizontal, FileText, User, Calendar, Store, Tag, ArrowDownCircle, ArrowUpCircle } from 'lucide-vue-next'
import { useToast } from '@/Components/ui/toast/use-toast'
import TransactionActionDialog from '@/Components/TransactionActionDialog.vue'
import ShowPageToolbar from '@/Components/ShowPageToolbar.vue'

const { t } = useI18n()
const { resolveColor } = useColors();
const { toast } = useToast()
const page = usePage()

const props = defineProps({
    adjustment: { type: Object, required: true },
    reversal: { type: Object, default: null },
    originalDoc: { type: Object, default: null },
})

const adjustmentData = computed(() => props.adjustment?.data ?? props.adjustment ?? {})

const isOut = computed(() => adjustmentData.value.type === 'out')

const totalQuantity = computed(() =>
    Number(adjustmentData.value.items?.reduce((sum, item) => sum + Number(item.quantity || 0), 0) || 0)
)

const totalAmount = computed(() =>
    Number(adjustmentData.value.items?.reduce((sum, item) => sum + (Number(item.quantity || 0) * Number(item.unit_cost || 0)), 0) || 0)
)

const documentVoucherNumber = computed(() => adjustmentData.value.transaction?.voucher_number || adjustmentData.value.reference)
const originalVoucherNumber = computed(() => props.originalDoc?.voucher_number || documentVoucherNumber.value)
const reversalVoucherNumber = computed(() => props.reversal?.voucher_number || documentVoucherNumber.value)

const statusBadgeClasses = computed(() => {
    switch (adjustmentData.value.status) {
        case 'draft': return 'border-gray-500/30 bg-gray-500/10 text-gray-700 dark:text-gray-300'
        case 'posted': return 'border-green-500/30 bg-green-500/10 text-green-700 dark:text-green-300'
        case 'reversed': return 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300'
        default: return 'border-border bg-muted text-foreground'
    }
})

const postDialogOpen = ref(false)
const reverseDialogOpen = ref(false)

const postAdjustment = () => {
    router.post(route('stock-adjustments.post', adjustmentData.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            // A failed post redirects back with a flash error (e.g. stock consumed
            // by another document). Keep the dialog open and surface it.
            const flashError = page.props.flash?.error
            if (flashError) {
                toast({
                    title: t('general.error') ?? 'Error',
                    description: flashError,
                    variant: 'destructive',
                })
                return
            }
            postDialogOpen.value = false
        },
    })
}

const reverseAdjustment = (reason) => {
    router.post(route('stock-adjustments.reverse', adjustmentData.value.id), { reason }, {
        preserveScroll: true,
        onSuccess: () => { reverseDialogOpen.value = false },
    })
}
</script>

<template>
    <AppLayout :title="`${t('adjustment.stock_adjustment')} ${adjustmentData.reference || ''}`">
        <div class="space-y-6">
            <!-- Page header -->
            <ShowPageToolbar
                back-route="stock-adjustments.index"
                :status="adjustmentData.status"
                :edit-route="adjustmentData.id ? route('stock-adjustments.edit', adjustmentData.id) : null"
                edit-permission="stock_adjustment.update"
                @post="postDialogOpen = true"
                @reverse="reverseDialogOpen = true"
            />

            <TransactionActionDialog
                v-model:open="postDialogOpen"
                type="post"
                :title="t('general.post') + ' ' + t('adjustment.stock_adjustment')"
                :description="t('general.post_document_desc')"
                @confirm="postAdjustment"
            />
            <TransactionActionDialog
                v-model:open="reverseDialogOpen"
                type="reverse"
                :title="t('general.reverse') + ' ' + t('adjustment.stock_adjustment')"
                :description="t('general.reverse_description')"
                @confirm="reverseAdjustment"
            />

            <div v-if="originalDoc" class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-700 dark:text-amber-300">
                {{ t('general.reversal_of_transaction', { number: originalVoucherNumber }) }}.
            </div>
            <div v-if="adjustmentData.status === 'reversed' && reversal" class="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-300">
                {{ t('general.reversed_by') }} {{ reversalVoucherNumber }}.
            </div>

            <!-- Info card -->
            <fieldset class="rounded-xl border border-border bg-card px-5 pb-5 pt-3 text-card-foreground shadow-sm">
                <legend class="px-2 flex items-center gap-1.5">
                    <span class="text-sm font-semibold text-violet-500">{{ t('adjustment.stock_adjustment') }} {{ adjustmentData.reference }}</span>
                    <Badge :class="statusBadgeClasses">{{ adjustmentData.status_label }}</Badge>
                </legend>
                <div class="mb-4 flex items-center gap-2">
                    <FileText class="h-5 w-5 text-violet-500" />
                    <h3 class="text-base font-semibold text-foreground">{{ t('adjustment.adjustment_details') }}</h3>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <Calendar class="h-3 w-3" />{{ t('general.date') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ adjustmentData.date }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <Tag class="h-3 w-3" />{{ t('adjustment.reason') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ adjustmentData.reason_label || '-' }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <component :is="isOut ? ArrowDownCircle : ArrowUpCircle" class="h-3 w-3" />{{ t('adjustment.type') }}
                        </div>
                        <div class="text-sm font-medium" :class="isOut ? 'text-red-500' : 'text-green-500'">
                            {{ adjustmentData.type_label || '-' }}
                            ({{ isOut ? t('adjustment.stock_decreases') : t('adjustment.stock_increases') }})
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <Store class="h-3 w-3" />{{ t('adjustment.warehouse') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ adjustmentData.warehouse?.name || '-' }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <User class="h-3 w-3" />{{ t('general.created_by') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ adjustmentData.created_by?.name || '-' }}</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <User class="h-3 w-3" />{{ t('general.updated_by') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ adjustmentData.updated_by?.name || '-' }}</div>
                    </div>
                    <div v-if="adjustmentData.notes" class="space-y-1.5 sm:col-span-2">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <FileText class="h-3 w-3" />{{ t('adjustment.notes') }}
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ adjustmentData.notes }}</div>
                    </div>
                </div>
            </fieldset>

            <!-- Items table -->
            <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="border-b border-border bg-muted/30 px-4 py-3 flex items-center gap-2">
                    <SlidersHorizontal class="h-5 w-5 text-violet-500" />
                    <h3 class="text-base font-semibold text-foreground">{{ t('adjustment.adjustment_items') }}</h3>
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
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('general.expire_date') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('general.qty') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground rtl:text-right">{{ t('general.unit') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('adjustment.unit_cost') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('general.total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="(item, index) in adjustmentData.items" :key="item.id"
                                class="bg-background/40 transition-colors hover:bg-muted/40">
                                <td class="px-3 py-3 text-foreground">{{ index + 1 }}</td>
                                <td class="px-3 py-3 text-foreground">
                                    <div class="font-medium">{{ item.item?.name }}</div>
                                    <div class="text-xs text-muted-foreground">{{ item.item?.code }}</div>
                                </td>
                                <td class="px-3 py-3 text-foreground">{{ item.batch || '-' }}</td>
                                <td class="px-3 py-3 text-foreground"><span v-if="resolveColor(item.color)" class="flex items-center gap-1.5"><span class="h-3 w-3 shrink-0 rounded-full border border-muted-foreground/40" :style="{ backgroundColor: resolveColor(item.color).hex }" />{{ resolveColor(item.color).name }}</span><span v-else>-</span></td>
                                <td class="px-3 py-3 text-foreground">{{ item.size_name || '-' }}</td>
                                <td class="px-3 py-3 text-foreground">{{ item.expire_date || '-' }}</td>
                                <td class="px-3 py-3 text-right text-foreground">{{ item.quantity }}</td>
                                <td class="px-3 py-3 text-foreground">{{ item.unit_measure?.name || '-' }}</td>
                                <td class="px-3 py-3 text-right text-foreground">{{ Number(item.unit_cost || 0).toFixed(2) }}</td>
                                <td class="px-3 py-3 text-right font-semibold text-foreground">
                                    {{ (Number(item.quantity || 0) * Number(item.unit_cost || 0)).toFixed(2) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t border-border bg-muted/30">
                            <tr>
                                <td colspan="4" class="px-3 py-4 text-right text-sm font-semibold text-foreground">{{ t('general.total') }}:</td>
                                <td class="px-3 py-4 text-right text-sm font-semibold text-foreground">{{ totalQuantity }}</td>
                                <td class="px-3 py-4"></td>
                                <td class="px-3 py-4"></td>
                                <td class="px-3 py-4 text-right text-lg font-bold text-violet-600 dark:text-violet-400">
                                    {{ totalAmount.toFixed(2) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="mt-4">
                <AttachmentList :items="adjustmentData.attachments || []" :label="t('general.attachments')" />
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
td, th { white-space: nowrap; }
</style>
