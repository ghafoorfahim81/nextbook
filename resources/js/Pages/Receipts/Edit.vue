<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { ref, onMounted, watch, computed } from 'vue'
import axios from 'axios'
import { useLazyProps } from '@/composables/useLazyProps'
import NextInput from '@/Components/next/NextInput.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import NextTextarea from '@/Components/next/NextTextarea.vue'
import NextDate from '@/Components/next/NextDatePicker.vue'
import BillAllocationDialog from '@/Components/next/BillAllocationDialog.vue'
import ModuleHelpButton from '@/Components/ModuleHelpButton.vue'
import { Spinner } from '@/Components/ui/spinner'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/Components/ui/toast/use-toast'
const { t } = useI18n()
const { toast } = useToast()
const page = usePage()

const ledgers = computed(() => page.props.ledgers?.data || [])
const accounts = computed(() => page.props.accounts?.data || [])
const currencies = computed(() => page.props.currencies?.data || [])
const homeCurrency = computed(() => page.props.homeCurrency?.data || null)
const paymentModes = computed(() => page.props.paymentModes || [])
useLazyProps(page.props, ['ledgers', 'accounts'])
const billLoading = ref(false)
const showBillDialog = ref(false)
const billOptions = ref([])
const initialized = ref(false)
const form = useForm({
  id: '',
  number: '',
  date: '',
  ledger_id: '',
  selected_ledger: null,
  payment_mode: 'on_account',
  amount: '',
  bank_account_id: '',
  selected_bank_account: null,
  currency_id: '',
  selected_currency: null,
  rate: '',
  cheque_no: '',
  narration: '',
  allocations: [],
})
const submitAction = ref('update')
const pendingPrintWindow = ref(null)

function parseIdFromUrl() {
  const path = page.url.split('?')[0]
  const parts = path.split('/').filter(Boolean)
  // /receipts/{id}/edit
  const idx = parts.indexOf('receipts')
  return idx >= 0 && parts[idx + 1] ? parts[idx + 1] : ''
}
onMounted(async () => {
  const id = parseIdFromUrl()
  if (!id) return
  const { data } = await axios.get(`/receipts/${id}`)
  const r = data?.data || {}
  form.id = r.id
  form.number = r.number
  form.date = r.date
  form.ledger_id = r.ledger_id
  form.payment_mode = r.payment_mode || 'on_account'
  form.amount = r.amount
  form.currency_id = r.currency_id
  form.rate = r.rate
  form.cheque_no = r.cheque_no
  form.narration = r.narration
  form.allocations = (r.sale_receives || []).map((allocation) => ({
    bill_id: allocation.sale_id,
    amount: allocation.amount,
  }))
  form.selected_ledger = ledgers.value.find(l => l.id === r.ledger_id) || r.ledger || null
  form.selected_currency = currencies.value.find(c => c.id === r.currency_id) || null
  const bankId = r?.bank_transaction?.account_id || r.bank_transaction_id
  form.bank_account_id = bankId
  form.selected_bank_account = r.bank_account
  form.bank_account_id = r.bank_account_id
  oldBalanceText();
  initialized.value = true
})

watch([ledgers, currencies], () => {
  if (form.ledger_id && !form.selected_ledger) {
    form.selected_ledger = ledgers.value.find(l => l.id === form.ledger_id) || form.selected_ledger
  }
  if (form.currency_id && !form.selected_currency) {
    form.selected_currency = currencies.value.find(c => c.id === form.currency_id) || form.selected_currency
  }
})
function handleSelectChange(field, value) {
  form[field] = value
  if (field === 'currency_id') {
    const chosen = currencies.value.find(c => c.id === value)
    if (chosen) form.rate = chosen.exchange_rate
  }
}

