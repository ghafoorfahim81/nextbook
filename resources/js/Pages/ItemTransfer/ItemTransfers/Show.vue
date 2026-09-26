<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import AttachmentList from '@/Components/AttachmentList.vue'
import ShowPageToolbar from '@/Components/ShowPageToolbar.vue'
import TransactionActionDialog from '@/Components/TransactionActionDialog.vue'
import { Badge } from '@/Components/ui/badge'
import { computed, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { useDocumentAction } from '@/composables/useDocumentAction'
import { useToast } from '@/Components/ui/toast/use-toast'
import {
  ArrowLeftRight, Calendar, Store, DollarSign, FileText, User, Landmark, Coins,
} from 'lucide-vue-next'

const { t } = useI18n()
const { submit } = useDocumentAction()
const { toast } = useToast()
const page = usePage()

const props = defineProps({
  transfer: { type: Object, required: true },
})

const transfer = computed(() => props.transfer?.data ?? props.transfer ?? {})

/**
 * ShowPageToolbar speaks the shared document vocabulary (draft / posted /
 * reversed) and decides from it that only a draft can be posted and only a
 * posted document can be reversed. A transfer carries its own older set of
 * words, so it is translated here rather than teaching the toolbar a second
 * vocabulary — which also gives this page exactly the standard behaviour:
 * a draft offers Post and no Reverse, a posted transfer offers Reverse.
 */
const toolbarStatus = computed(() => ({
  pending: 'draft',
  completed: 'posted',
  cancelled: 'reversed',
}[transfer.value.status] ?? transfer.value.status))

const statusBadgeClasses = computed(() => {
  switch (transfer.value.status) {
    case 'completed': return 'border-green-500/30 bg-green-500/10 text-green-700 dark:text-green-300'
    case 'cancelled': return 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300'
    case 'pending': return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300'
    default: return 'border-border bg-muted text-foreground'
  }
})

const items = computed(() => transfer.value.items ?? [])

const totalQuantity = computed(() =>
  items.value.reduce((sum, item) => sum + Number(item.quantity || 0), 0)
)

const totalAmount = computed(() =>
  items.value.reduce((sum, item) => sum + (Number(item.quantity || 0) * Number(item.unit_price || 0)), 0)
)

const postDialogOpen = ref(false)
const reverseDialogOpen = ref(false)
const processing = ref(false)

const postTransfer = () => {
  if (processing.value) return
  processing.value = true
  submit(route('item-transfers.post', transfer.value.id), {}, {
    preserveScroll: true,
    onSuccess: () => {
      // A failed post redirects back with a flash error (e.g. stock consumed by
      // another document). Keep the dialog open and surface it.
      const flashError = page.props.flash?.error
      if (flashError) {
        toast({ title: t('general.error'), description: flashError, variant: 'destructive' })
        return
      }
      postDialogOpen.value = false
    },
    onFinish: () => { processing.value = false },
  })
}

const reverseTransfer = (reason) => {
  if (processing.value) return
  processing.value = true
  submit(route('item-transfers.reverse', transfer.value.id), { reason }, {
    preserveScroll: true,
    onSuccess: () => { reverseDialogOpen.value = false },
    onFinish: () => { processing.value = false },
  })
}
</script>

<template>
  <AppLayout :title="`${t('item_transfer.item_transfer')} ${transfer.date || ''}`">
    <div class="space-y-6 min-w-0 max-w-full overflow-x-clip">
      <ShowPageToolbar
        back-route="item-transfers.index"
        :status="toolbarStatus"
        :edit-route="transfer.id ? route('item-transfers.edit', transfer.id) : null"
        edit-permission="item_transfers.update"
        @post="postDialogOpen = true"
        @reverse="reverseDialogOpen = true"
      />

      <TransactionActionDialog
        v-model:open="postDialogOpen"
        type="post"
        :title="t('general.post') + ' ' + t('item_transfer.item_transfer')"
        :description="t('general.post_document_desc')"
        :processing="processing"
        @confirm="postTransfer"
      />
      <TransactionActionDialog
        v-model:open="reverseDialogOpen"
        type="reverse"
        :title="t('general.reverse') + ' ' + t('item_transfer.item_transfer')"
        :description="t('general.reverse_description')"
        :processing="processing"
        @confirm="reverseTransfer"
      />

      <!-- Header card -->
      <fieldset class="min-w-0 rounded-xl border border-border bg-card px-5 pb-5 pt-3 text-card-foreground shadow-sm">
        <legend class="px-2 flex items-center gap-1.5">
          <span class="text-sm font-semibold text-violet-500">{{ t('item_transfer.item_transfer') }}</span>
          <Badge :class="statusBadgeClasses">{{ transfer.status_label || '-' }}</Badge>
        </legend>

        <div class="mb-4 flex items-center gap-2">
          <FileText class="h-5 w-5 text-violet-500" />
          <h3 class="text-base font-semibold text-foreground">{{ t('item_transfer.transfer_details') }}</h3>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <div class="space-y-1.5">
            <div class="flex items-center gap-2 text-xs text-muted-foreground">
              <Calendar class="h-3 w-3" />{{ t('general.date') }}
            </div>
            <div class="text-sm font-medium text-foreground">{{ transfer.date || '-' }}</div>
          </div>
          <div class="space-y-1.5">
            <div class="flex items-center gap-2 text-xs text-muted-foreground">
              <Store class="h-3 w-3" />{{ t('item_transfer.from_warehouse') }}
            </div>
            <div class="text-sm font-medium text-foreground">{{ transfer.from_warehouse?.name || '-' }}</div>
          </div>
          <div class="space-y-1.5">
            <div class="flex items-center gap-2 text-xs text-muted-foreground">
              <Store class="h-3 w-3" />{{ t('item_transfer.to_warehouse') }}
            </div>
            <div class="text-sm font-medium text-foreground">{{ transfer.to_warehouse?.name || '-' }}</div>
          </div>
          <div class="space-y-1.5">
            <div class="flex items-center gap-2 text-xs text-muted-foreground">
              <DollarSign class="h-3 w-3" />{{ t('item_transfer.transfer_cost') }}
            </div>
            <div class="text-sm font-medium text-foreground">
              {{ transfer.has_transfer_cost ? (transfer.transfer_cost || 0) : t('general.no') }}
            </div>
          </div>

          <template v-if="transfer.has_transfer_cost">
            <div class="space-y-1.5">
              <div class="flex items-center gap-2 text-xs text-muted-foreground">
                <Landmark class="h-3 w-3" />{{ t('item_transfer.bank_account') }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ transfer.bank_account?.name || '-' }}</div>
            </div>
            <div class="space-y-1.5">
              <div class="flex items-center gap-2 text-xs text-muted-foreground">
                <DollarSign class="h-3 w-3" />{{ t('item_transfer.expense_account') }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ transfer.expense_account?.name || '-' }}</div>
            </div>
            <div class="space-y-1.5">
              <div class="flex items-center gap-2 text-xs text-muted-foreground">
                <Coins class="h-3 w-3" />{{ t('admin.currency.currency') }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ transfer.currency?.name || '-' }}</div>
            </div>
            <div class="space-y-1.5">
              <div class="flex items-center gap-2 text-xs text-muted-foreground">
                <Coins class="h-3 w-3" />{{ t('general.rate') }}
              </div>
              <div class="text-sm font-medium text-foreground">{{ transfer.rate || '-' }}</div>
            </div>
          </template>

          <div class="space-y-1.5">
            <div class="flex items-center gap-2 text-xs text-muted-foreground">
              <User class="h-3 w-3" />{{ t('general.created_by') }}
            </div>
            <div class="text-sm font-medium text-foreground">{{ transfer.created_by?.name || '-' }}</div>
          </div>
          <div class="space-y-1.5">
            <div class="flex items-center gap-2 text-xs text-muted-foreground">
              <User class="h-3 w-3" />{{ t('general.updated_by') }}
            </div>
            <div class="text-sm font-medium text-foreground">{{ transfer.updated_by?.name || '-' }}</div>
          </div>
        </div>

        <div v-if="transfer.remarks" class="mt-4 space-y-1.5">
          <div class="text-xs text-muted-foreground">{{ t('general.remarks') }}</div>
          <p class="text-sm text-foreground">{{ transfer.remarks }}</p>
        </div>
      </fieldset>

      <!-- Lines -->
      <div class="min-w-0 overflow-hidden rounded-xl border border-border bg-card">
        <div class="flex items-center gap-2 border-b border-border bg-muted/30 px-4 py-2">
          <ArrowLeftRight class="h-5 w-5 text-violet-500" />
          <h3 class="text-base font-semibold text-foreground">{{ t('item_transfer.transfer_items') }}</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="border-b border-border bg-muted/30">
              <tr>
                <th class="px-3 py-2 text-start text-xs font-semibold text-muted-foreground">#</th>
                <th class="px-3 py-2 text-start text-xs font-semibold text-muted-foreground">{{ t('item.item') }}</th>
                <th class="px-3 py-2 text-start text-xs font-semibold text-muted-foreground">{{ t('item.variant') }}</th>
                <th class="px-3 py-2 text-start text-xs font-semibold text-muted-foreground">{{ t('general.batch') }}</th>
                <th class="px-3 py-2 text-start text-xs font-semibold text-muted-foreground">{{ t('general.expire_date') }}</th>
                <th class="px-3 py-2 text-end text-xs font-semibold text-muted-foreground">{{ t('general.qty') }}</th>
                <th class="px-3 py-2 text-start text-xs font-semibold text-muted-foreground">{{ t('general.unit') }}</th>
                <th class="px-3 py-2 text-end text-xs font-semibold text-muted-foreground">{{ t('general.unit_price') }}</th>
                <th class="px-3 py-2 text-end text-xs font-semibold text-muted-foreground">{{ t('general.total') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border">
              <tr v-for="(item, index) in items" :key="item.id" class="hover:bg-muted/30">
                <td class="whitespace-nowrap px-3 py-2 text-foreground">{{ index + 1 }}</td>
                <td class="whitespace-nowrap px-3 py-2 text-foreground">
                  <div class="font-medium">{{ item.item?.name }}</div>
                  <div class="text-xs text-muted-foreground">{{ item.item?.code }}</div>
                </td>
                <td class="whitespace-nowrap px-3 py-2 text-foreground">{{ item.variant?.display_name || '-' }}</td>
                <td class="whitespace-nowrap px-3 py-2 text-foreground">{{ item.batch || '-' }}</td>
                <td class="whitespace-nowrap px-3 py-2 text-foreground">{{ item.expire_date || '-' }}</td>
                <td class="whitespace-nowrap px-3 py-2 text-end text-foreground">{{ item.quantity }}</td>
                <td class="whitespace-nowrap px-3 py-2 text-foreground">{{ item.unit_measure?.name || '-' }}</td>
                <td class="whitespace-nowrap px-3 py-2 text-end text-foreground">{{ item.unit_price || 0 }}</td>
                <td class="whitespace-nowrap px-3 py-2 text-end font-semibold text-foreground">
                  {{ (Number(item.quantity || 0) * Number(item.unit_price || 0)).toFixed(2) }}
                </td>
              </tr>
              <tr v-if="!items.length">
                <td colspan="9" class="py-8 text-center text-muted-foreground">{{ t('general.no_record_available') }}</td>
              </tr>
            </tbody>
            <tfoot v-if="items.length" class="border-t border-border bg-muted/30">
              <tr>
                <td colspan="5" class="px-3 py-2 text-end text-sm font-semibold text-foreground">{{ t('general.total') }}:</td>
                <td class="px-3 py-2 text-end text-sm font-semibold text-foreground">{{ totalQuantity }}</td>
                <td colspan="2"></td>
                <td class="px-3 py-2 text-end text-lg font-bold text-violet-600">{{ totalAmount.toFixed(2) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <AttachmentList v-if="transfer.attachments?.length" :items="transfer.attachments" :label="t('general.attachments')" />
    </div>
  </AppLayout>
</template>
