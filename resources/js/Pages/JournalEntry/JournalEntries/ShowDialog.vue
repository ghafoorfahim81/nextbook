<script setup>
import { computed, ref, watch } from 'vue'
import axios from 'axios'
import { useI18n } from 'vue-i18n'
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/Components/ui/dialog'
import { Button } from '@/Components/ui/button'
import { Calendar, DollarSign, ReceiptText, FileText } from 'lucide-vue-next'

const { t } = useI18n()

const props = defineProps({
    open: Boolean,
    journalEntryId: String,
})
const emit = defineEmits(['update:open'])

const journalEntry = ref(null)
const loading = ref(false)

watch(() => [props.open, props.journalEntryId], async ([isOpen, id]) => {
    if (isOpen && id) {
        loading.value = true
        try {
            const { data } = await axios.get(`/journal-entries/${id}`)
            journalEntry.value = data?.data || null
        } finally {
            loading.value = false
        }
    }
})

const transaction = computed(() => journalEntry.value?.transaction || null)
const lines = computed(() => transaction.value?.lines || [])
const totalDebit = computed(() => lines.value.reduce((sum, l) => sum + (Number(l.debit) || 0), 0))
const totalCredit = computed(() => lines.value.reduce((sum, l) => sum + (Number(l.credit) || 0), 0))

function formatNumber(n) {
    if (n === null || n === undefined || n === '') return '-'
    const num = Number(n)
    if (Number.isNaN(num)) return String(n)
    return num.toLocaleString()
}

function closeDialog() {
    emit('update:open', false)
    journalEntry.value = null
}
</script>

<template>
    <Dialog :open="open" @update:open="closeDialog">
        <DialogContent class="max-w-4xl">
            <DialogHeader>
                <div class="flex items-center gap-3">
                    <ReceiptText class="w-6 h-6 text-violet-500" />
                    <DialogTitle class="text-xl">
                        {{ t('sidebar.journal_entry.journal_entries') }} <span v-if="journalEntry">#{{ journalEntry.number }}</span>
                    </DialogTitle>
                </div>
                <DialogDescription class="text-xs text-muted-foreground" v-if="journalEntry?.remark">
                    {{ journalEntry.remark }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="loading" class="py-6 text-center text-muted-foreground">
                {{ t('general.loading') }}...
            </div>

            <div v-else-if="journalEntry" class="space-y-6">
                <div class="rounded-lg p-4 border bg-muted/20">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <Calendar class="w-3 h-3" />
                                {{ t('general.date') }}
                            </div>
                            <div class="text-sm font-medium">{{ journalEntry.date || '-' }}</div>
                        </div>

                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <FileText class="w-3 h-3" />
                                {{ t('general.status') }}
                            </div>
                            <div class="text-sm font-medium">{{ journalEntry.status || '-' }}</div>
                        </div>

                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <DollarSign class="w-3 h-3" />
                                {{ t('general.amount') }}
                            </div>
                            <div class="text-sm font-medium">
                                {{ formatNumber(journalEntry.amount) }}
                                <span class="text-muted-foreground" v-if="transaction?.currency?.code">
                                    {{ transaction.currency.code }}
                                </span>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <DollarSign class="w-3 h-3" />
                                {{ t('general.rate') }}
                            </div>
                            <div class="text-sm font-medium">{{ formatNumber(transaction?.rate) }}</div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border overflow-hidden">
                    <div class="flex items-center justify-between p-4 border-b bg-card">
                        <div class="font-semibold text-violet-500">
                            {{ t('account.detail_lines') }}
                        </div>
                        <div class="text-sm text-muted-foreground">
                            {{ t('general.total') }}:
                            <span class="font-medium text-foreground">
                                {{ formatNumber(totalDebit) }}
                            </span>
                        </div>
                    </div>

                    <div class="overflow-auto">
                        <table class="w-full min-w-[720px]">
                            <thead class="bg-muted/40">
                                <tr class="text-sm text-muted-foreground rtl:text-right ltr:text-left">
                                    <th class="px-4 py-2 w-12">#</th>
                                    <th class="px-4 py-2">{{ t('account.account') }}</th>
                                    <th class="px-4 py-2 w-40 text-right">{{ t('general.debit') }}</th>
                                    <th class="px-4 py-2 w-40 text-right">{{ t('general.credit') }}</th>
                                    <th class="px-4 py-2">{{ t('general.ledger') }}</th>
                                    <th class="px-4 py-2">{{ t('sidebar.journal_entry.journal_class') }}</th>
                                    <th class="px-4 py-2">{{ t('general.remark') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(line, idx) in lines" :key="line.id || idx" class="border-t">
                                    <td class="px-4 py-2 text-center text-muted-foreground">{{ idx + 1 }}</td>
                                    <td class="px-4 py-2">
                                        {{ line.account?.name || line.account_id || '-' }}
                                    </td>
                                    <td class="px-4 py-2 text-right font-medium">
                                        {{ formatNumber(line.debit) }}
                                    </td>
                                    <td class="px-4 py-2 text-right font-medium">
                                        {{ formatNumber(line.credit) }}
                                    </td>
                                    <td class="px-4 py-2">
                                        {{ line.ledger?.name || line.ledger_id || '-' }}
                                    </td>
                                    <td class="px-4 py-2">
                                        {{ line.journalClass?.name || line.journal_class?.name || '-' }}
                                    </td>
                                    <td class="px-4 py-2">
                                        {{ line.remark || '-' }}
                                    </td>
                                </tr>
                                <tr class="border-t bg-muted/20 font-semibold">
                                    <td colspan="2" class="px-4 py-2 text-right">{{ t('general.total') }}</td>
                                    <td class="px-4 py-2 text-right">{{ formatNumber(totalDebit) }}</td>
                                    <td class="px-4 py-2 text-right">{{ formatNumber(totalCredit) }}</td>
                                    <td class="px-4 py-2"></td>
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