const loadBills = async () => {
  if (!form.ledger_id) {
    billOptions.value = []
    return
  }

  billLoading.value = true
  try {
    const { data } = await axios.get('/sales/open-bills', {
      params: {
        ledger_id: form.ledger_id,
        exclude_receipt_id: form.id,
      },
    })
    billOptions.value = data?.data || []
  } finally {
    billLoading.value = false
  }
}

const openBillDialog = async () => {
  if (form.payment_mode !== 'bill_by_bill' || !form.ledger_id) {
    return
  }

  await loadBills()
  showBillDialog.value = true
}

const handleBillAllocationsSave = (allocations) => {
  form.allocations = allocations
}

watch([() => form.ledger_id, () => form.payment_mode], async ([ledgerId, paymentMode], [prevLedgerId, prevPaymentMode]) => {
  if (!initialized.value) {
    return
  }

  if (paymentMode !== 'bill_by_bill') {
    form.allocations = []
    showBillDialog.value = false
    return
  }

  if (ledgerId && (ledgerId !== prevLedgerId || paymentMode !== prevPaymentMode)) {
    await openBillDialog()
  }
})

function oldBalanceText() {
  const s = form.selected_ledger?.statement
  if (!s) return ''
  return s.balance > 0
    ? `${s.balance} ${String(s.balance_nature || '').toUpperCase()}`
    : `${s.balance}`
}

function finalizePrint(page) {
  const printUrl = page?.props?.flash?.print_url

  if (!printUrl) {
    if (pendingPrintWindow.value && !pendingPrintWindow.value.closed) {
      pendingPrintWindow.value.close()
    }
    pendingPrintWindow.value = null
    return
  }

  if (pendingPrintWindow.value && !pendingPrintWindow.value.closed) {
    pendingPrintWindow.value.location = printUrl
    pendingPrintWindow.value.focus?.()
  } else {
    window.open(printUrl, '_blank')
  }

  pendingPrintWindow.value = null
}

function cleanupPrintWindow() {
  if (pendingPrintWindow.value && !pendingPrintWindow.value.closed) {
    pendingPrintWindow.value.close()
  }

  pendingPrintWindow.value = null
}

function submit(action = 'update') {
  submitAction.value = action

  if (action === 'save_and_print') {
    pendingPrintWindow.value = window.open('about:blank', '_blank')
  }

  form.transform((data) => ({
    ...data,
    save_and_print: action === 'save_and_print',
  })).put(`/receipts/${form.id}`, {
    onSuccess: (page) => {
      if (action === 'save_and_print') {
        finalizePrint(page)
      }
      // done
      toast({
        title: t('general.success'),
        description: t('general.update_success', { name: t('receipt.receipt') }),
        variant: 'success',
        class:'bg-green-600 text-white',
      })
    },
    onError: () => {
      cleanupPrintWindow()
    },
  })
}
</script>

