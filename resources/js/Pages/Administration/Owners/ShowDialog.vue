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
import { User, Phone, Mail, MapPin, FileText, Percent, CheckCircle, DollarSign, Banknote, TrendingUp } from 'lucide-vue-next'

const { t } = useI18n()

const props = defineProps({
  open: Boolean,
  ownerId: String,
})
const emit = defineEmits(['update:open'])

const owner = ref(null)
const loading = ref(false)

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
    <DialogContent class="max-w-3xl">
      <DialogHeader>
        <div class="flex items-center gap-3">
          <User class="w-6 h-6 text-violet-500" />
          <DialogTitle class="text-xl">
            {{ t('owner.owner') || 'Owner' }} <span v-if="owner">- {{ owner.name }}</span>
          </DialogTitle>
        </div>
      </DialogHeader>

      <div v-if="loading" class="py-6 text-center text-muted-foreground">
        {{ t('general.loading') || 'Loading' }}...
      </div>

      <div v-else-if="owner" class="space-y-6">
        <!-- Owner details -->
        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
          <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div class="space-y-1">
              <div class="flex items-center gap-2 text-xs text-gray-500">
                <User class="w-3 h-3" />
                {{ t('general.name') || 'Name' }}
              </div>
              <div class="text-sm font-medium text-gray-900">{{ owner.name }}</div>
            </div>
            <div class="space-y-1">
              <div class="flex items-center gap-2 text-xs text-gray-500">
                <User class="w-3 h-3" />
                {{ t('owner.father_name') || 'Father Name' }}
              </div>
              <div class="text-sm font-medium text-gray-900">{{ owner.father_name }}</div>
            </div>
            <div class="space-y-1">
              <div class="flex items-center gap-2 text-xs text-gray-500">
                <FileText class="w-3 h-3" />
                {{ t('owner.nic') || 'NIC' }}
              </div>
              <div class="text-sm font-medium text-gray-900">{{ owner.nic || '-' }}</div>
            </div>
            <div class="space-y-1">
              <div class="flex items-center gap-2 text-xs text-gray-500">
                <Mail class="w-3 h-3" />
                {{ t('general.email') || 'Email' }}
              </div>
              <div class="text-sm font-medium text-gray-900">{{ owner.email || '-' }}</div>
            </div>
            <div class="space-y-1">
              <div class="flex items-center gap-2 text-xs text-gray-500">
                <Phone class="w-3 h-3" />
                {{ t('general.phone') || 'Phone' }}
              </div>
              <div class="text-sm font-medium text-gray-900">{{ owner.phone_number || '-' }}</div>
            </div>
            <div class="space-y-1">
              <div class="flex items-center gap-2 text-xs text-gray-500">
                <MapPin class="w-3 h-3" />
                {{ t('general.address') || 'Address' }}
              </div>
              <div class="text-sm font-medium text-gray-900">{{ owner.address || '-' }}</div>
            </div>
            <div class="space-y-1">
              <div class="flex items-center gap-2 text-xs text-gray-500">
                <Percent class="w-3 h-3" />
                {{ t('owner.ownership_percentage') || 'Ownership %' }}
              </div>
              <div class="text-sm font-medium text-gray-900">{{ owner.ownership_percentage }}%</div>
            </div>
            <div class="space-y-1">
              <div class="flex items-center gap-2 text-xs text-gray-500">
                <CheckCircle class="w-3 h-3" />
                {{ t('general.active') || 'Active' }}
              </div>
              <div class="text-sm font-medium text-gray-900">{{ owner.is_active ? (t('general.yes') || 'Yes') : (t('general.no') || 'No') }}</div>
            </div>
          </div>
        </div>

        <!-- Accounts -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="border border-gray-200 rounded-lg p-4">
            <div class="text-sm font-semibold mb-3 text-violet-500">{{ t('owner.capital_account') || 'Capital Account' }}</div>
            <div class="text-sm font-medium">{{ owner.capital_account?.name || '-' }}</div>
          </div>
          <div class="border border-gray-200 rounded-lg p-4">
            <div class="text-sm font-semibold mb-3 text-violet-500">{{ t('owner.drawing_account') || 'Drawing Account' }}</div>
            <div class="text-sm font-medium">{{ owner.drawing_account?.name || '-' }}</div>
          </div>
        </div>

        <!-- Capital Contribution Transaction -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="border border-gray-200 rounded-lg p-4">
            <div class="text-sm font-semibold mb-3 text-violet-500">{{ t('owner.capital_contribution') || 'Capital Contribution' }}</div>
            <div v-if="owner.capital_transaction" class="grid grid-cols-2 gap-2 text-sm">
              <div class="text-muted-foreground">{{ t('general.amount') || 'Amount' }}</div>
              <div class="font-medium">
                {{ owner.capital_transaction.currency?.symbol || '' }} {{ owner.capital_transaction.amount }}
              </div>
              <div class="text-muted-foreground">{{ t('admin.currency.currency') || 'Currency' }}</div>
              <div class="font-medium">
                {{ owner.capital_transaction.currency?.code || '-' }}
              </div>
              <div class="text-muted-foreground">{{ t('general.rate') || 'Rate' }}</div>
              <div class="font-medium">{{ owner.capital_transaction.rate }}</div>
              <div class="text-muted-foreground">{{ t('general.account') || 'Account' }}</div>
              <div class="font-medium">{{ owner.capital_transaction.account?.name || '-' }}</div>
            </div>
            <div v-else class="text-sm text-muted-foreground">-</div>
          </div>
          <div class="border border-gray-200 rounded-lg p-4">
            <div class="text-sm font-semibold mb-3 text-violet-500">{{ t('owner.cash_received') || 'Cash Received' }}</div>
            <div v-if="owner.account_transaction" class="grid grid-cols-2 gap-2 text-sm">
              <div class="text-muted-foreground">{{ t('general.amount') || 'Amount' }}</div>
              <div class="font-medium">
                {{ owner.account_transaction.currency?.symbol || '' }} {{ owner.account_transaction.amount }}
              </div>
              <div class="text-muted-foreground">{{ t('admin.currency.currency') || 'Currency' }}</div>
              <div class="font-medium">
                {{ owner.account_transaction.currency?.code || '-' }}
              </div>
              <div class="text-muted-foreground">{{ t('general.rate') || 'Rate' }}</div>
              <div class="font-medium">{{ owner.account_transaction.rate }}</div>
              <div class="text-muted-foreground">{{ t('general.account') || 'Account' }}</div>
              <div class="font-medium">{{ owner.account_transaction.account?.name || '-' }}</div>
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


