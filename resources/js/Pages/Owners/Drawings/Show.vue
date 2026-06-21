<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { router } from '@inertiajs/vue3'
import { CalendarDays, Landmark, Wallet, User, FileText, ArrowRightLeft, Percent } from 'lucide-vue-next'
import ShowPageToolbar from '@/Components/ShowPageToolbar.vue'
import TransactionActionDialog from '@/Components/TransactionActionDialog.vue'
import { Badge } from '@/Components/ui/badge'

const { t } = useI18n()

const props = defineProps({
    drawing: { type: Object, required: true },
})

const drawing = computed(() => props.drawing?.data ?? props.drawing ?? {})
const transaction = computed(() => drawing.value?.transaction || null)
const transactionLines = computed(() => transaction.value?.lines?.data ?? transaction.value?.lines ?? [])
const creditLine = computed(() => transactionLines.value.find((l) => Number(l.credit || 0) > 0) || null)
const debitLine = computed(() => transactionLines.value.find((l) => Number(l.debit || 0) > 0) || null)

const reverseDialogOpen = ref(false)

function reverseDrawing(reason) {
    router.post(route('drawings.reverse', drawing.value.id), { reason }, {
        preserveScroll: true,
        onSuccess: () => { reverseDialogOpen.value = false },
    })
}

const formatAmount = (value) => {
    if (value === null || value === undefined || value === '') return '-'
    return Number(value).toLocaleString(undefined, { maximumFractionDigits: 2 })
}

const currencyLabel = computed(() => {
    const currency = drawing.value?.currency
    if (!currency) return '-'
    return [currency.symbol, currency.code].filter(Boolean).join(' ') || currency.code || '-'
})

const statusClass = (status) => {
    switch (status) {
        case 'posted':   return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
        case 'reversed': return 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300'
        default:         return 'border-border bg-muted text-foreground'
    }
}
const statusLabel = (status) => {
    switch (status) {
        case 'posted':   return t('general.status_posted')
        case 'reversed': return t('general.status_reversed')
        default:         return status ?? '-'
    }
}
</script>

<template>
    <AppLayout :title="`${t('sidebar.owners.drawing')} #${drawing.number}`">
        <div class="space-y-6">
            <ShowPageToolbar
                back-route="drawings.index"
                :status="drawing.status"
                @reverse="reverseDialogOpen = true"
            />

            <TransactionActionDialog
                v-model:open="reverseDialogOpen"
                type="reverse"
                :title="t('general.reverse') + ' ' + t('sidebar.owners.drawing')"
                :description="t('general.reverse_description')"
                @confirm="reverseDrawing"
            />

            <fieldset class="rounded-xl border border-border bg-card px-5 pb-5 pt-3 shadow-sm">
                <legend class="px-2 flex items-center gap-2">
                    <Wallet class="w-4 h-4 text-violet-500" />
                    <span class="text-sm font-semibold text-violet-500">
                        {{ t('sidebar.owners.drawing') }}
                        <span v-if="drawing.number"> #{{ drawing.number }}</span>
                        <span v-if="drawing.owner"> - {{ drawing.owner.name }}</span>
                    </span>
                    <Badge v-if="drawing.status" :class="statusClass(drawing.status)" variant="outline">{{ statusLabel(drawing.status) }}</Badge>
                </legend>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                        <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                            <FileText class="h-3.5 w-3.5" /> {{ t('general.number') }}
                        </div>
                        <div class="text-sm font-medium">{{ drawing.number ?? '-' }}</div>
                    </div>
                    <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                        <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                            <User class="h-3.5 w-3.5" /> {{ t('owner.owner') }}
                        </div>
                        <div class="text-sm font-medium">{{ drawing.owner?.name || '-' }}</div>
                    </div>
                    <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                        <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                            <Landmark class="h-3.5 w-3.5" /> {{ t('general.bank_account') }}
                        </div>
                        <div class="text-sm font-medium">{{ drawing.bank_account?.name || '-' }}</div>
                    </div>
                    <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                        <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                            <Wallet class="h-3.5 w-3.5" /> {{ t('owner.drawing_account') }}
                        </div>
                        <div class="text-sm font-medium">{{ drawing.drawing_account?.name || '-' }}</div>
                    </div>
                    <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                        <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                            <CalendarDays class="h-3.5 w-3.5" /> {{ t('general.date') }}
                        </div>
                        <div class="text-sm font-medium">{{ drawing.formatted_date || drawing.date || '-' }}</div>
                    </div>
                    <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                        <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                            <ArrowRightLeft class="h-3.5 w-3.5" /> {{ t('general.amount') }}
                        </div>
                        <div class="text-sm font-medium">{{ currencyLabel }} {{ formatAmount(drawing.amount) }}</div>
                    </div>
                    <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                        <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                            <Percent class="h-3.5 w-3.5" /> {{ t('general.rate') }}
                        </div>
                        <div class="text-sm font-medium">{{ drawing.rate || '-' }}</div>
                    </div>
                    <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                        <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                            <FileText class="h-3.5 w-3.5" /> {{ t('general.remarks') }}
                        </div>
                        <div class="text-sm font-medium">{{ drawing.narration || '-' }}</div>
                    </div>
                </div>
            </fieldset>

            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-violet-500">
                    <ArrowRightLeft class="h-4 w-4" /> {{ t('general.transaction') }}
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-xl border border-emerald-500/25 bg-emerald-500/10 p-4">
                        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-emerald-600 dark:text-emerald-300">CR</div>
                        <div class="text-sm font-medium">{{ drawing.bank_account?.name || '-' }}</div>
                        <div class="mt-1 text-xs text-muted-foreground">{{ currencyLabel }} {{ formatAmount(creditLine?.credit || drawing.amount) }}</div>
                    </div>
                    <div class="rounded-xl border border-rose-500/25 bg-rose-500/10 p-4">
                        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-rose-600 dark:text-rose-300">DR</div>
                        <div class="text-sm font-medium">{{ drawing.drawing_account?.name || '-' }}</div>
                        <div class="mt-1 text-xs text-muted-foreground">{{ currencyLabel }} {{ formatAmount(debitLine?.debit || drawing.amount) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
