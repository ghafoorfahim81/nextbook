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
import { User, Phone, Mail, MapPin, FileText, Percent, CheckCircle, Landmark, Wallet } from 'lucide-vue-next'

const { t } = useI18n()

const props = defineProps({
  open: Boolean,
  ownerId: String,
})
const emit = defineEmits(['update:open'])

const owner = ref(null)
const loading = ref(false)

const formatAmount = (value) => {
  if (value === null || value === undefined || value === '') return '-'
  return Number(value).toLocaleString(undefined, { maximumFractionDigits: 2 })
}

watch(() => props.open, async (isOpen) => {
  if (isOpen && props.ownerId) {
    loading.value = true
    try {
      const { data } = await axios.get(`/owners/${props.ownerId}`)
      owner.value = data?.data || data || null
    } finally {
      loading.value = false
    }
  }
})

function closeDialog() {
  emit('update:open', false)
  owner.value = null
}
</script>

<template>
  <Dialog :open="open" @update:open="closeDialog">
    <DialogContent class="max-w-5xl p-0 overflow-hidden border-border bg-background text-foreground">
      <div class="flex max-h-[90vh] flex-col">
        <DialogHeader class="border-b border-border bg-gradient-to-r from-violet-500/10 via-background to-background px-6 py-4">
          <div class="flex items-center gap-3">
            <div class="rounded-lg bg-violet-500/15 p-2 text-violet-500">
              <User class="h-5 w-5" />
            </div>
            <div>
              <DialogTitle class="text-lg font-semibold text-foreground">
                {{ t('owner.owner') || 'Owner' }}<span v-if="owner"> - {{ owner.name }}</span>
              </DialogTitle>
              <DialogDescription class="text-muted-foreground">
                {{ owner?.father_name || t('general.details') || 'Details' }}
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>

        <div v-if="loading" class="px-6 py-10 text-center text-muted-foreground">
          {{ t('general.loading') || 'Loading' }}...
        </div>

        <div v-else-if="owner" class="flex-1 space-y-4 overflow-y-auto px-6 py-5">
          <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                <User class="h-3.5 w-3.5" />
                {{ t('general.name') || 'Name' }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ owner.name }}</div>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                <User class="h-3.5 w-3.5" />
                {{ t('owner.father_name') || 'Father Name' }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ owner.father_name }}</div>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                <FileText class="h-3.5 w-3.5" />
                {{ t('owner.nic') || 'NIC' }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ owner.nic || '-' }}</div>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                <Percent class="h-3.5 w-3.5" />
                {{ t('owner.ownership_percentage') || 'Ownership %' }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ owner.share_percentage ?? owner.ownership_percentage }}%</div>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                <Mail class="h-3.5 w-3.5" />
                {{ t('general.email') || 'Email' }}
              </div>
              <div class="text-sm font-medium text-foreground break-all">{{ owner.email || '-' }}</div>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                <Phone class="h-3.5 w-3.5" />
                {{ t('general.phone') || 'Phone' }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ owner.phone_number || '-' }}</div>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                <MapPin class="h-3.5 w-3.5" />
                {{ t('general.address') || 'Address' }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ owner.address || '-' }}</div>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                <CheckCircle class="h-3.5 w-3.5" />
                {{ t('general.active') || 'Active' }}
              </div>
              <div class="text-sm font-medium text-foreground">
                {{ owner.is_active ? (t('general.yes') || 'Yes') : (t('general.no') || 'No') }}
              </div>
            </div>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-violet-500">
                <Landmark class="h-4 w-4" />
                {{ t('owner.capital_account') || 'Capital Account' }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ owner.capital_account?.name || '-' }}</div>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-violet-500">
                <Wallet class="h-4 w-4" />
                {{ t('owner.drawing_account') || 'Drawing Account' }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ owner.drawing_account?.name || '-' }}</div>
            </div>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-violet-500">
                <Landmark class="h-4 w-4" />
                {{ t('owner.capital_contribution') || 'Capital Contribution' }}
              </div>
              <div v-if="owner.capital_account_transaction" class="grid grid-cols-2 gap-2 text-sm">
                <div class="text-muted-foreground">{{ t('general.amount') || 'Amount' }}</div>
                <div class="font-medium text-foreground">
                  {{ owner.opening_currency?.symbol || '' }} {{ formatAmount(owner.amount) }}
                </div>
                <div class="text-muted-foreground">{{ t('admin.currency.currency') || 'Currency' }}</div>
                <div class="font-medium text-foreground">
                  {{ owner.opening_currency?.code || '-' }}
                </div>
                <div class="text-muted-foreground">{{ t('general.rate') || 'Rate' }}</div>
                <div class="font-medium text-foreground">{{ owner.rate }}</div>
                <div class="text-muted-foreground">{{ t('general.account') || 'Account' }}</div>
                <div class="font-medium text-foreground">{{ owner.capital_account_transaction.account?.name || '-' }}</div>
              </div>
              <div v-else class="text-sm text-muted-foreground">-</div>
            </div>

            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-violet-500">
                <Wallet class="h-4 w-4" />
                {{ t('owner.cash_received') || 'Cash Received' }}
              </div>
              <div v-if="owner.bank_account_transaction" class="grid grid-cols-2 gap-2 text-sm">
                <div class="text-muted-foreground">{{ t('general.amount') || 'Amount' }}</div>
                <div class="font-medium text-foreground">
                  {{ owner.opening_currency?.symbol || '' }} {{ formatAmount(owner.amount) }}
                </div>
                <div class="text-muted-foreground">{{ t('admin.currency.currency') || 'Currency' }}</div>
                <div class="font-medium text-foreground">
                  {{ owner.opening_currency?.code || '-' }}
                </div>
                <div class="text-muted-foreground">{{ t('general.rate') || 'Rate' }}</div>
                <div class="font-medium text-foreground">{{ owner.rate }}</div>
                <div class="text-muted-foreground">{{ t('general.account') || 'Account' }}</div>
                <div class="font-medium text-foreground">{{ owner.bank_account?.name || '-' }}</div>
              </div>
              <div v-else class="text-sm text-muted-foreground">-</div>
            </div>
          </div>
        </div>

        <DialogFooter class="border-t border-border bg-background px-6 py-4">
          <Button variant="outline" @click="closeDialog">
            {{ t('general.close') || 'Close' }}
          </Button>
        </DialogFooter>
      </div>
    </DialogContent>
  </Dialog>
</template>

