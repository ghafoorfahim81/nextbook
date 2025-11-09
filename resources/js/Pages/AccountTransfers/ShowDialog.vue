<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'
import { useI18n } from 'vue-i18n'
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/Components/ui/dialog'
import { Button } from '@/Components/ui/button'
import { Calendar, DollarSign, FileText, ArrowLeftRight, Banknote } from 'lucide-vue-next'

const { t } = useI18n()

const props = defineProps({
    open: Boolean,
    transferId: String,
})
const emit = defineEmits(['update:open'])

const transfer = ref(null)
const loading = ref(false)

watch(() => props.open, async (isOpen) => {
    if (isOpen && props.transferId) {
        loading.value = true
        try {
            const { data } = await axios.get(`/account-transfers/${props.transferId}`)
            transfer.value = data?.data || null
        } finally {
            loading.value = false
        }
    }
})

function closeDialog() {
    emit('update:open', false)
    transfer.value = null
}
</script>

<template>
    <Dialog :open="open" @update:open="closeDialog">
        <DialogContent class="max-w-3xl">
            <DialogHeader>
                <div class="flex items-center gap-3">
                    <ArrowLeftRight class="w-6 h-6 text-violet-500" />
                    <DialogTitle class="text-xl">
                        {{ t('general.account_transfer') }} <span v-if="transfer">#{{ transfer.number }}</span>
                    </DialogTitle>
                </div>
                <DialogDescription class="text-xs text-muted-foreground" v-if="transfer?.remark">
                    {{ transfer.remark }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="loading" class="py-6 text-center text-muted-foreground">
                {{ t('general.loading') }}...
            </div>

            <div v-else-if="transfer" class="space-y-6">
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-gray-500">
                                <Calendar class="w-3 h-3" />
                                {{ t('general.date') }}
                            </div>
                            <div class="text-sm font-medium text-gray-900">{{ transfer.date }}</div>
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-gray-500">
                                <Banknote class="w-3 h-3" />
                                {{ t('general.amount') }}
                            </div>
                            <div class="text-sm font-medium text-gray-900">{{ transfer.amount }} {{ transfer.currency_code }}</div>
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-gray-500">
                                <FileText class="w-3 h-3" />
                                {{ t('general.number') }}
                            </div>
                            <div class="text-sm font-medium text-gray-900">{{ transfer.number }}</div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="text-sm font-semibold mb-3 text-violet-500">{{ t('general.from_account') }} (Credit)</div>
                        <div v-if="transfer.from_transaction" class="grid grid-cols-2 gap-2 text-sm">
                            <div class="text-muted-foreground">{{ t('general.account') }}</div>
                            <div class="font-medium">
                                {{ transfer.from_transaction.account?.name || '-' }}
                            </div>
                            <div class="text-muted-foreground">{{ t('general.amount') }}</div>
                            <div class="font-medium">
                                {{ transfer.from_transaction.currency?.symbol || '' }} {{ transfer.from_transaction.amount }}
                            </div>
                        </div>
                        <div v-else class="text-sm text-muted-foreground">-</div>
                    </div>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="text-sm font-semibold mb-3 text-violet-500">{{ t('general.to_account') }} (Debit)</div>
                        <div v-if="transfer.to_transaction" class="grid grid-cols-2 gap-2 text-sm">
                            <div class="text-muted-foreground">{{ t('general.account') }}</div>
                            <div class="font-medium">
                                {{ transfer.to_transaction.account?.name || '-' }}
                            </div>
                            <div class="text-muted-foreground">{{ t('general.amount') }}</div>
                            <div class="font-medium">
                                {{ transfer.to_transaction.currency?.symbol || '' }} {{ transfer.to_transaction.amount }}
                            </div>
                        </div>
                        <div v-else class="text-sm text-muted-foreground">-</div>
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



