<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import TransactionActionDialog from '@/Components/TransactionActionDialog.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { router } from '@inertiajs/vue3'
import { useAuth } from '@/composables/useAuth'
import { useDocumentAction } from '@/composables/useDocumentAction'

const { t } = useI18n()
const { can } = useAuth()
const { submit } = useDocumentAction()

const props = defineProps({
  contraSettlement: { type: Object, required: true },
  customerSettlements: { type: Array, default: () => [] },
  supplierSettlements: { type: Array, default: () => [] },
})

const doc = computed(() => props.contraSettlement?.data ?? props.contraSettlement ?? {})
const reverseDialogOpen = ref(false)

const money = (value) => Number(value || 0).toLocaleString(undefined, {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
})

function reverseDocument(reason) {
  submit(route('contra-settlements.reverse', doc.value.id), { reason }, {
    preserveScroll: true,
    onSuccess: () => { reverseDialogOpen.value = false },
  })
}

const statusClass = (status) => {
  switch (status) {
    case 'posted': return 'border-green-500/30 bg-green-500/10 text-green-700 dark:text-green-300'
    case 'reversed': return 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300'
    default: return 'border-border bg-muted text-foreground'
  }
}
</script>

<template>
  <AppLayout :title="`${t('contra.contra_settlement')} #${doc.number}`">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
      <Button size="sm" variant="ghost" @click="router.visit(route('contra-settlements.index'))">
        {{ t('contra.contra_settlements') }}
      </Button>

      <div class="flex items-center gap-2">
        <Badge :class="statusClass(doc.status)" variant="outline">{{ doc.status_label }}</Badge>
        <Button
          v-if="doc.status === 'posted' && can('contra_settlements.update')"
          size="sm"
          variant="outline"
          @click="reverseDialogOpen = true"
        >
          {{ t('general.reverse') }}
        </Button>
      </div>
    </div>

    <div class="rounded-xl border p-4">
      <div class="text-sm font-semibold text-violet-500">
        {{ t('contra.contra_settlement') }} #{{ doc.number }}
      </div>
      <p class="mt-1 text-xs text-muted-foreground">{{ t('contra.what_it_is') }}</p>

      <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
        <div>
          <div class="text-xs text-muted-foreground">{{ t('general.date') }}</div>
          <div class="font-medium">{{ doc.date || '-' }}</div>
        </div>
        <div>
          <div class="text-xs text-muted-foreground">{{ t('contra.offset_amount') }}</div>
          <div class="font-medium">{{ money(doc.amount) }} {{ doc.currency_code }}</div>
        </div>
        <div>
          <div class="text-xs text-muted-foreground">{{ t('general.rate') }}</div>
          <div class="font-medium">{{ doc.rate }}</div>
        </div>
        <div>
          <div class="text-xs text-muted-foreground">{{ t('general.created_by') }}</div>
          <div class="font-medium">{{ doc.created_by?.name ?? '-' }}</div>
        </div>
      </div>

      <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        <div class="rounded-lg border p-3">
          <div class="text-xs text-muted-foreground">{{ t('contra.customer_account') }}</div>
          <div class="font-medium">{{ doc.customer_ledger_name || '-' }}</div>
        </div>
        <div class="rounded-lg border p-3">
          <div class="text-xs text-muted-foreground">{{ t('contra.supplier_account') }}</div>
          <div class="font-medium">{{ doc.supplier_ledger_name || '-' }}</div>
        </div>
      </div>

      <div v-if="doc.narration" class="mt-4">
        <div class="text-xs text-muted-foreground">{{ t('general.narration') }}</div>
        <div class="text-sm">{{ doc.narration }}</div>
      </div>
    </div>

    <!-- What each half actually relieved. The two lists together are the whole
         document: one shows the invoices closed, the other the bills. -->
    <div class="mt-4 grid grid-cols-1 lg:grid-cols-2 gap-4">
      <div class="rounded-xl border p-4">
        <div class="text-sm font-semibold text-violet-500">{{ t('contra.select_invoices') }}</div>
        <table v-if="customerSettlements.length" class="mt-3 w-full text-sm">
          <thead>
            <tr class="text-xs text-muted-foreground">
              <th class="ltr:text-left rtl:text-right py-1">{{ t('settlement.document') }}</th>
              <th class="ltr:text-left rtl:text-right py-1">{{ t('general.date') }}</th>
              <th class="ltr:text-right rtl:text-left py-1">{{ t('settlement.applied') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in customerSettlements" :key="row.id" class="border-t">
              <td class="py-1">{{ row.document_type }} {{ row.document_number }}</td>
              <td class="py-1">{{ row.date }}</td>
              <td class="py-1 ltr:text-right rtl:text-left">
                {{ money(row.amount_applied) }} {{ row.currency_code }}
              </td>
            </tr>
          </tbody>
        </table>
        <p v-else class="mt-3 text-xs text-muted-foreground">{{ t('settlement.nothing_open') }}</p>
      </div>

      <div class="rounded-xl border p-4">
        <div class="text-sm font-semibold text-violet-500">{{ t('contra.select_bills') }}</div>
        <table v-if="supplierSettlements.length" class="mt-3 w-full text-sm">
          <thead>
            <tr class="text-xs text-muted-foreground">
              <th class="ltr:text-left rtl:text-right py-1">{{ t('settlement.document') }}</th>
              <th class="ltr:text-left rtl:text-right py-1">{{ t('general.date') }}</th>
              <th class="ltr:text-right rtl:text-left py-1">{{ t('settlement.applied') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in supplierSettlements" :key="row.id" class="border-t">
              <td class="py-1">{{ row.document_type }} {{ row.document_number }}</td>
              <td class="py-1">{{ row.date }}</td>
              <td class="py-1 ltr:text-right rtl:text-left">
                {{ money(row.amount_applied) }} {{ row.currency_code }}
              </td>
            </tr>
          </tbody>
        </table>
        <p v-else class="mt-3 text-xs text-muted-foreground">{{ t('settlement.nothing_open') }}</p>
      </div>
    </div>

    <p class="mt-4 text-xs text-muted-foreground">{{ t('contra.no_edit_hint') }}</p>

    <TransactionActionDialog
      v-model:open="reverseDialogOpen"
      type="reverse"
      :title="t('contra.reverse_contra_settlement')"
      :description="t('contra.reverse_hint')"
      @confirm="reverseDocument"
    />
  </AppLayout>
</template>
