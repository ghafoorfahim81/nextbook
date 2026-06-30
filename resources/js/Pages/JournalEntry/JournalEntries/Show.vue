<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import AttachmentList from '@/Components/AttachmentList.vue'
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { router } from '@inertiajs/vue3'
import { Calendar, DollarSign, ReceiptText } from 'lucide-vue-next'
import TransactionActionDialog from '@/Components/TransactionActionDialog.vue'
import ShowPageToolbar from '@/Components/ShowPageToolbar.vue'
import { Badge } from '@/Components/ui/badge'

const { t } = useI18n()

const props = defineProps({
    journalEntry: { type: Object, required: true },
})

const je = computed(() => props.journalEntry?.data ?? props.journalEntry ?? {})
const transaction = computed(() => je.value?.transaction || null)
const lines = computed(() => {
    const txnLines = transaction.value?.lines || []
    if (txnLines.length) return txnLines
    return je.value?.lines || []
})
const totalDebit = computed(() => lines.value.reduce((sum, l) => sum + (Number(l.debit) || 0), 0))
const totalCredit = computed(() => lines.value.reduce((sum, l) => sum + (Number(l.credit) || 0), 0))

const postDialogOpen = ref(false)
const reverseDialogOpen = ref(false)

function postEntry() {
    router.post(route('journal-entries.post', je.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => { postDialogOpen.value = false },
    })
}

function reverseEntry(reason) {
    router.post(route('journal-entries.reverse', je.value.id), { reason }, {
        preserveScroll: true,
        onSuccess: () => { reverseDialogOpen.value = false },
    })
}

function formatNumber(n) {
    if (n === null || n === undefined || n === '') return '-'
    const num = Number(n)
    return Number.isNaN(num) ? String(n) : num.toLocaleString()
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
    <AppLayout :title="`${t('sidebar.journal_entry.journal_entries')} #${je.number}`">
        <div class="space-y-6">
            <ShowPageToolbar
                back-route="journal-entries.index"
                :status="je.status"
                :edit-route="je.status === 'draft' ? route('journal-entries.edit', je.id) : null"
                edit-permission="journal_entries.update"
                @post="postDialogOpen = true"
                @reverse="reverseDialogOpen = true"
            />

            <TransactionActionDialog
                v-model:open="postDialogOpen"
                type="post"
                :title="t('general.post') + ' ' + t('sidebar.journal_entry.journal_entries')"
                :description="t('general.post_document_desc')"
                @confirm="postEntry"
            />
            <TransactionActionDialog
                v-model:open="reverseDialogOpen"
                type="reverse"
                :title="t('general.reverse') + ' ' + t('sidebar.journal_entry.journal_entries')"
                :description="t('general.reverse_description')"
                @confirm="reverseEntry"
            />

            <fieldset class="rounded-xl border border-border bg-card px-5 pb-5 pt-3 shadow-sm">
                <legend class="px-2 flex items-center gap-2">
                    <ReceiptText class="w-4 h-4 text-violet-500" />
                    <span class="text-sm font-semibold text-violet-500">{{ t('sidebar.journal_entry.journal_entries') }} #{{ je.number }}</span>
                    <Badge v-if="je.status" :class="statusClass(je.status)" variant="outline">{{ statusLabel(je.status) }}</Badge>
                </legend>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <Calendar class="w-3 h-3" /> {{ t('general.date') }}
                        </div>
                        <div class="text-sm font-medium">{{ je.date || '-' }}</div>
                    </div>
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <DollarSign class="w-3 h-3" /> {{ t('general.amount') }}
                        </div>
                        <div class="text-sm font-medium">
                            {{ formatNumber(je.amount) }}
                            <span class="text-muted-foreground" v-if="transaction?.currency?.code">{{ transaction.currency.code }}</span>
                        </div>
                    </div>
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <DollarSign class="w-3 h-3" /> {{ t('general.rate') }}
                        </div>
                        <div class="text-sm font-medium">{{ formatNumber(transaction?.rate) }}</div>
                    </div>
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <ReceiptText class="w-3 h-3" /> {{ t('general.remark') }}
                        </div>
                        <div class="text-sm font-medium">{{ je.remark || '-' }}</div>
                    </div>
                </div>
            </fieldset>

            <div class="rounded-xl border border-border overflow-hidden">
                <div class="flex items-center justify-between p-4 border-b bg-card">
                    <div class="font-semibold text-violet-500">{{ t('account.detail_lines') }}</div>
                    <div class="text-sm text-muted-foreground">
                        {{ t('general.total') }}: <span class="font-medium text-foreground">{{ formatNumber(totalDebit) }}</span>
                    </div>
                </div>
                <div class="overflow-auto">
                    <table class="w-full min-w-[720px]">
                        <thead class="bg-muted/40">
                            <tr class="text-sm text-muted-foreground ltr:text-left rtl:text-right">
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
                                <td class="px-4 py-2">{{ line.account?.name || '-' }}</td>
                                <td class="px-4 py-2 text-right font-medium">{{ formatNumber(line.debit) }}</td>
                                <td class="px-4 py-2 text-right font-medium">{{ formatNumber(line.credit) }}</td>
                                <td class="px-4 py-2">{{ line.ledger?.name || '-' }}</td>
                                <td class="px-4 py-2">{{ line.journalClass?.name || line.journal_class?.name || '-' }}</td>
                                <td class="px-4 py-2">{{ line.remark || '-' }}</td>
                            </tr>
                            <tr class="border-t bg-muted/20 font-semibold">
                                <td colspan="2" class="px-4 py-2 text-right">{{ t('general.total') }}</td>
                                <td class="px-4 py-2 text-right">{{ formatNumber(totalDebit) }}</td>
                                <td class="px-4 py-2 text-right">{{ formatNumber(totalCredit) }}</td>
                                <td colspan="3" class="px-4 py-2"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="mt-4">
            <AttachmentList :items="je.attachments || []" :label="t('general.attachments')" />
        </div>
    </AppLayout>
</template>
