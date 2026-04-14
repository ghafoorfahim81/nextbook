<script setup>
import axios from 'axios'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Dialog, DialogContent } from '@/Components/ui/dialog'
import { Button } from '@/Components/ui/button'
import LedgerListTable from '@/Components/reports/LedgerListTable.vue'
import { Printer, UserStar } from 'lucide-vue-next'

const { t } = useI18n()

const props = defineProps({
  open: { type: Boolean, default: false },
  supplierId: { type: String, default: null },
})

const emit = defineEmits(['update:open'])

const supplier = ref(null)
const purchases = ref([])
const payments = ref([])
const receipts = ref([])
const loading = ref(false)

const activeMainTab = ref('general')
const activeTxnTab = ref('purchases')

const supplierData = computed(() => supplier.value ?? {})
const statement = computed(() => supplierData.value.statement ?? {})
const opening = computed(() => supplierData.value.opening ?? {})
const openingRows = computed(() => (opening.value?.id ? [opening.value] : []))

const formatAmount = (value) => {
  if (value === null || value === undefined) return '-'
  return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const openPrint = (routeName, id) => {
  if (!routeName || !id) return
  window.open(route(routeName, id), '_blank')
}

const loadSupplier = async (id) => {
  if (!id) return

  loading.value = true

  try {
    const response = await axios.get(`/suppliers/${id}`)
    const data = response.data
    supplier.value = data.supplier?.data ?? data.supplier ?? null
    purchases.value = data.purchases?.data ?? data.purchases ?? []
    payments.value = data.payments?.data ?? data.payments ?? []
    receipts.value = data.receipts?.data ?? data.receipts ?? []
  } catch (error) {
    console.error('Error loading supplier:', error)
  } finally {
    loading.value = false
  }
}

const exportUrl = (list) => route('suppliers.export', {
  supplier: supplierData.value.id,
  list,
})

const purchasesTableRows = computed(() => purchases.value.map((row) => ({
  id: row.id,
  number: row.number || row.reference_id || row.id,
  date: row.date,
  type: row.type || '-',
  amount: row.amount,
  status: row.payment_status_label || row.payment_status || '-',
  description: row.description || '-',
})))

const movementTableRows = (source, printRoute) => source.map((row) => ({
  id: row.id,
  number: row.number || row.reference_id || row.id,
  date: row.date,
  amount: row.amount,
  currency: row.currency_code || row.transaction?.currency?.code || row.transaction?.currency?.name || '',
  rate: row.rate || 0,
  payment_mode: row.payment_mode_label || row.payment_mode || '-',
  description: row.narration || row.description || '-',
  printRoute,
}))

const receiptTableRows = computed(() => movementTableRows(receipts.value, 'receipts.print'))
const paymentTableRows = computed(() => movementTableRows(payments.value, 'payments.print'))

const purchasesColumns = computed(() => [
  { key: 'number', label: t('general.number') },
  { key: 'date', label: t('general.date') },
  { key: 'type', label: t('general.type') },
  { key: 'amount', label: t('general.amount'), type: 'money', align: 'right' },
  { key: 'status', label: t('general.status') },
  { key: 'description', label: t('general.description') },
])

const movementColumns = computed(() => [
  { key: 'number', label: t('general.number') },
  { key: 'date', label: t('general.date') },
  { key: 'amount', label: t('general.amount'), type: 'money', align: 'right' },
  { key: 'currency', label: t('admin.currency.currency') },
  { key: 'rate', label: t('general.rate'), type: 'money', align: 'right' },
  { key: 'payment_mode', label: t('general.payment_method') },
  { key: 'description', label: t('general.description') },
  { key: 'actions', label: t('general.actions'), align: 'right' },
])

watch(
  () => props.open,
  async (isOpen) => {
    if (isOpen && props.supplierId) {
      await loadSupplier(props.supplierId)
    } else if (!isOpen) {
      supplier.value = null
      purchases.value = []
      payments.value = []
      receipts.value = []
      activeMainTab.value = 'general'
      activeTxnTab.value = 'purchases'
    }
  }
)

const closeDialog = () => {
  emit('update:open', false)
}
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="max-w-5xl p-0">
      <div class="w-full bg-background text-foreground rounded-2xl shadow-2xl flex flex-col max-h-[90vh] overflow-hidden">
        <div class="border-b border-border bg-gradient-to-r rtl:bg-gradient-to-l from-violet-400/70 to-background px-6 py-4 flex justify-between items-center dark:from-violet-900/40 dark:to-background">
          <div class="flex items-center gap-3">
            <div class="bg-violet-500 text-white p-3 rounded-lg">
              <UserStar class="w-6 h-6" />
            </div>
            <div>
              <h2 class="text-xl font-bold text-foreground">
                {{ supplierData.name }}
              </h2>
              <p class="text-xs text-muted-foreground mt-1">
                {{ supplierData.code }}
              </p>
            </div>
          </div>
        </div>

        <div class="flex-1 overflow-y-auto px-6 py-4">
          <div v-if="loading" class="flex justify-center items-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
          </div>

          <div v-else-if="supplierData && supplierData.id" class="space-y-4">
            <div class="border-b border-border flex gap-4">
              <button
                type="button"
                class="px-4 py-2 -mb-px border-b-2"
                :class="activeMainTab === 'general' ? 'border-primary text-primary font-semibold' : 'border-transparent text-muted-foreground hover:text-foreground'"
                @click="activeMainTab = 'general'"
              >
                {{ t('general.general') }}
              </button>
              <button
                type="button"
                class="px-4 py-2 -mb-px border-b-2"
                :class="activeMainTab === 'opening' ? 'border-primary text-primary font-semibold' : 'border-transparent text-muted-foreground hover:text-foreground'"
                @click="activeMainTab = 'opening'"
              >
                {{ t('item.opening') }}
              </button>
            </div>

            <div v-if="activeMainTab === 'general'" class="space-y-4">
              <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="bg-card rounded-xl shadow-sm border p-4 flex flex-col items-center gap-4">
                  <div class="w-16 h-16 rounded-full bg-gradient-to-tr rtl:bg-gradient-tl from-violet-500 to-violet-400 flex items-center justify-center text-white text-xl font-bold">
                    {{ (supplierData.name || '').charAt(0).toUpperCase() }}
                  </div>
                  <div class="text-center">
                    <div class="text-base font-semibold text-primary">
                      {{ supplierData.name }}
                    </div>
                    <div class="text-xs text-muted-foreground mt-1">
                      {{ supplierData.code }}
                    </div>
                    <div class="mt-2 text-xs text-muted-foreground">
                      {{ t('ledger.supplier.supplier') }}
                    </div>
                  </div>

                  <div class="w-full bg-card border rounded-xl overflow-hidden mt-4">
                    <div class="flex flex-col divide-y divide-border">
                      <div class="flex items-center px-5 py-2">
                        <div class="flex-1 text-base text-muted-foreground">{{ t('general.receivable') }}</div>
                        <div class="text-base font-medium text-primary">
                          {{ formatAmount(statement.total_credit) }}
                        </div>
                      </div>
                      <div class="flex items-center px-5 py-2 mt-1">
                        <div class="flex-1 text-base text-muted-foreground">{{ t('general.payable') }}</div>
                        <div class="text-base font-medium text-primary">
                          {{ formatAmount(statement.total_debit) }}
                        </div>
                      </div>
                      <div class="flex items-center px-5 py-2">
                        <div class="flex-1 text-base text-muted-foreground">{{ t('general.balance') }}</div>
                        <div class="text-base font-medium" :class="statement.balance_nature === 'cr' ? 'text-green-600' : 'text-primary'">
                          {{ statement.balance }}
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="lg:col-span-2 bg-card rounded-xl shadow-sm border p-4">
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <div class="text-sm text-muted-foreground">{{ t('general.name') }}</div>
                      <div class="font-medium">{{ supplierData.name }}</div>
                    </div>
                    <div>
                      <div class="text-sm text-muted-foreground">{{ t('ledger.contact_person') }}</div>
                      <div class="font-medium">{{ supplierData.contact_person }}</div>
                    </div>
                    <div>
                      <div class="text-sm text-muted-foreground">{{ t('general.phone') }}</div>
                      <div class="font-medium">{{ supplierData.phone_no }}</div>
                    </div>
                    <div>
                      <div class="text-sm text-muted-foreground">{{ t('general.email') }}</div>
                      <div class="font-medium">{{ supplierData.email }}</div>
                    </div>
                    <div>
                      <div class="text-sm text-muted-foreground">{{ t('admin.currency.currency') }}</div>
                      <div class="font-medium">{{ supplierData.currency?.name || '' }}</div>
                    </div>
                    <div>
                      <div class="text-sm text-muted-foreground">{{ t('admin.branch.branch') }}</div>
                      <div class="font-medium">{{ supplierData.branch?.name || '' }}</div>
                    </div>
                    <div class="md:col-span-2">
                      <div class="text-sm text-muted-foreground">{{ t('general.address') }}</div>
                      <div class="font-medium">{{ supplierData.address }}</div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="bg-card rounded-xl shadow-sm border p-4">
                <div class="flex flex-wrap items-center gap-3 mb-4">
                  <button
                    type="button"
                    class="px-3 py-1.5 text-sm rounded-full"
                    :class="activeTxnTab === 'purchases' ? 'bg-primary text-white' : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                    @click="activeTxnTab = 'purchases'"
                  >
                    {{ t('purchase.purchases') }}
                  </button>
                  <button
                    type="button"
                    class="px-3 py-1.5 text-sm rounded-full"
                    :class="activeTxnTab === 'receipts' ? 'bg-primary text-white' : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                    @click="activeTxnTab = 'receipts'"
                  >
                    {{ t('receipt.receipts') }}
                  </button>
                  <button
                    type="button"
                    class="px-3 py-1.5 text-sm rounded-full"
                    :class="activeTxnTab === 'payments' ? 'bg-primary text-white' : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                    @click="activeTxnTab = 'payments'"
                  >
                    {{ t('payment.payments') }}
                  </button>
                </div>

                <LedgerListTable
                  v-if="activeTxnTab === 'purchases'"
                  :title="t('purchase.purchases')"
                  :rows="purchasesTableRows"
                  :columns="purchasesColumns"
                  :empty-message="t('general.no_data_found')"
                  :export-url="exportUrl('purchases')"
                  :export-label="t('report.export_excel')"
                  :row-number-label="t('report.columns.no')"
                  default-sort-key="date"
                  default-sort-direction="desc"
                />

                <LedgerListTable
                  v-else-if="activeTxnTab === 'receipts'"
                  :title="t('receipt.receipts')"
                  :rows="receiptTableRows"
                  :columns="movementColumns"
                  :empty-message="t('general.no_data_found')"
                  :export-url="exportUrl('receipts')"
                  :export-label="t('report.export_excel')"
                  :row-number-label="t('report.columns.no')"
                  default-sort-key="date"
                  default-sort-direction="desc"
                >
                  <template #cell-actions="{ row }">
                    <Button variant="outline" size="sm" class="gap-2" @click="openPrint(row.printRoute, row.id)">
                      <Printer class="h-4 w-4" />
                      {{ t('datatable.print') }}
                    </Button>
                  </template>
                </LedgerListTable>

                <LedgerListTable
                  v-else
                  :title="t('payment.payments')"
                  :rows="paymentTableRows"
                  :columns="movementColumns"
                  :empty-message="t('general.no_data_found')"
                  :export-url="exportUrl('payments')"
                  :export-label="t('report.export_excel')"
                  :row-number-label="t('report.columns.no')"
                  default-sort-key="date"
                  default-sort-direction="desc"
                >
                  <template #cell-actions="{ row }">
                    <Button variant="outline" size="sm" class="gap-2" @click="openPrint(row.printRoute, row.id)">
                      <Printer class="h-4 w-4" />
                      {{ t('datatable.print') }}
                    </Button>
                  </template>
                </LedgerListTable>
              </div>
            </div>

            <div v-else class="bg-card rounded-xl shadow-sm border p-4">
              <div class="text-sm font-semibold mb-3">
                {{ t('item.opening') }}
              </div>
              <table class="min-w-full text-sm">
                <thead>
                  <tr class="border-b border-border text-left rtl:text-right text-muted-foreground">
                    <th class="py-2 pr-4">{{ t('admin.currency.currency') }}</th>
                    <th class="py-2 pr-4">{{ t('general.amount') }}</th>
                    <th class="py-2 pr-4">{{ t('general.rate') }}</th>
                    <th class="py-2 pr-4">{{ t('general.type') }}</th>
                    <th class="py-2 pr-4">{{ t('general.date') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!openingRows.length">
                    <td colspan="5" class="py-4 text-center text-muted-foreground">
                      {{ t('general.no_data_found') }}
                    </td>
                  </tr>
                  <tr v-for="openingItem in openingRows" :key="openingItem.id" class="border-b border-border last:border-b-0">
                    <td class="py-2 pr-4">{{ openingItem.currency?.name || '' }}</td>
                    <td class="py-2 pr-4">{{ formatAmount(openingItem.amount) }}</td>
                    <td class="py-2 pr-4">{{ openingItem.rate }}</td>
                    <td class="py-2 pr-4 capitalize">{{ openingItem.type }}</td>
                    <td class="py-2 pr-4">{{ openingItem.date }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="flex justify-end mt-4">
            <Button variant="outline" @click="closeDialog">
              {{ t('general.close') }}
            </Button>
          </div>
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>
