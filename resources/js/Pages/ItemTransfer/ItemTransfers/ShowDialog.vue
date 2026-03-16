<script setup>
import { ref, watch, computed } from 'vue'
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
import { Badge } from '@/Components/ui/badge'
import { ArrowLeftRight, Calendar, Store, DollarSign, FileText, User } from 'lucide-vue-next'
import { router } from '@inertiajs/vue3'
import { useToast } from '@/Components/ui/toast/use-toast'

const { t } = useI18n()
const { toast } = useToast()

const props = defineProps({
  open: Boolean,
  transferId: String,
})

const emit = defineEmits(['update:open'])

const transfer = ref(null)
const loading = ref(false)
const actionLoading = ref(false)

const statusBadgeClasses = computed(() => {
  if (!transfer.value) return 'bg-muted text-foreground border-border'
  switch (transfer.value.status) {
    case 'completed':
      return 'bg-green-500/15 text-green-700 border-green-500/30 dark:text-green-300'
    case 'cancelled':
      return 'bg-red-500/15 text-red-700 border-red-500/30 dark:text-red-300'
    case 'pending':
      return 'bg-yellow-500/15 text-yellow-700 border-yellow-500/30 dark:text-yellow-300'
    default:
      return 'bg-muted text-foreground border-border'
  }
})

const totalQuantity = computed(() => {
  if (!transfer.value?.items?.length) return 0
  return transfer.value.items.reduce((sum, item) => sum + Number(item.quantity || 0), 0)
})

const totalAmount = computed(() => {
  if (!transfer.value?.items?.length) return 0
  return transfer.value.items.reduce((sum, item) => {
    return sum + (Number(item.quantity || 0) * Number(item.unit_price || 0))
  }, 0)
})

async function fetchTransfer() {
  if (!props.transferId) return
  const { data } = await axios.get(`/item-transfers/${props.transferId}`)
  transfer.value = data?.data || null
}

watch(() => props.open, async (isOpen) => {
  if (isOpen && props.transferId) {
    loading.value = true
    try {
      await fetchTransfer()
    } finally {
      loading.value = false
    }
  }
})

function completeTransfer() {
  if (!props.transferId || actionLoading.value) return
  actionLoading.value = true
  router.patch(route('item-transfers.complete', props.transferId), {}, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: async () => {
      await fetchTransfer()
      toast({
        title: t('general.success'),
        description: t('item_transfer.completed_successfully'),
        variant: 'success',
        class: 'bg-green-600 text-white',
      })
    },
    onFinish: () => {
      actionLoading.value = false
    },
  })
}

function cancelTransfer() {
  if (!props.transferId || actionLoading.value) return
  actionLoading.value = true
  router.patch(route('item-transfers.cancel', props.transferId), {}, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: async () => {
      await fetchTransfer()
      toast({
        title: t('general.success'),
        description: t('item_transfer.cancelled_successfully'),
        variant: 'success',
        class: 'bg-green-600 text-white',
      })
    },
    onFinish: () => {
      actionLoading.value = false
    },
  })
}

function closeDialog() {
  emit('update:open', false)
  transfer.value = null
}
</script>

