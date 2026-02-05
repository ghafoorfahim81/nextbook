<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { useForm, usePage, Link } from '@inertiajs/vue3'
import { ref, watch, computed } from 'vue'
import { useLazyProps } from '@/composables/useLazyProps'
import NextInput from '@/Components/next/NextInput.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import NextTextarea from '@/Components/next/NextTextarea.vue'
import NextDate from '@/Components/next/NextDatePicker.vue'
import SubmitButtons from '@/Components/SubmitButtons.vue'
import { useI18n } from 'vue-i18n'
const { t } = useI18n()
import { useToast } from '@/Components/ui/toast/use-toast'
const page = usePage()
const ledgers = computed(() => page.props.ledgers?.data || [])
const accounts = computed(() => page.props.accounts?.data || [])
const currencies = computed(() => page.props.currencies?.data || [])

useLazyProps(page.props, ['ledgers', 'accounts'])
const { toast } = useToast()
const form = useForm({
  number: page.props.latestNumber ?? '',
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

const submitAction = ref(null)
const createLoading = computed(() => form.processing && submitAction.value === 'create')
const createAndNewLoading = computed(() => form.processing && submitAction.value === 'create_and_new')

const submitActionHandler = (createAndNew = false) => {
  const isCreateAndNew = createAndNew === true
  submitAction.value = isCreateAndNew ? 'create_and_new' : 'create'
  submit(isCreateAndNew)
}

// default currency
watch(currencies, (list) => {
  if (list && list.length && !form.currency_id) {
    const base = list.find(c => c.is_base_currency)
    if (base) {
      form.selected_currency = base
      form.currency_id = base.id
      form.rate = base.exchange_rate
    }
  }
}, { immediate: true })



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

function submit(createAndNew = false) {
  const payload = createAndNew ? { create_and_new: true } : {}
  form.transform(data => ({ ...data, ...payload })).post('/receipts', {
    onSuccess: () => {
      if (createAndNew) {
        const latest = Number(form.number || 0)
        form.reset('date', 'amount', 'cheque_no', 'narration')
        form.number = String((isNaN(latest) ? 0 : latest) + 1)

      }
      toast({
        title: t('general.success'),
        description: t('general.create_success', { name: t('receipt.receipt') }),
        variant: 'success',
        class:'bg-green-600 text-white',
      })
    }

  })
}
</script>

<template>
  <AppLayout :title="t('general.create', { name: t('receipt.receipt') })"  >
    <form @submit.prevent="submitActionHandler(false)">
      <div class="mb-5 rounded-xl border p-4 shadow-sm border-primary relative">
        <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
          {{ t('general.create', { name: t('receipt.receipt') }) }}
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
      <SubmitButtons
        :create-label="t('general.create')"
        :create-and-new-label="t('general.create_and_new')"
        :cancel-label="t('general.cancel')"
        :creating-label="t('general.creating', { name: t('receipt.receipt') })"
        :create-loading="createLoading"
        :create-and-new-loading="createAndNewLoading"
        @create-and-new="submitActionHandler(true)"
        @cancel="() => $inertia.visit('/receipts')"
      />
    </form>
  </AppLayout>
</template>


