<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'
import { useI18n } from 'vue-i18n'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog'
import { Button } from '@/Components/ui/button'
import { FileText, Calendar, User, DollarSign, Receipt as ReceiptIcon } from 'lucide-vue-next'

const { t } = useI18n()

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
            </div>

            <DialogFooter>
                <Button variant="outline" @click="closeDialog">
                    {{ t('general.close') || 'Close' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>


