<script setup>
import { ref, watch, computed } from 'vue'
import axios from 'axios'
import { useI18n } from 'vue-i18n'
import { useForm } from '@inertiajs/vue3'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog'
import { Button } from '@/Components/ui/button'
import { Badge } from '@/Components/ui/badge'
import { FileText, Calendar, User, DollarSign, Receipt as ReceiptIcon, Send, RotateCcw } from 'lucide-vue-next'
import { useAuth } from '@/composables/useAuth'

const { t } = useI18n()
const { can } = useAuth()

const props = defineProps({
    open: Boolean,
    receiptId: String,
})
const emit = defineEmits(['update:open'])

const receipt = ref(null)
const loading = ref(false)

watch(() => props.open, async (isOpen) => {
    if (isOpen && props.receiptId) {
        loading.value = true
        showReverseForm.value = false
        reverseReason.value = ''
        try {
            const { data } = await axios.get(`/receipts/${props.receiptId}`)
            receipt.value = data?.data || null
        } finally {
            loading.value = false
        }
    }
})

function closeDialog() {
    emit('update:open', false)
    receipt.value = null
    showReverseForm.value = false
    reverseReason.value = ''
}

const statusBadgeClasses = computed(() => {
    switch (receipt.value?.status) {
        case 'posted':   return 'border-green-500/30 bg-green-500/10 text-green-700 dark:text-green-300';
        case 'reversed': return 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300';
        case 'draft':    return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300';
        default:         return 'border-border bg-muted text-foreground';
    }
})

const postForm = useForm({})
const postReceipt = () => {
    if (!confirm(t('general.confirm_post'))) return
    postForm.post(route('receipts.post', receipt.value.id))
}

