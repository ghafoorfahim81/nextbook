<script setup>
import { computed } from 'vue'
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
import { CalendarDays, Landmark, Wallet, User, FileText, ArrowRightLeft, Percent } from 'lucide-vue-next'

const { t } = useI18n()

const props = defineProps({
  open: Boolean,
  drawing: Object,
})

const emit = defineEmits(['update:open'])

const transaction = computed(() => props.drawing?.transaction || null)
const transactionLines = computed(() => transaction.value?.lines?.data ?? transaction.value?.lines ?? [])

const creditLine = computed(() => transactionLines.value.find((line) => Number(line.credit || 0) > 0) || null)
const debitLine = computed(() => transactionLines.value.find((line) => Number(line.debit || 0) > 0) || null)

const formatAmount = (value) => {
  if (value === null || value === undefined || value === '') return '-'
  return Number(value).toLocaleString(undefined, { maximumFractionDigits: 2 })
}

const currencyLabel = computed(() => {
  const currency = props.drawing?.currency
  if (!currency) return '-'
  return [currency.symbol, currency.code].filter(Boolean).join(' ') || currency.code || '-'
})
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="max-w-5xl p-0 overflow-hidden border-border bg-background text-foreground">
      <div class="flex max-h-[90vh] flex-col">
        <DialogHeader class="border-b border-border bg-gradient-to-r from-violet-500/10 via-background to-background px-6 py-4">
          <div class="flex items-center gap-3">
            <div class="rounded-lg bg-violet-500/15 p-2 text-violet-500">
              <Wallet class="h-5 w-5" />
            </div>
            <div>
              <DialogTitle class="text-lg font-semibold text-foreground">
                {{ t('sidebar.owners.drawing') || 'Drawing' }}
                <span v-if="drawing"> - {{ drawing.owner?.name || '-' }}</span>
              </DialogTitle>
              <DialogDescription class="text-muted-foreground">
                {{ drawing?.formatted_date || drawing?.date || t('general.details') || 'Details' }}
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>

        <div v-if="drawing" class="flex-1 space-y-4 overflow-y-auto px-6 py-5">
          <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                <User class="h-3.5 w-3.5" />
                {{ t('owner.owner') || 'Owner' }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ drawing.owner?.name || '-' }}</div>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                <Landmark class="h-3.5 w-3.5" />
                {{ t('general.bank_account') || 'Bank Account' }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ drawing.bank_account?.name || '-' }}</div>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                <Wallet class="h-3.5 w-3.5" />
                {{ t('owner.drawing_account') || 'Drawing Account' }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ drawing.drawing_account?.name || '-' }}</div>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                <CalendarDays class="h-3.5 w-3.5" />
                {{ t('general.date') || 'Date' }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ drawing.formatted_date || drawing.date || '-' }}</div>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                <ArrowRightLeft class="h-3.5 w-3.5" />
                {{ t('general.amount') || 'Amount' }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ currencyLabel }} {{ formatAmount(drawing.amount) }}</div>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                <FileText class="h-3.5 w-3.5" />
                {{ t('general.remarks') || 'Remarks' }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ drawing.narration || '-' }}</div>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                <Percent class="h-3.5 w-3.5" />
                {{ t('general.rate') || 'Rate' }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ drawing.rate || '-' }}</div>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                <Landmark class="h-3.5 w-3.5" />
                {{ t('admin.currency.currency') || 'Currency' }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ drawing.currency?.code || '-' }}</div>
            </div>
          </div>

          <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
            <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-violet-500">
              <ArrowRightLeft class="h-4 w-4" />
              {{ t('general.transaction') || 'Transaction' }}
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="rounded-xl border border-emerald-500/25 bg-emerald-500/10 p-4">
                <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-emerald-600 dark:text-emerald-300">
                  CR
                </div>
                <div class="text-sm font-medium text-foreground">{{ drawing.bank_account?.name || '-' }}</div>
                <div class="mt-1 text-xs text-muted-foreground">
                  {{ currencyLabel }} {{ formatAmount(creditLine?.credit || drawing.amount) }}
                </div>
              </div>
              <div class="rounded-xl border border-rose-500/25 bg-rose-500/10 p-4">
                <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-rose-600 dark:text-rose-300">
                  DR
                </div>
                <div class="text-sm font-medium text-foreground">{{ drawing.drawing_account?.name || '-' }}</div>
                <div class="mt-1 text-xs text-muted-foreground">
                  {{ currencyLabel }} {{ formatAmount(debitLine?.debit || drawing.amount) }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="px-6 py-10 text-center text-muted-foreground">
          {{ t('general.loading') || 'Loading' }}...
        </div>

        <DialogFooter class="border-t border-border bg-background px-6 py-4">
          <Button variant="outline" @click="emit('update:open', false)">
            {{ t('general.close') || 'Close' }}
          </Button>
        </DialogFooter>
      </div>
    </DialogContent>
  </Dialog>
</template>
