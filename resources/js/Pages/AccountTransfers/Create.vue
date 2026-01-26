<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { ref, watch, computed } from 'vue'
import { useLazyProps } from '@/composables/useLazyProps'
import NextInput from '@/Components/next/NextInput.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import NextTextarea from '@/Components/next/NextTextarea.vue'
import NextDate from '@/Components/next/NextDatePicker.vue'
import SubmitButtons from '@/Components/SubmitButtons.vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/Components/ui/toast/use-toast'

const { t } = useI18n()
const { toast } = useToast()

const page = usePage()
const accounts = computed(() => page.props.accounts?.data || [])
const currencies = computed(() => page.props.currencies?.data || [])
console.log('this is currencies', page.props);

useLazyProps(page.props, ['accounts'])

const form = useForm({
  number: page.props.latestNumber ?? '',
  date: '',
  from_account_id: '',
  selected_from_account: null,
  to_account_id: '',
  selected_to_account: null,
  amount: '',
  currency_id: '',
  selected_currency: null,
  rate: '',
  remark: '',
})

const submitAction = ref(null)
const createLoading = computed(() => form.processing && submitAction.value === 'create')
const createAndNewLoading = computed(() => form.processing && submitAction.value === 'create_and_new')

const submitActionHandler = (createAndNew = false) => {
  submitAction.value = createAndNew ? 'create_and_new' : 'create'
  submit(createAndNew)
}

const sameAccountError = computed(() => {
  return form.from_account_id && form.to_account_id && form.from_account_id === form.to_account_id
})

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

function submit(createAndNew = false) {
  if (sameAccountError.value) {
    toast({
      title: t('general.error'),
      description: t('general.accounts_cannot_be_same'),
      variant: 'destructive',
    })
    return
  }
  const payload = createAndNew ? { create_and_new: true } : {}
  form.transform(data => ({ ...data, ...payload })).post('/account-transfers', {
    onSuccess: () => {
      const latest = Number(form.number || 0)
      if (createAndNew) {
        form.reset('date', 'amount', 'remark')
        form.number = String((isNaN(latest) ? 0 : latest) + 1)
      }
      
      toast({
        title: t('general.success'),
        description: t('general.create_success', { name: t('general.account_transfer') }),
        variant: 'success',
        class:'bg-green-600 text-white',
      })
    }
  })
}
</script>

<template>
  <AppLayout :title="t('general.create', { name: t('general.account_transfer') })">
    <form @submit.prevent="submitActionHandler">
      <div class="mb-5 rounded-xl border p-4 shadow-sm relative">
        <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
          {{ t('general.create', { name: t('general.account_transfer') }) }}
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
          <NextInput placeholder="Number" :error="form.errors?.number" v-model="form.number" type="text" :label="t('general.number')" />
          <NextDate v-model="form.date" :current-date="true" :error="form.errors?.date" :placeholder="t('general.enter', { text: t('general.date') })" :label="t('general.date')" />
          <NextInput placeholder="Amount" :error="form.errors?.amount" type="number" step="any" v-model="form.amount" :label="t('general.amount')" />

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

          <NextSelect
            :options="accounts"
            v-model="form.selected_from_account"
            @update:modelValue="(v) => handleSelectChange('from_account_id', v.id)"
            label-key="name"
            value-key="id"
            :reduce="acc => acc"
            :floating-text="t('general.from_account')"
            :error="form.errors?.from_account_id"
            :searchable="true"
            resource-type="accounts"
            :search-fields="['name', 'number', 'slug']"
          />
          <NextSelect
            :options="accounts"
            v-model="form.selected_to_account"
            @update:modelValue="(v) => handleSelectChange('to_account_id', v.id)"
            label-key="name"
            value-key="id"
            :reduce="acc => acc"
            :floating-text="t('general.to_account')"
            :error="form.errors?.to_account_id"
            :searchable="true"
            resource-type="accounts"
            :search-fields="['name', 'number', 'slug']"
          />
          <div v-if="sameAccountError" class="md:col-span-3 text-sm text-red-600">
            {{ t('general.accounts_cannot_be_same') }}
          </div>

          <div class="md:col-span-3">
            <NextTextarea placeholder="Remark" :error="form.errors?.remark" v-model="form.remark" :label="t('general.remark')" />
          </div>
        </div>
      </div>

      <SubmitButtons
        :create-label="t('general.create')"
        :create-and-new-label="t('general.create_and_new')"
        :cancel-label="t('general.cancel')"
        :creating-label="t('general.creating', { name: t('general.account_transfer') })"
        :create-loading="createLoading"
        :create-and-new-loading="createAndNewLoading"
        :disabled="sameAccountError"
        @create-and-new="submitActionHandler(true)"
        @cancel="() => $inertia.visit('/account-transfers')"
      />
    </form>
  </AppLayout>
</template>

