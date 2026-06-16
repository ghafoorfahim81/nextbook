<script setup>
import { ref, watch, computed } from 'vue'
import axios from 'axios'
import { useI18n } from 'vue-i18n'
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/Components/ui/dialog'
import { Button } from '@/Components/ui/button'
import { Calendar, User, DollarSign, Receipt as ReceiptIcon, FileText } from 'lucide-vue-next'
import { router } from '@inertiajs/vue3'
import TransactionActionDialog from '@/Components/TransactionActionDialog.vue'

const { t } = useI18n()

const props = defineProps({
    open: Boolean,
    paymentId: String,
})
const emit = defineEmits(['update:open'])

const payment = ref(null)
const loading = ref(false)
const postDialogOpen = ref(false)
const reverseDialogOpen = ref(false)

watch(() => props.open, async (isOpen) => {
    if (isOpen && props.paymentId) {
        loading.value = true
        try {
            const { data } = await axios.get(`/payments/${props.paymentId}`)
            payment.value = data?.data || null
        } finally {
            loading.value = false
        }
    }
})

const statusClass = computed(() => {
    switch (payment.value?.status) {
        case 'draft':    return 'bg-amber-100 text-amber-800 border border-amber-300 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-700'
        case 'posted':   return 'bg-green-100 text-green-800 border border-green-300 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700'
        case 'reversed': return 'bg-red-100 text-red-800 border border-red-300 dark:bg-red-900/30 dark:text-red-300 dark:border-red-700'
        default:         return 'bg-muted text-muted-foreground border border-border'
    }
})

const statusLabel = computed(() => {
    switch (payment.value?.status) {
        case 'draft':    return t('general.status_draft')
        case 'posted':   return t('general.status_posted')
        case 'reversed': return t('general.status_reversed')
        default:         return payment.value?.status ?? ''
    }
})

function closeDialog() {
    emit('update:open', false)
    payment.value = null
}

function postPayment() {
    if (!props.paymentId) return
    router.post(route('payments.post', props.paymentId), {}, {
        preserveScroll: true,
        onSuccess: () => {
            postDialogOpen.value = false
            closeDialog()
        },
    })
}

function reversePayment(reason) {
    if (!props.paymentId) return
    router.post(route('payments.reverse', props.paymentId), { reason }, {
        preserveScroll: true,
        onSuccess: () => {
            reverseDialogOpen.value = false
            closeDialog()
        },
    })
}
</script>

<template>
    <Dialog :open="open" @update:open="closeDialog">
        <DialogContent class="max-w-3xl">
            <DialogHeader>
                <div class="flex items-center gap-3">
                    <ReceiptIcon class="w-6 h-6 text-violet-500" />
                    <DialogTitle class="text-xl">
                        {{ t('payment.payment') }} <span v-if="payment">#{{ payment.number }}</span>
                    </DialogTitle>
                </div>
                <DialogDescription class="text-xs text-muted-foreground" v-if="payment?.narration">
                    {{ payment.narration }}
                </DialogDescription>
                <div v-if="payment" class="flex items-center gap-2 pt-2">
                    <span :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', statusClass]">{{ statusLabel }}</span>
                    <Button v-if="payment.status === 'draft'" size="sm" class="bg-green-600 text-white hover:bg-green-700" @click="postDialogOpen = true">
                        {{ t('general.post') }}
                    </Button>
                    <Button v-if="payment.status === 'posted'" size="sm" variant="destructive" @click="reverseDialogOpen = true">
                        {{ t('general.reverse') }}
                    </Button>
                </div>
            </DialogHeader>

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

            <div v-if="loading" class="py-6 text-center text-muted-foreground">
                {{ t('general.loading') }}...
            </div>

            <div v-else-if="payment" class="space-y-6">
                <div class="bg-card rounded-lg p-4 border border-border">
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <Calendar class="w-3 h-3" />
                                {{ t('general.date') }}
                            </div>
                            <div class="text-sm font-medium text-foreground">{{ payment.date }}</div>
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <User class="w-3 h-3" />
                                {{ t('ledger.supplier.supplier') }}
                            </div>
                            <div class="text-sm font-medium text-foreground">{{ payment.ledger?.name || payment.ledger_name || '-' }}</div>
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <DollarSign class="w-3 h-3" />
                                {{ t('general.amount') }}
                            </div>
                            <div class="text-sm font-medium text-foreground">{{ payment.amount }} {{ payment.currency_code }}</div>
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <DollarSign class="w-3 h-3" />
                                {{ t('general.rate') }}
                            </div>
                            <div class="text-sm font-medium text-foreground">{{ payment.rate }}</div>
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <FileText class="w-3 h-3" />
                                {{ t('general.number') }}
                            </div>
                            <div class="text-sm font-medium text-foreground">{{ payment.number }}</div>
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <FileText class="w-3 h-3" />
                                {{ t('general.payment_mode') }}
                            </div>
                            <div class="text-sm font-medium text-foreground">{{ payment.payment_mode_label || payment.payment_mode || '-' }}</div>
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <User class="w-3 h-3" />
                                {{ t('general.created_by') }}
                            </div>
                            <div class="text-sm font-medium text-foreground">{{ payment.created_by?.name || '-' }}</div>
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <User class="w-3 h-3" />
                                {{ t('general.updated_by') }}
                            </div>
                            <div class="text-sm font-medium text-foreground">{{ payment.updated_by?.name || '-' }}</div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="border border-border rounded-lg p-4 bg-card">
                        <div class="text-sm font-semibold mb-3 text-violet-500">{{ t('general.debit') }} ({{ t('payment.payment') }})</div>
                        <div v-if="payment.transaction" class="grid grid-cols-2 gap-2 text-sm">
                            <div class="text-muted-foreground">{{ t('general.amount') }}</div>
                            <div class="font-medium">{{ payment.transaction.currency?.symbol || '' }} {{ payment.transaction.lines?.[1]?.debit || payment.amount || 0 }}</div>
                            <div class="text-muted-foreground">{{ t('admin.currency.currency') }}</div>
                            <div class="font-medium">{{ payment.transaction.currency?.code || '-' }}</div>
                        </div>
                        <div v-else class="text-sm text-muted-foreground">-</div>
                    </div>
                    <div class="border border-border rounded-lg p-4 bg-card">
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

                <div v-if="payment.purchase_payments?.length" class="border border-border rounded-lg p-4 bg-card">
                    <div class="text-sm font-semibold mb-3 text-violet-500">{{ t('general.bill_allocations') || 'Bill allocations' }}</div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-muted/40">
                                <tr>
                                    <th class="px-3 py-2 text-left">{{ t('general.bill') || 'Bill' }}</th>
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
            </div>

            <DialogFooter>
                <Button variant="outline" @click="closeDialog">
                    {{ t('general.close') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
