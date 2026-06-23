<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { router } from '@inertiajs/vue3'
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
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import TransactionActionDialog from '@/Components/TransactionActionDialog.vue'
import { CalendarDays, Landmark, Wallet, User, FileText, ArrowRightLeft, Percent, RotateCcw } from 'lucide-vue-next'

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
const transactionStatus = computed(() => props.drawing?.status || transaction.value?.status || null)

const postDialogOpen = ref(false)
const reverseDialogOpen = ref(false)
const reverseReason = ref('')
const reversing = ref(false)
const posting = ref(false)

function postDrawing() {
  if (!props.drawing?.id || posting.value) return
  posting.value = true
  router.post(route('drawings.post', props.drawing.id), {}, {
    preserveScroll: true,
    onSuccess: () => {
      postDialogOpen.value = false
      emit('update:open', false)
    },
    onFinish: () => {
      posting.value = false
    },
  })
}

function openReverse() {
  reverseReason.value = ''
  reverseOpen.value = true
}

function closeReverse() {
  reverseDialogOpen.value = false
  reverseReason.value = ''
}

function confirmReverse() {
  if (!props.drawing?.id || !reverseReason.value.trim() || reversing.value) return
  reversing.value = true
  router.post(
    route('drawings.reverse', props.drawing.id),
    { reason: reverseReason.value.trim() },
    {
      preserveScroll: true,
      onSuccess: () => {
        closeReverse()
        emit('update:open', false)
      },
      onFinish: () => {
        reversing.value = false
      },
    },
  )
}

// ── Helpers ──────────────────────────────────────────────────────
const formatAmount = (value) => {
  if (value === null || value === undefined || value === '') return '-'
  return Number(value).toLocaleString(undefined, { maximumFractionDigits: 2 })
}

const currencyLabel = computed(() => {
  const currency = props.drawing?.currency
  if (!currency) return '-'
  return [currency.symbol, currency.code].filter(Boolean).join(' ') || currency.code || '-'
})

const statusBadgeClass = computed(() => {
  switch (transactionStatus.value) {
    case 'draft':    return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300'
    case 'posted':   return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
    case 'reversed': return 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300'
    default:         return 'border-border bg-muted text-foreground'
  }
})

const statusLabel = computed(() => {
  switch (transactionStatus.value) {
    case 'draft':    return t('general.status_draft')
    case 'posted':   return t('general.status_posted')
    case 'reversed': return t('general.status_reversed')
    default:         return transactionStatus.value || '-'
  }
})
</script>

