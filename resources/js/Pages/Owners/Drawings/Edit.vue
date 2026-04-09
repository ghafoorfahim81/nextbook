<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { computed, ref, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { toast } from 'vue-sonner'
import NextInput from '@/Components/next/NextInput.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import NextTextarea from '@/Components/next/NextTextarea.vue'
import NextDate from '@/Components/next/NextDatePicker.vue'
import SubmitButtons from '@/Components/SubmitButtons.vue'

const { t } = useI18n()

const props = defineProps({
  drawing: Object,
  owners: Object,
  bankAccounts: Object,
  currencies: Object,
  homeCurrency: Object,
})

const currentDrawing = computed(() => props.drawing?.data ?? props.drawing ?? {})
const owners = computed(() => props.owners?.data ?? props.owners ?? [])
const bankAccounts = computed(() => props.bankAccounts?.data ?? props.bankAccounts ?? [])
const currencies = computed(() => props.currencies?.data ?? props.currencies ?? [])
const homeCurrency = computed(() => props.homeCurrency?.data ?? props.homeCurrency ?? null)
const initialBankAccount = computed(() => {
  return bankAccounts.value.find((account) => account.id === currentDrawing.value?.bank_account_id)
    || currentDrawing.value?.bank_account
    || null
})

const todayValue = () => new Date().toLocaleDateString('en-CA')

const form = useForm({
  owner_id: currentDrawing.value?.owner_id || '',
  bank_account_id: currentDrawing.value?.bank_account_id || '',
  currency_id: currentDrawing.value?.currency_id || '',
  rate: currentDrawing.value?.rate || 1,
  amount: currentDrawing.value?.amount || '',
  date: currentDrawing.value?.date || todayValue(),
  narration: currentDrawing.value?.narration || '',
  selectedOwner: currentDrawing.value?.owner || null,
  selected_bank_account: initialBankAccount.value,
  selected_currency: currentDrawing.value?.currency || null,
})

const submitAction = ref(null)
const updateLoading = computed(() => form.processing && submitAction.value === 'update')

const fallbackCurrency = () => {
  return (
    homeCurrency.value ||
    currencies.value.find((currency) => currency.is_base_currency) ||
    currencies.value[0] ||
    null
  )
}

const syncCurrencyFromBankAccount = (bankAccount) => {
  const openingCurrency = bankAccount?.opening?.currency || fallbackCurrency()
  form.selected_currency = openingCurrency || null
  form.currency_id = openingCurrency?.id || ''
  form.rate = Number(bankAccount?.opening?.rate ?? openingCurrency?.exchange_rate ?? 1)
}

const syncOwner = (owner) => {
  form.selectedOwner = owner || null
  form.owner_id = owner?.id || ''
}

const syncBankAccount = (bankAccount) => {
  form.selected_bank_account = bankAccount || null
  form.bank_account_id = bankAccount?.id || ''
  syncCurrencyFromBankAccount(bankAccount)
}

watch(
  [bankAccounts, currencies, homeCurrency],
  () => {
    if (!form.selected_bank_account) {
      syncCurrencyFromBankAccount(null)
    }
  },
  { immediate: true },
)

if (form.selectedOwner) {
  syncOwner(form.selectedOwner)
}

if (form.selected_bank_account) {
  syncBankAccount(form.selected_bank_account)
}

const selectedDrawingAccount = computed(() => form.selectedOwner?.drawing_account || null)
const currencyDisplay = computed(() => {
  const currency = form.selected_currency
  if (!currency) return '-'
  return [currency.symbol, currency.code].filter(Boolean).join(' ') || currency.code || '-'
})

const amountDisplay = computed(() => Number(form.amount || 0).toLocaleString(undefined, { maximumFractionDigits: 2 }))

const handleSubmit = () => {
  submitAction.value = 'update'

  form
    .transform((data) => ({
      owner_id: data.owner_id,
      bank_account_id: data.bank_account_id,
      currency_id: data.currency_id,
      rate: data.rate,
      amount: data.amount,
      date: data.date,
      narration: data.narration,
    }))
    .patch(route('drawings.update', currentDrawing.value.id), {
      preserveScroll: true,
      onSuccess: () => {
        toast.success(t('general.success'), {
          description: t('general.update_success', { name: t('sidebar.owners.drawing') }),
          class: 'bg-green-600 text-white',
        })
      },
      onError: () => {
        toast.error(t('general.error'), {
          description: t('general.update_error', { name: t('sidebar.owners.drawing') }),
        })
      },
    })
}

function handleSelectChange(field, value) {
  form[field] = value
  if (field === 'currency_id') { 
    const chosen = (currencies.value || []).find(c => c.id === value)
    form.currency_id = value.id
    if (chosen) form.rate = chosen.exchange_rate
  }
}
</script>

<template>
  <AppLayout :title="t('general.edit', { name: t('sidebar.owners.drawing') })">
    <form @submit.prevent="handleSubmit">
      <div class="mb-5 rounded-xl border border-primary p-4 shadow-sm relative bg-card">
        <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-violet-500">
          {{ t('general.edit', { name: t('sidebar.owners.drawing') }) }}
        </div>

        <div class="grid grid-cols-1 gap-4 pt-3 md:grid-cols-2 xl:grid-cols-3">
          <NextSelect
            :options="owners"
            v-model="form.selectedOwner"
            @update:modelValue="syncOwner"
            label-key="name"
            value-key="id"
            :reduce="(owner) => owner"
            :floating-text="t('owner.owner')"
            :error="form.errors?.owner_id"
            :searchable="true"
            resource-type="owners"
            :search-fields="['name', 'father_name', 'nic']"
          />

          <NextSelect
            :options="bankAccounts"
            v-model="form.selected_bank_account"
            @update:modelValue="syncBankAccount"
            label-key="name"
            value-key="id"
            :reduce="(account) => account"
            :floating-text="t('general.bank_account')"
            :error="form.errors?.bank_account_id"
            :searchable="true"
            resource-type="accounts"
            :search-fields="['name', 'number', 'slug']"
          />

          <NextInput
            :modelValue="selectedDrawingAccount?.name || ''"
            :label="t('owner.drawing_account')"
            readonly
            :placeholder="t('owner.drawing_account')"
          />

          <div class="grid grid-cols-2 gap-2">
            <NextSelect
            :options="currencies"
            v-model="form.selected_currency"
            label-key="code"
            value-key="id"
            @update:modelValue="(value) => handleSelectChange('currency_id', value)"
            :reduce="currency => currency"
            :floating-text="t('admin.currency.currency')"
            :error="form.errors?.currency_id"
            :searchable="true"
            resource-type="currencies"
            :search-fields="['name', 'code', 'symbol']"
             />
            <NextInput placeholder="Rate" :disabled="form.selected_currency?.is_base_currency == true" :error="form.errors?.rate" type="number" step="any" v-model="form.rate" :label="t('general.rate')" />
            
          </div>

          <div class="space-y-1"> 
            <NextDate
              v-model="form.date"
              :error="form.errors?.date"
            />
          </div>

          <NextInput
            v-model="form.amount"
            type="number"
            step="any"
            :label="t('general.amount')"
            :error="form.errors?.amount"
          />

          <NextTextarea
            v-model="form.narration"
            :label="t('general.remarks')"
            :error="form.errors?.narration"
            rows="2"
            class="md:col-span-2 xl:col-span-3"
          />
        </div>
      </div>

      <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
        <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-violet-500">
          {{ t('general.transaction') || 'Transaction' }}
        </div>
        <div class="grid gap-4 md:grid-cols-2">
          <div class="rounded-xl border border-emerald-500/25 bg-emerald-500/10 p-4">
            <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-emerald-600 dark:text-emerald-300">
              CR
            </div>
            <div class="text-sm font-medium text-foreground">
              {{ form.selected_bank_account?.name || t('general.bank_account') }}
            </div>
            <div class="mt-1 text-xs text-muted-foreground">
              {{ currencyDisplay }} {{ amountDisplay }}
            </div>
          </div>

          <div class="rounded-xl border border-rose-500/25 bg-rose-500/10 p-4">
            <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-rose-600 dark:text-rose-300">
              DR
            </div>
            <div class="text-sm font-medium text-foreground">
              {{ selectedDrawingAccount?.name || t('owner.drawing_account') }}
            </div>
            <div class="mt-1 text-xs text-muted-foreground">
              {{ currencyDisplay }} {{ amountDisplay }}
            </div>
          </div>
        </div>
      </div>

      <SubmitButtons
        :create-label="t('general.update')"
        :create-and-new-label="t('general.update')"
        :cancel-label="t('general.cancel')"
        :creating-label="t('general.updating', { name: t('sidebar.owners.drawing') })"
        :create-loading="updateLoading"
        :create-and-new-loading="false"
        :show-create-and-new="false"
        @cancel="() => router.visit(route('drawings.index'))"
      />
    </form>
  </AppLayout>
</template>
