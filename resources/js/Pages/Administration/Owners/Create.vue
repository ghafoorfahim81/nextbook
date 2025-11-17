<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { useForm, usePage } from '@inertiajs/vue3'
import NextInput from '@/Components/next/NextInput.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import NextTextarea from '@/Components/next/NextTextarea.vue'
import { useI18n } from 'vue-i18n'
import { watch, computed } from 'vue'
const { t } = useI18n()

const page = usePage()
// Pull shared data from HandleInertiaRequests middleware
const allAccounts = computed(() => page.props.accounts?.data ?? page.props.accounts ?? [])
const currencies = computed(() => page.props.currencies?.data ?? page.props.currencies ?? [])

// Derive lists for selects
const capitalAccounts = computed(() => {
  const list = Array.isArray(allAccounts.value) ? allAccounts.value : []
  return list.filter(a => a?.slug === 'owners-capital' || (a?.name || '').toLowerCase().includes('capital'))
})
const drawingAccounts = computed(() => {
  const list = Array.isArray(allAccounts.value) ? allAccounts.value : []
  return list.filter(a => a?.slug === 'owners-drawing' || (a?.name || '').toLowerCase().includes('drawing'))
})

const form = useForm({
    capital_account_id: '',
    selected_capital_account: null,
    drawing_account_id: '',
    selected_drawing_account: null,
    name: '',
    account_id: '',
    selected_account: null,
    father_name: '',
    nic: '',
    email: '',
    address: '',
    phone_number: '',
    ownership_percentage: 100,
    is_active: true,
    amount: null,
    currency_id: '',
    selected_currency: null,
    rate: 1,
})

watch(currencies, (list) => {
    if (Array.isArray(list) && !form.currency_id) {
        const baseCurrency = list.find(c => c.is_base_currency);
        if (baseCurrency) {
            form.selected_currency = baseCurrency;
            form.rate = baseCurrency.exchange_rate;
            form.currency_id = baseCurrency.id;
        }
    }
}, { immediate: true });


function handleSelectChange(field, value) {
  form[field] = value
  if (field === 'currency_id') {
    const chosen = (currencies.value || []).find(c => c.id === value)
    if (chosen) form.rate = chosen.exchange_rate
  }
}

function submit(createAndNew = false) {
  const payload = createAndNew ? { create_and_new: true } : {}
  form.transform(data => ({ ...data, ...payload })).post('/owners')
}
</script>

<template>
    <AppLayout :title="t('general.create', { name: 'owner' })">
    <form @submit.prevent="submit()">
      <div class="mb-5 rounded-xl border p-4 shadow-sm relative">
        <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
          {{ t('general.create', { name: 'owner' }) }}
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
          <NextInput :label="t('general.name')" v-model="form.name" :error="form.errors?.name" />
          <NextInput :label="t('owner.father_name')" v-model="form.father_name" :error="form.errors?.father_name" />
          <NextInput label="NIC" v-model="form.nic" :error="form.errors?.nic" />
          <NextInput :label="t('owner.email')" v-model="form.email" type="email" :error="form.errors?.email"/>
          <NextInput :label="t('owner.phone_number')" v-model="form.phone_number" type="text" :error="form.errors?.phone_number"/>
          <NextInput :label="t('general.address')" v-model="form.address" type="text" :error="form.errors?.address"/>
          <NextInput :label="t('owner.ownership_percentage')" v-model="form.ownership_percentage" type="number" :error="form.errors?.ownership_percentage"/>
          <NextInput label="Amount" v-model="form.amount" type="number" :error="form.errors?.amount"/>
          <NextSelect
            :options="capitalAccounts"
            label-key="name"
            value-key="id"
            :reduce="capitalAccount => capitalAccount"
            v-model="form.selected_capital_account"
            @update:modelValue="(value) => handleSelectChange('capital_account_id', value?.id)"
            :floating-text="t('owner.capital_account')"
            :error="form.errors?.capital_account_id"
            :searchable="true"
            resource-type="accounts"
            :search-fields="['name']"
          />
           <NextSelect
            :options="drawingAccounts"
            label-key="name"
            value-key="id"
            :reduce="drawingAccount => drawingAccount"
            v-model="form.selected_drawing_account"
            @update:modelValue="(value) => handleSelectChange('drawing_account_id', value?.id)"
            :floating-text="t('owner.drawing_account')"
            :error="form.errors?.drawing_account_id"
            :searchable="true"
            resource-type="accounts"
            :search-fields="['name', 'number', 'slug']"
          />
          <NextSelect
            :options="allAccounts"
            label-key="name"
            value-key="id"
            :reduce="account => account"
            v-model="form.selected_account"
            @update:modelValue="(value) => handleSelectChange('account_id', value?.id)"
            :floating-text="t('general.account')"
            :error="form.errors?.account_id"
            :searchable="true"
            resource-type="accounts"
            :search-fields="['name', 'number', 'slug']"
          />
          <div class="flex gap-3">
                <NextSelect
                :options="currencies"
                label-key="code"
                value-key="id"
                :reduce="currency => currency"
                v-model="form.selected_currency"
                @update:modelValue="(value) => handleSelectChange('currency_id', value.id)"
                :floating-text="t('admin.currency.currency')"
                :error="form.errors?.currency_id"
                :searchable="true"
                resource-type="currencies"
                :search-fields="['name', 'code', 'symbol']"
              />
              <NextInput label="Rate" v-model="form.rate" type="number" :error="form.errors?.rate"/>
            </div>
          <div class="col-span-1 flex items-center gap-2">
            <label class="text-sm font-medium text-gray-700">{{ t('general.status') }}</label>
            <input type="checkbox" v-model="form.is_active" />
          </div>
        </div>
      </div>
      <div class="mt-4 flex gap-2">
        <button type="submit" class="btn btn-primary px-4 py-2 rounded-md bg-primary text-white">{{ t('general.create') }}</button>
        <button type="button" class="btn btn-primary px-4 py-2 rounded-md bg-primary border text-white" @click="() => submit(true)">
          {{ t('general.create') }} & {{ t('general.new') }}
        </button>
        <button type="button" class="btn px-4 py-2 rounded-md border" @click="() => $inertia.visit('/owners')">{{ t('general.cancel') }}</button>
      </div>
    </form>
  </AppLayout>
</template>