<template>
  <!-- ── Main details dialog ─────────────────────────────────── -->
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="max-w-5xl p-0 overflow-hidden border-border bg-background text-foreground">
      <div class="flex max-h-[90vh] flex-col">

        <DialogHeader class="border-b border-border bg-gradient-to-r from-violet-500/10 via-background to-background px-6 py-4">
          <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
              <div class="rounded-lg bg-violet-500/15 p-2 text-violet-500">
                <Wallet class="h-5 w-5" />
              </div>
              <div>
                <DialogTitle class="text-lg font-semibold text-foreground">
                  {{ t('sidebar.owners.drawing') }}
                  <span v-if="drawing?.number"> #{{ drawing.number }}</span>
                  <span v-if="drawing"> - {{ drawing.owner?.name || '-' }}</span>
                </DialogTitle>
                <DialogDescription class="text-muted-foreground">
                  {{ drawing?.formatted_date || drawing?.date || t('general.details') }}
                </DialogDescription>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <Button
                v-if="transactionStatus === 'draft'"
                size="sm"
                class="bg-green-600 text-white hover:bg-green-700"
                :disabled="posting"
                @click="postDialogOpen = true"
              >
                {{ t('general.post') }}
              </Button>
              <Button
                v-if="transactionStatus === 'posted'"
                size="sm"
                variant="outline"
                class="border-amber-500/30 text-amber-600 hover:bg-amber-500/10 dark:text-amber-400"
                @click="openReverse"
              >
                <RotateCcw class="ltr:mr-1.5 rtl:ml-1.5 h-3.5 w-3.5" />
                {{ t('general.reverse') }}
              </Button>
              <Badge v-if="transactionStatus" :class="statusBadgeClass" variant="outline" class="capitalize text-xs">
                {{ statusLabel }}
              </Badge>
            </div>
          </div>
        </DialogHeader>

        <div v-if="drawing" class="flex-1 space-y-4 overflow-y-auto px-6 py-5">
          <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                <FileText class="h-3.5 w-3.5" />
                {{ t('general.number') }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ drawing.number ?? '-' }}</div>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                <User class="h-3.5 w-3.5" />
                {{ t('owner.owner') }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ drawing.owner?.name || '-' }}</div>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                <Landmark class="h-3.5 w-3.5" />
                {{ t('general.bank_account') }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ drawing.bank_account?.name || '-' }}</div>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                <Wallet class="h-3.5 w-3.5" />
                {{ t('owner.drawing_account') }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ drawing.drawing_account?.name || '-' }}</div>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                <CalendarDays class="h-3.5 w-3.5" />
                {{ t('general.date') }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ drawing.formatted_date || drawing.date || '-' }}</div>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                <ArrowRightLeft class="h-3.5 w-3.5" />
                {{ t('general.amount') }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ currencyLabel }} {{ formatAmount(drawing.amount) }}</div>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                <FileText class="h-3.5 w-3.5" />
                {{ t('general.remarks') }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ drawing.narration || '-' }}</div>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                <Percent class="h-3.5 w-3.5" />
                {{ t('general.rate') }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ drawing.rate || '-' }}</div>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                <Landmark class="h-3.5 w-3.5" />
                {{ t('admin.currency.currency') }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ drawing.currency?.code || '-' }}</div>
            </div>
          </div>

          <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
            <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-violet-500">
              <ArrowRightLeft class="h-4 w-4" />
              {{ t('general.transaction') }}
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="rounded-xl border border-emerald-500/25 bg-emerald-500/10 p-4">
                <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-emerald-600 dark:text-emerald-300">CR</div>
                <div class="text-sm font-medium text-foreground">{{ drawing.bank_account?.name || '-' }}</div>
                <div class="mt-1 text-xs text-muted-foreground">
                  {{ currencyLabel }} {{ formatAmount(creditLine?.credit || drawing.amount) }}
                </div>
              </div>
              <div class="rounded-xl border border-rose-500/25 bg-rose-500/10 p-4">
                <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-rose-600 dark:text-rose-300">DR</div>
                <div class="text-sm font-medium text-foreground">{{ drawing.drawing_account?.name || '-' }}</div>
                <div class="mt-1 text-xs text-muted-foreground">
                  {{ currencyLabel }} {{ formatAmount(debitLine?.debit || drawing.amount) }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="px-6 py-10 text-center text-muted-foreground">
          {{ t('general.loading') }}...
        </div>

        <DialogFooter class="border-t border-border bg-background px-6 py-4">
          <Button variant="outline" @click="emit('update:open', false)">
            {{ t('general.close') }}
          </Button>
        </DialogFooter>
      </div>
    </DialogContent>
  </Dialog>

  <TransactionActionDialog
    v-model:open="postDialogOpen"
    type="post"
    :title="t('general.post') + ' ' + t('sidebar.owners.drawing')"
    :description="t('general.post_document_desc')"
    :processing="posting"
    @confirm="postDrawing"
  />

  <!-- ── Reverse confirmation dialog ────────────────────────── -->
  <Dialog :open="reverseDialogOpen" @update:open="closeReverse">
    <DialogContent class="max-w-md">
      <DialogHeader>
        <div class="flex items-center gap-3">
          <div class="rounded-lg bg-amber-500/15 p-2 text-amber-500">
            <RotateCcw class="h-5 w-5" />
          </div>
          <DialogTitle class="text-base font-semibold">
            {{ t('general.reverse') }}
          </DialogTitle>
        </div>
        <DialogDescription class="mt-2 text-sm text-muted-foreground">
          {{ t('general.reverse_description') }}
        </DialogDescription>
      </DialogHeader>

      <div class="space-y-3 py-2">
        <div class="space-y-1.5">
          <Label for="reverse-reason" class="text-sm font-medium">
            {{ t('general.reason_for_reversal') }} <span class="text-destructive">*</span>
          </Label>
          <Input
            id="reverse-reason"
            v-model="reverseReason"
            :placeholder="t('general.reversal_placeholder')"
            @keyup.enter="confirmReverse"
          />
        </div>
      </div>

      <DialogFooter class="gap-2">
        <Button variant="outline" @click="closeReverse">
          {{ t('general.cancel') }}
        </Button>
        <Button
          variant="destructive"
          :disabled="!reverseReason.trim() || reversing"
          @click="confirmReverse"
        >
          <RotateCcw class="mr-1.5 h-4 w-4" />
          {{ reversing ? t('general.loading') + '...' : t('general.submit_reversal') }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
