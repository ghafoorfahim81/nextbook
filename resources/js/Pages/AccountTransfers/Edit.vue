<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { ref, watch, computed } from 'vue'
import { useLazyProps } from '@/composables/useLazyProps'
import NextInput from '@/Components/next/NextInput.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import NextTextarea from '@/Components/next/NextTextarea.vue'
import NextDate from '@/Components/next/NextDatePicker.vue'
import { useI18n } from 'vue-i18n'
import { toast } from 'vue-sonner'

const { t } = useI18n()

const page = usePage()
const accounts = computed(() => page.props.accounts?.data || [])
const currencies = computed(() => page.props.currencies?.data || [])

useLazyProps(page.props, ['accounts'])

const props = defineProps({
  data: Object,
})

const initial = props.data.data || {}
const form = useForm({
  number: initial.number ?? '',
  date: initial.date ?? '',
  from_account_id: initial.from_account?.id || '',
  selected_from_account: initial.from_account || null,
  to_account_id: initial.to_account?.id || '',
  selected_to_account: initial.to_account || null,
  amount: initial.amount || '',
  currency_id: initial?.currency_id || initial.currency?.id || '',
  selected_currency: initial?.currency || initial.currency || null,
  rate: initial?.rate || initial.rate || '',
  remark: initial.remark || '',
})

watch(() => props.data, (val) => {
  if (!val) return
  form.number = val.number || ''
  form.date = val.date || ''
  form.remark = val.remark || ''
  form.amount = val.amount || ''
  form.selected_from_account = val.from_account || null
  form.from_account_id = val.from_account?.id || ''
  form.selected_to_account = val.to_account || null
  form.to_account_id = val.to_account?.id || ''
  form.selected_currency = val.currency || null
  form.currency_id = form.selected_currency?.id || ''
  form.rate = val.rate || ''
}, { immediate: false })

function handleSelectChange(field, value) {
  form[field] = value
  if (field === 'currency_id') {
    const chosen = currencies.value.find(c => c.id === value)
    if (chosen) form.rate = chosen.exchange_rate
  }
}

const sameAccountError = computed(() => {
  return form.from_account_id && form.to_account_id && form.from_account_id === form.to_account_id
})

const handleSubmit = () => {
  form.put(`/account-transfers/${initial.id}`, {
    onSuccess: () => {
      toast.success(t('general.success'), {
        description: t('general.update_success', { name: t('general.account_transfer') }),
        class:'bg-green-600',
      })
    }
  })
}
</script>

<template>
  <AppLayout :title="t('general.edit', { name: t('general.account_transfer') })">
    <form @submit.prevent="handleSubmit">
      <div class="mb-5 rounded-xl border p-4 shadow-sm relative">
        <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
          {{ t('general.edit', { name: t('general.account_transfer') }) }}
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
          <NextInput placeholder="Number" :error="form.errors?.number" v-model="form.number" type="text" :label="t('general.number')" />
          <NextDate v-model="form.date" :current-date="false" :error="form.errors?.date" :placeholder="t('general.enter', { text: t('general.date') })" :label="t('general.date')" />
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
          <NextInput placeholder="Rate" :error="form.errors?.rate" :disabled="form.selected_currency.is_base_currency === true" type="number" step="any" v-model="form.rate" :label="t('general.rate')" />

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

      <div class="mt-4 flex gap-2">
        <button type="submit" class="btn btn-primary px-4 py-2 rounded-md bg-primary text-white" :disabled="sameAccountError || form.processing">
          {{ t('general.update') }}
        </button>
        <button type="button" class="btn px-4 py-2 rounded-md border" @click="() => $inertia.visit('/account-transfers')">{{ t('general.cancel') }}</button>
      </div>
    </form>
  </AppLayout>
</template>