const showReverseForm = ref(false)
const reverseReason = ref('')
const reverseForm = useForm({ reason: '' })
const reverseReceipt = () => {
    reverseForm.reason = reverseReason.value
    reverseForm.post(route('receipts.reverse', receipt.value.id), {
        onSuccess: () => closeDialog(),
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
                        {{ t('receipt.receipt') }} <span v-if="receipt">#{{ receipt.number }}</span>
                    </DialogTitle>
                    <Badge v-if="receipt" :class="statusBadgeClasses">{{ receipt.status }}</Badge>
                </div>
                <DialogDescription class="text-xs text-muted-foreground" v-if="receipt?.narration">
                    {{ receipt.narration }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="loading" class="py-6 text-center text-muted-foreground">
                {{ t('general.loading') || 'Loading' }}...
            </div>

            <div v-else-if="receipt" class="space-y-6">
                <!-- Receipt details -->
                <div class="bg-card rounded-lg p-4 border border-border">
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <Calendar class="w-3 h-3" />
                                {{ t('general.date') }}
                            </div>
                            <div class="text-sm font-medium text-foreground">{{ receipt.date }}</div>
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <User class="w-3 h-3" />
                                {{ t('ledger.customer.customer') || 'Ledger' }}
                            </div>
                            <div class="text-sm font-medium text-foreground">{{ receipt.ledger?.name || receipt.ledger_name || '-' }}</div>
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <DollarSign class="w-3 h-3" />
                                {{ t('general.amount') }}
                            </div>
                            <div class="text-sm font-medium text-foreground">{{ receipt.amount }} {{ receipt.currency_code }}</div>
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <FileText class="w-3 h-3" />
                                {{ t('general.number') }}
                            </div>
                            <div class="text-sm font-medium text-foreground">{{ receipt.number }}</div>
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <FileText class="w-3 h-3" />
                                {{ t('general.payment_mode') }}
                            </div>
                            <div class="text-sm font-medium text-foreground">{{ receipt.payment_mode_label || receipt.payment_mode || '-' }}</div>
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <FileText class="w-3 h-3" />
                                {{ t('general.rate') }}
                            </div>
                            <div class="text-sm font-medium text-foreground">{{ receipt.rate }}</div>
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <FileText class="w-3 h-3" />
                                {{ t('receipt.cheque_no') }}
                            </div>
                            <div class="text-sm font-medium text-foreground">{{ receipt.cheque_no || '-' }}</div>
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <User class="w-3 h-3" />
                                {{ t('general.created_by') }}
                            </div>
                            <div class="text-sm font-medium text-foreground">{{ receipt.created_by?.name || '-' }}</div>
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <User class="w-3 h-3" />
                                {{ t('general.updated_by') }}
                            </div>
                            <div class="text-sm font-medium text-foreground">{{ receipt.updated_by?.name || '-' }}</div>
                        </div>
                    </div>
                </div>

                <!-- Transactions -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="border border-border rounded-lg p-4 bg-card">
                        <div class="text-sm font-semibold mb-3 text-violet-500">{{ t('receipt.receive_credit') || 'Credit (Receive)' }}</div>
                        <div v-if="receipt.transaction" class="grid grid-cols-2 gap-2 text-sm">
                            <div class="text-muted-foreground">{{ t('general.amount') }}</div>
                            <div class="font-medium">
                                {{ receipt.transaction.currency?.symbol || '' }} {{ receipt.transaction.lines[0].debit }}
                            </div>
                            <div class="text-muted-foreground">{{ t('admin.currency.currency') }}</div>
                            <div class="font-medium">
                                {{ receipt.transaction.currency?.code || '-' }}
                            </div>

                        </div>
                        <div v-else class="text-sm text-muted-foreground">-</div>
                    </div>
                    <div class="border border-border rounded-lg p-4 bg-card">
                        <div class="text-sm font-semibold mb-3 text-violet-500">{{ t('receipt.bank_debit') || 'Debit (Bank)' }}</div>
                        <div v-if="receipt.transaction" class="grid grid-cols-2 gap-2 text-sm">
                            <div class="text-muted-foreground">{{ t('general.amount') }}</div>
                            <div class="font-medium">
                                {{ receipt.transaction.currency?.symbol || '' }} {{ receipt.transaction.lines[0].debit }}
                            </div>
                            <div class="text-muted-foreground">{{ t('admin.currency.currency') }}</div>
                            <div class="font-medium">
                                {{ receipt.transaction.currency?.code || '-' }}
                            </div>

                        </div>
                        <div v-else class="text-sm text-muted-foreground">-</div>
                    </div>
                </div>

                <div v-if="receipt.sale_receives?.length" class="border border-border rounded-lg p-4 bg-card">
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
                                <tr v-for="allocation in receipt.sale_receives" :key="allocation.id" class="border-t">
                                    <td class="px-3 py-2">
                                        #{{ allocation.sale?.number || allocation.sale_id }}
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ allocation.amount }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <DialogFooter class="flex-col gap-2 sm:flex-row">
                <!-- Reverse reason form (shown inline when reversing) -->
                <div v-if="showReverseForm" class="w-full space-y-2">
                    <textarea
                        v-model="reverseReason"
                        rows="2"
                        :placeholder="t('general.reverse_reason_placeholder')"
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                    />
                    <div class="flex justify-end gap-2">
                        <Button variant="outline" size="sm" @click="showReverseForm = false; reverseReason = ''">
                            {{ t('general.cancel') }}
                        </Button>
                        <Button variant="destructive" size="sm" :disabled="reverseForm.processing" @click="reverseReceipt">
                            <RotateCcw class="h-4 w-4 ltr:mr-1 rtl:ml-1" />
                            {{ t('general.reverse') }}
                        </Button>
                    </div>
                </div>
                <template v-else>
                    <Button
                        v-if="receipt && can('receipts.update') && receipt.status === 'draft'"
                        size="sm"
                        class="gap-1.5 bg-green-600 text-white hover:bg-green-700"
                        :disabled="postForm.processing"
                        @click="postReceipt"
                    >
                        <Send class="h-4 w-4" />
                        {{ t('general.post') }}
                    </Button>
                    <Button
                        v-if="receipt && can('receipts.update') && receipt.status === 'posted'"
                        variant="destructive"
                        size="sm"
                        class="gap-1.5"
                        @click="showReverseForm = true"
                    >
                        <RotateCcw class="h-4 w-4" />
                        {{ t('general.reverse') }}
                    </Button>
                    <Button variant="outline" @click="closeDialog">
                        {{ t('general.close') }}
                    </Button>
                </template>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