<template>
  <AppLayout :title="t('general.edit', { name: t('receipt.receipt') })">
    <form @submit.prevent="submit('update')">
      <div class="mb-5 rounded-xl border p-4 shadow-sm border-primary relative">
        <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
          {{ t('general.edit', { name: t('receipt.receipt') }) }}
        </div>
        <ModuleHelpButton module="receipt" />
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
          <NextSelect
            :options="ledgers"
            v-model="form.selected_ledger"
            @update:modelValue="(v) => handleSelectChange('ledger_id', v.id)"
            label-key="name"
            value-key="id"
            :reduce="ledger => ledger"
            :floating-text="t('ledger.customer.customer')"
            :error="form.errors?.ledger_id"
            :searchable="true"
            resource-type="ledgers"
            :search-fields="['name', 'email', 'phone_no']"
          />
          <NextInput placeholder="Number" :error="form.errors?.number" v-model="form.number" type="text" :label="t('general.number')" />
          <NextDate v-model="form.date" :current-date="true" :error="form.errors?.date" :placeholder="t('general.enter', { text: t('general.date') })" :label="t('general.date')" />
          <NextSelect
            :options="currencies"
            v-model="form.selected_currency"
            label-key="code"
            value-key="id"
            @update:modelValue="(value) => handleSelectChange('currency_id', value.id)"
            :reduce="currency => currency"
            :floating-text="t('admin.currency.currency')"
            :error="form.errors?.currency_id"
            :searchable="true"
            resource-type="currencies"
            :search-fields="['name', 'code', 'symbol']"
          />
          <NextSelect
            :options="paymentModes"
            v-model="form.payment_mode"
            label-key="name"
            value-key="id"
            :reduce="mode => mode.id"
            :floating-text="t('general.payment_mode')"
            :searchable="false"
            :clearable="false"
            :error="form.errors?.payment_mode"
          />

          <NextInput placeholder="Rate" :error="form.errors?.rate" :disabled="form.selected_currency?.is_base_currency === true" type="number" step="any" v-model="form.rate" :label="t('general.rate')" />
          <NextInput placeholder="Amount" :error="form.errors?.amount" type="number" step="any" v-model="form.amount" :label="t('general.amount')" />
          <NextSelect
            :options="accounts"
            v-model="form.selected_bank_account"
            @update:modelValue="(v) => handleSelectChange('bank_account_id', v.id)"
            label-key="name"
            value-key="id"
            :reduce="acc => acc"
            :floating-text="t('general.add_to_account')"
            :error="form.errors?.bank_account_id"
            :searchable="true"
            resource-type="accounts"
            :search-fields="['name', 'number', 'slug']"
          />
          <div class="flex flex-col gap-2">
            <button
              v-if="form.payment_mode === 'bill_by_bill'"
              type="button"
              class="inline-flex items-center justify-center rounded-md border px-3 py-2 text-sm font-medium"
              @click="openBillDialog"
            >
              {{ t('general.allocate_bills') || 'Allocate bills' }}
            </button>
            <p v-if="form.allocations.length" class="text-xs text-muted-foreground">
              {{ form.allocations.length }} {{ t('general.bills_selected') || 'bills selected' }}
            </p>
          </div>
          <NextInput placeholder="Cheque No" :error="form.errors?.cheque_no" v-model="form.cheque_no" :label="t('general.cheque_no')" />
          <div class="md:col-span-3 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2">
              <NextTextarea :placeholder="t('general.enter', { text: t('general.narration') })" :error="form.errors?.narration" v-model="form.narration" :label="t('general.narration')" />
            </div>
            <div class="md:col-span-1">
              <div class="rounded-xl border p-4 w-full md:w-64 ml-auto">
                <div class="text-sm font-semibold mb-2 text-violet-500">{{ t('general.old_balance') }}</div>
                <div class="text-lg font-bold">{{ oldBalanceText() }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-4 flex gap-2">
        <button type="submit" class="btn btn-primary px-4 py-2 rounded-md bg-primary text-white" :disabled="form.processing">
          <Spinner v-if="form.processing && submitAction === 'update'" class="mr-2 h-4 w-4" />
          {{ t('general.update') }}
        </button>
        <button type="button" class="btn btn-primary px-4 py-2 rounded-md bg-primary text-white" :disabled="form.processing" @click="submit('save_and_print')">
          <Spinner v-if="form.processing && submitAction === 'save_and_print'" class="mr-2 h-4 w-4" />
          {{ t('general.save_and_print') }}
        </button>
        <button type="button" class="btn px-4 py-2 rounded-md border" @click="() => $inertia.visit('/receipts')">{{ t('general.cancel') }}</button>
      </div>
      <BillAllocationDialog
        :open="showBillDialog"
        :title="t('general.allocate_bills') || 'Allocate bills'"
        bill-label="Sale"
        :amount="Number(form.amount || 0)"
        :bills="billOptions"
        :loading="billLoading"
        :allocations="form.allocations"
        @update:open="showBillDialog = $event"
        @update:allocations="(value) => form.allocations = value"
        @save="handleBillAllocationsSave"
      />
    </form>
  </AppLayout>
</template>