<template>
  <Dialog :open="open" @update:open="closeDialog">
    <DialogContent class="max-w-5xl max-h-[90vh] overflow-y-auto">
      <DialogHeader>
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <ArrowLeftRight class="w-6 h-6 text-violet-500" />
            <DialogTitle class="text-xl">
              {{ t('item_transfer.item_transfer') }}
            </DialogTitle>
          </div>
          <Badge :class="statusBadgeClasses">
            {{ transfer?.status_label || '-' }}
          </Badge>
        </div>
        <DialogDescription v-if="transfer?.remarks">
          {{ transfer.remarks }}
        </DialogDescription>
      </DialogHeader>

      <div v-if="loading" class="py-6 text-center text-muted-foreground">
        {{ t('general.loading') }}...
      </div>

      <div v-else-if="transfer" class="space-y-6">
        <div class="rounded-lg p-4 border border-border bg-muted/30">
          <div class="flex items-center gap-2 mb-3">
            <FileText class="w-5 h-5 text-violet-500" />
            <h3 class="text-base font-semibold text-foreground">{{ t('item_transfer.transfer_details') }}</h3>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="space-y-1">
              <div class="flex items-center gap-2 text-xs text-muted-foreground">
                <Calendar class="w-3 h-3" />
                {{ t('general.date') }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ transfer.date }}</div>
            </div>
            <div class="space-y-1">
              <div class="flex items-center gap-2 text-xs text-muted-foreground">
                <Store class="w-3 h-3" />
                {{ t('item_transfer.from_store') }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ transfer.from_store?.name || '-' }}</div>
            </div>
            <div class="space-y-1">
              <div class="flex items-center gap-2 text-xs text-muted-foreground">
                <Store class="w-3 h-3" />
                {{ t('item_transfer.to_store') }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ transfer.to_store?.name || '-' }}</div>
            </div>
            <div class="space-y-1">
              <div class="flex items-center gap-2 text-xs text-muted-foreground">
                <DollarSign class="w-3 h-3" />
                {{ t('item_transfer.transfer_cost') }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ transfer.transfer_cost || 0 }}</div>
            </div>
            <div class="space-y-1">
              <div class="flex items-center gap-2 text-xs text-muted-foreground">
                <User class="w-3 h-3" />
                {{ t('general.created_by') }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ transfer.created_by?.name || '-' }}</div>
            </div>
            <div class="space-y-1">
              <div class="flex items-center gap-2 text-xs text-muted-foreground">
                <User class="w-3 h-3" />
                {{ t('general.updated_by') }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ transfer.updated_by?.name || '-' }}</div>
            </div>
          </div>
        </div>

        <div class="border border-border rounded-lg overflow-hidden">
          <div class="bg-muted/30 px-4 py-2 border-b border-border">
            <div class="flex items-center gap-2">
              <ArrowLeftRight class="w-5 h-5 text-violet-500" />
              <h3 class="text-base font-semibold text-foreground">{{ t('item_transfer.transfer_items') }}</h3>
            </div>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-muted/30 border-b border-border">
                <tr>
                  <th class="px-3 py-2 text-left text-xs font-semibold text-muted-foreground">#</th>
                  <th class="px-3 py-2 text-left text-xs font-semibold text-muted-foreground">{{ t('item.item') }}</th>
                  <th class="px-3 py-2 text-left text-xs font-semibold text-muted-foreground">{{ t('general.batch') }}</th>
                  <th class="px-3 py-2 text-left text-xs font-semibold text-muted-foreground">{{ t('general.expire_date') }}</th>
                  <th class="px-3 py-2 text-right text-xs font-semibold text-muted-foreground">{{ t('general.qty') }}</th>
                  <th class="px-3 py-2 text-left text-xs font-semibold text-muted-foreground">{{ t('general.unit') }}</th>
                  <th class="px-3 py-2 text-right text-xs font-semibold text-muted-foreground">{{ t('general.unit_price') }}</th>
                  <th class="px-3 py-2 text-right text-xs font-semibold text-muted-foreground">{{ t('general.total') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-border">
                <tr v-for="(item, index) in transfer.items" :key="item.id" class="hover:bg-muted/30">
                  <td class="px-3 py-2 text-foreground">{{ index + 1 }}</td>
                  <td class="px-3 py-2 text-foreground">
                    <div>
                      <div class="font-medium">{{ item.item?.name }}</div>
                      <div class="text-xs text-muted-foreground">{{ item.item?.code }}</div>
                    </div>
                  </td>
                  <td class="px-3 py-2 text-foreground">{{ item.batch || '-' }}</td>
                  <td class="px-3 py-2 text-foreground">{{ item.expire_date || '-' }}</td>
                  <td class="px-3 py-2 text-foreground text-right">{{ item.quantity }}</td>
                  <td class="px-3 py-2 text-foreground">{{ item.unit_measure?.name || '-' }}</td>
                  <td class="px-3 py-2 text-foreground text-right">{{ item.unit_price || 0 }}</td>
                  <td class="px-3 py-2 font-semibold text-foreground text-right">
                    {{ (Number(item.quantity || 0) * Number(item.unit_price || 0)).toFixed(2) }}
                  </td>
                </tr>
              </tbody>
              <tfoot class="bg-muted/30 border-t border-border">
                <tr>
                  <td colspan="4" class="px-3 py-2 text-sm font-semibold text-foreground text-right">
                    {{ t('general.total') }}:
                  </td>
                  <td class="px-3 py-2 text-sm font-semibold text-foreground text-right">{{ totalQuantity }}</td>
                  <td class="px-3 py-2"></td>
                  <td class="px-3 py-2 text-sm font-semibold text-foreground text-right"></td>
                  <td class="px-3 py-2 text-lg font-bold text-violet-600 text-right">
                    {{ totalAmount.toFixed(2) }}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>

      <DialogFooter>
        <div class="flex items-center justify-between w-full gap-2">
          <div v-if="transfer?.status === 'pending'" class="flex gap-2">
            <Button
              variant="destructive"
              class="flex items-center gap-2"
              :disabled="actionLoading"
              @click="cancelTransfer"
            >
              {{ t('item_transfer.cancel_transfer') }}
            </Button> 
            <Button
              class="flex items-center gap-2 bg-green-600 hover:bg-green-700"
              :disabled="actionLoading"
              @click="completeTransfer"
            >
              {{ t('item_transfer.complete_transfer') }}
            </Button>
          </div>

          <Button variant="outline" @click="closeDialog">
            {{ t('general.close') }}
          </Button>
        </div>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

<style scoped>
td,
th {
  white-space: nowrap;
}
</style>

