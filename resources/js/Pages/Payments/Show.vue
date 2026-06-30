<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import AttachmentList from '@/Components/AttachmentList.vue'
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { router } from '@inertiajs/vue3'
import { Calendar, User, DollarSign, FileText, Receipt as ReceiptIcon } from 'lucide-vue-next'
import TransactionActionDialog from '@/Components/TransactionActionDialog.vue'
import ShowPageToolbar from '@/Components/ShowPageToolbar.vue'
import { Badge } from '@/Components/ui/badge'

const { t } = useI18n()

const props = defineProps({
    payment: { type: Object, required: true },
})

const payment = computed(() => props.payment?.data ?? props.payment ?? {})

const postDialogOpen = ref(false)
const reverseDialogOpen = ref(false)

function postPayment() {
    router.post(route('payments.post', payment.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => { postDialogOpen.value = false },
    })
}

function reversePayment(reason) {
    router.post(route('payments.reverse', payment.value.id), { reason }, {
        preserveScroll: true,
        onSuccess: () => { reverseDialogOpen.value = false },
    })
}

const statusClass = (status) => {
    switch (status) {
        case 'draft':    return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300'
        case 'posted':   return 'border-green-500/30 bg-green-500/10 text-green-700 dark:text-green-300'
        case 'reversed': return 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300'
        default:         return 'border-border bg-muted text-foreground'
    }
}
const statusLabel = (status) => {
    switch (status) {
        case 'draft':    return t('general.status_draft')
        case 'posted':   return t('general.status_posted')
        case 'reversed': return t('general.status_reversed')
        default:         return status ?? ''
    }
}
</script>

<template>
    <AppLayout :title="`${t('payment.payment')} #${payment.number}`">
        <div class="space-y-6">
            <ShowPageToolbar
                back-route="payments.index"
                :status="payment.status"
                :edit-route="payment.status === 'draft' ? route('payments.edit', payment.id) : null"
                edit-permission="payments.update"
                :print-url="route('payments.print', payment.id)"
                @post="postDialogOpen = true"
                @reverse="reverseDialogOpen = true"
            />

            <TransactionActionDialog
                v-model:open="postDialogOpen"
                type="post"
                :title="t('general.post') + ' ' + t('payment.payment')"
                :description="t('general.post_document_desc')"
                @confirm="postPayment"
            />
            <TransactionActionDialog
                v-model:open="reverseDialogOpen"
                type="reverse"
                :title="t('general.reverse') + ' ' + t('payment.payment')"
                :description="t('general.reverse_description')"
                @confirm="reversePayment"
            />

            <fieldset class="rounded-xl border border-border bg-card px-5 pb-5 pt-3 shadow-sm">
                <legend class="px-2 flex items-center gap-2">
                    <ReceiptIcon class="w-4 h-4 text-violet-500" />
                    <span class="text-sm font-semibold text-violet-500">{{ t('payment.payment') }} #{{ payment.number }}</span>
                    <Badge v-if="payment.status" :class="statusClass(payment.status)" variant="outline">{{ statusLabel(payment.status) }}</Badge>
                </legend>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <Calendar class="w-3 h-3" /> {{ t('general.date') }}
                        </div>
                        <div class="text-sm font-medium">{{ payment.date }}</div>
                    </div>
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <User class="w-3 h-3" /> {{ t('ledger.supplier.supplier') }}
                        </div>
                        <div class="text-sm font-medium">{{ payment.ledger?.name || payment.ledger_name || '-' }}</div>
                    </div>
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <DollarSign class="w-3 h-3" /> {{ t('general.amount') }}
                        </div>
                        <div class="text-sm font-medium">{{ payment.amount }} {{ payment.currency_code }}</div>
                    </div>
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <DollarSign class="w-3 h-3" /> {{ t('general.rate') }}
                        </div>
                        <div class="text-sm font-medium">{{ payment.rate }}</div>
                    </div>
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <FileText class="w-3 h-3" /> {{ t('general.payment_mode') }}
                        </div>
                        <div class="text-sm font-medium">{{ payment.payment_mode_label || payment.payment_mode || '-' }}</div>
                    </div>
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <User class="w-3 h-3" /> {{ t('general.created_by') }}
                        </div>
                        <div class="text-sm font-medium">{{ payment.created_by?.name || '-' }}</div>
                    </div>
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <User class="w-3 h-3" /> {{ t('general.updated_by') }}
                        </div>
                        <div class="text-sm font-medium">{{ payment.updated_by?.name || '-' }}</div>
                    </div>
                </div>
            </fieldset>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="border border-border rounded-xl p-4 bg-card">
                    <div class="text-sm font-semibold mb-3 text-violet-500">{{ t('general.debit') }} ({{ t('payment.payment') }})</div>
                    <div v-if="payment.transaction" class="grid grid-cols-2 gap-2 text-sm">
                        <div class="text-muted-foreground">{{ t('general.amount') }}</div>
                        <div class="font-medium">{{ payment.transaction.currency?.symbol || '' }} {{ payment.transaction.lines?.[1]?.debit || payment.amount || 0 }}</div>
                        <div class="text-muted-foreground">{{ t('admin.currency.currency') }}</div>
                        <div class="font-medium">{{ payment.transaction.currency?.code || '-' }}</div>
                    </div>
                    <div v-else class="text-sm text-muted-foreground">-</div>
                </div>
                <div class="border border-border rounded-xl p-4 bg-card">
                    <div class="text-sm font-semibold mb-3 text-violet-500">{{ t('general.credit') }} ({{ t('general.bank') }})</div>
                    <div v-if="payment.transaction" class="grid grid-cols-2 gap-2 text-sm">
                        <div class="text-muted-foreground">{{ t('general.amount') }}</div>
                        <div class="font-medium">{{ payment.transaction.currency?.symbol || '' }} {{ payment.transaction.lines?.[0]?.credit || payment.amount || 0 }}</div>
                        <div class="text-muted-foreground">{{ t('admin.currency.currency') }}</div>
                        <div class="font-medium">{{ payment.transaction.currency?.code || '-' }}</div>
                    </div>
                    <div v-else class="text-sm text-muted-foreground">-</div>
                </div>
            </div>

            <div v-if="payment.purchase_payments?.length" class="border border-border rounded-xl p-4 bg-card">
                <div class="text-sm font-semibold mb-3 text-violet-500">{{ t('general.bill_allocations') }}</div>
                <table class="min-w-full text-sm">
                    <thead class="bg-muted/40">
                        <tr>
                            <th class="px-3 py-2 ltr:text-left rtl:text-right">{{ t('general.bill') }}</th>
                            <th class="px-3 py-2 text-right">{{ t('general.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="allocation in payment.purchase_payments" :key="allocation.id" class="border-t">
                            <td class="px-3 py-2">#{{ allocation.purchase?.number || allocation.purchase_id }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ allocation.amount }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4">
            <AttachmentList :items="payment.attachments || []" :label="t('general.attachments')" />
        </div>
    </AppLayout>
</template>
