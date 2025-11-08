<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { ref, onMounted, watch } from 'vue'
import axios from 'axios'
import NextInput from '@/Components/next/NextInput.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import NextTextarea from '@/Components/next/NextTextarea.vue'
import NextDate from '@/Components/next/NextDatePicker.vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/Components/ui/toast/use-toast'
const { t } = useI18n()
const { toast } = useToast()
const page = usePage()
const ledgers = page.props.ledgers?.data || []
const accounts = page.props.accounts?.data || []
const currencies = page.props.currencies?.data || []
const form = useForm({
  id: '',
  number: '',
  date: '',
  ledger_id: '',
  selected_ledger: null,
  amount: '',
  bank_account_id: '',
  selected_bank_account: null,
  currency_id: '',
  selected_currency: null,
  rate: '',
  cheque_no: '',
  narration: '',
})

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
  form.amount = r.amount
  form.currency_id = r.currency_id
  form.rate = r.rate
  form.cheque_no = r.cheque_no
  form.narration = r.narration
  form.selected_ledger = ledgers.find(l => l.id === r.ledger_id) || r.ledger || null
  form.selected_currency = currencies.find(c => c.id === r.currency_id) || null
  const bankId = r?.bank_transaction?.account_id || r.bank_transaction_id
  form.bank_account_id = bankId
  form.selected_bank_account = r.bank_account
  form.bank_account_id = r.bank_account_id

})
function handleSelectChange(field, value) {
  form[field] = value
  if (field === 'currency_id') {
    const chosen = currencies.find(c => c.id === value)
    if (chosen) form.rate = chosen.exchange_rate
  }
}

function oldBalanceText() {
  const s = form.selected_ledger?.statement
  if (!s) return ''
  return s.balance > 0
    ? `${s.balance} ${String(s.balance_nature || '').toUpperCase()}`
    : `${s.balance}`
}

function submit() {
  form.put(`/receipts/${form.id}`, {
    onSuccess: () => {
      // done
      toast({
        title: t('general.success'),
        description: t('general.update_success', { name: t('receipt.receipt') }),
        variant: 'success',
        class:'bg-green-600 text-white',
      })
    },
  })
}
</script>

<template>
  <AppLayout :title="t('general.edit', { name: 'Receipt' })">
    <form @submit.prevent="submit()">
      <div class="mb-5 rounded-xl border p-4 shadow-sm relative">
        <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
          {{ t('general.edit', { name: 'Receipt' }) }}
        </div>
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
          <NextInput placeholder="Rate" :error="form.errors?.rate" type="number" step="any" v-model="form.rate" :label="t('general.rate')" />
          <NextInput placeholder="Amount" :error="form.errors?.amount" type="number" step="any" v-model="form.amount" :label="t('general.amount')" />
          <NextSelect
            :options="accounts"
            v-model="form.selected_bank_account"
            @update:modelValue="(v) => handleSelectChange('bank_account_id', v.id)"
            label-key="name"
            value-key="id"
            :reduce="acc => acc"
            :floating-text="'Bank Account'"
            :error="form.errors?.bank_account_id"
            :searchable="true"
            resource-type="accounts"
            :search-fields="['name', 'number', 'slug']"
          />
          <NextInput placeholder="Cheque No" :error="form.errors?.cheque_no" v-model="form.cheque_no" :label="'Cheque No'" />
          <div class="md:col-span-3 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2">
              <NextTextarea placeholder="Narration" :error="form.errors?.narration" v-model="form.narration" :label="'Narration'" />
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
        <button type="submit" class="btn btn-primary px-4 py-2 rounded-md bg-primary text-white">{{ t('general.update') }}</button>
        <button type="button" class="btn px-4 py-2 rounded-md border" @click="() => $inertia.visit('/receipts')">{{ t('general.cancel') }}</button>
      </div>
    </form>
  </AppLayout>
</template>


