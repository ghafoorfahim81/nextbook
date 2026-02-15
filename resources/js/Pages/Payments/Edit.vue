<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { ref, onMounted, computed, watch } from 'vue'
import axios from 'axios'
import { useLazyProps } from '@/composables/useLazyProps'
import NextInput from '@/Components/next/NextInput.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import NextTextarea from '@/Components/next/NextTextarea.vue'
import NextDate from '@/Components/next/NextDatePicker.vue'
import ModuleHelpButton from '@/Components/ModuleHelpButton.vue'
import { useI18n } from 'vue-i18n'
const { t } = useI18n()

const page = usePage()
const ledgers = computed(() => page.props.ledgers?.data || [])
const accounts = computed(() => page.props.accounts?.data || [])
const currencies = computed(() => page.props.currencies?.data || [])

useLazyProps(page.props, ['ledgers', 'accounts'])

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
  description: '',
})

function parseIdFromUrl() {
  const path = page.url.split('?')[0]
  const parts = path.split('/').filter(Boolean)
  const idx = parts.indexOf('payments')
  return idx >= 0 && parts[idx + 1] ? parts[idx + 1] : ''
}

onMounted(async () => {
  const id = parseIdFromUrl()
  if (!id) return
  const { data } = await axios.get(`/payments/${id}`)
  const r = data?.data || {}
  form.id = r.id
  form.number = r.number
  form.date = r.date
  form.ledger_id = r.ledger_id
  form.amount = r.amount
  form.currency_id = r.currency_id
  form.rate = r.rate
  form.cheque_no = r.cheque_no
  form.description = r.description
  form.selected_ledger = ledgers.value.find(l => l.id === r.ledger_id) || r.ledger || null
  form.selected_currency = currencies.value.find(c => c.id === r.currency_id) || null
  const bankId = r?.transaction?.lines[0]?.account_id || r.transaction_id
  form.bank_account_id = bankId
  form.selected_bank_account = r.transaction?.lines[0]?.account || null
  form.bank_account_id = r.transaction?.lines[0]?.account_id || null
  oldBalanceText();
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

function oldBalanceText() {
  const s = form.selected_ledger?.statement
  if (!s) return ''
  return s.balance > 0
    ? `${s.balance} ${String(s.balance_nature || '').toUpperCase()}`
    : `${s.balance}`
}

function submit() {
  form.put(`/payments/${form.id}`)
}
</script>

<template>
  <AppLayout :title="t('general.edit', { name: t('payment.payment') })">
    <form @submit.prevent="submit()">
      <div class="mb-5 rounded-xl border p-4 shadow-sm border-primary relative">
        <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
          {{ t('general.edit', { name: t('payment.payment') }) }}
        </div>
        <ModuleHelpButton module="payments" />
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
          <NextSelect
            :options="ledgers"
            v-model="form.selected_ledger"
            @update:modelValue="(v) => handleSelectChange('ledger_id', v.id)"
            label-key="name"
            value-key="id"
            :reduce="ledger => ledger"
            :floating-text="t('ledger.supplier.supplier')"
            :error="form.errors?.ledger_id"
            :searchable="true"
            resource-type="ledgers"
            :search-fields="['name', 'email', 'phone_no']"
          />
          <NextInput :placeholder="t('general.enter', { text: t('general.number') })" :error="form.errors?.number" v-model="form.number" type="text" :label="t('general.number')" />
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
          <NextInput :placeholder="t('general.enter', { text: t('general.rate') })" :error="form.errors?.rate" :disabled="form.selected_currency?.is_base_currency === true" type="number" step="any" v-model="form.rate" :label="t('general.rate')" />
          <NextInput :placeholder="t('general.enter', { text: t('general.amount') })" :error="form.errors?.amount" type="number" step="any" v-model="form.amount" :label="t('general.amount')" />
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
          <NextInput :placeholder="t('general.enter', { text: t('general.cheque_no') })" :error="form.errors?.cheque_no" v-model="form.cheque_no" :label="t('general.cheque_no')" />
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
        <button type="submit" class="btn btn-primary px-4 py-2 rounded-md bg-primary text-white">{{ t('general.update') }}</button>
        <button type="button" class="btn px-4 py-2 rounded-md border" @click="() => $inertia.visit('/payments')">{{ t('general.cancel') }}</button>
      </div>
    </form>
  </AppLayout>
</template>


