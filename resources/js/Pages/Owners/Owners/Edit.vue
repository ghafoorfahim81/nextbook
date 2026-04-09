<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { useForm, usePage } from '@inertiajs/vue3'
import NextInput from '@/Components/next/NextInput.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import SubmitButtons from '@/Components/SubmitButtons.vue'
import ModuleHelpButton from '@/Components/ModuleHelpButton.vue'
import { useI18n } from 'vue-i18n'
import { watch, computed, ref } from 'vue'
import { useLazyProps } from '@/composables/useLazyProps'
import { toast } from 'vue-sonner'
const { t } = useI18n()

const page = usePage()
// Pull shared data from HandleInertiaRequests middleware
const allAccounts = computed(() => page.props.accounts?.data ?? page.props.accounts ?? [])
const currencies = computed(() => page.props.currencies?.data ?? page.props.currencies ?? [])
 const capitalAccounts = computed(() => page.props.capitalAccounts?.data ?? page.props.capitalAccounts ?? [])
 const drawingAccounts = computed(() => page.props.drawingAccounts?.data ?? page.props.drawingAccounts ?? [])
 const bankAccounts = computed(() => page.props.bankAccounts?.data ?? page.props.bankAccounts ?? [])
 const homeCurrency = computed(() => page.props.homeCurrency || {}) 
const owner = page.props.owner?.data ?? page.props.owner; 
console.log('this is the owner', owner)
const form = useForm({
    capital_account_id: owner?.capital_account_id ?? null,
    selected_capital_account: owner?.capital_account ?? null,
    drawing_account_id: owner?.drawing_account_id ?? null,
    selected_drawing_account: owner?.drawing_account ?? null,
    opening_currency_id: owner?.opening_currency_id ?? null,
    selected_opening_currency: owner?.opening_currency ?? null,
    name: owner?.name ?? null,
    bank_account_id: owner?.bank_account_id ?? null,
    selected_bank_account: owner?.bank_account_transaction?.account ?? null,
    father_name: owner?.father_name ?? null,
    nic: owner?.nic ?? null,
    email: owner?.email ?? null,
    address: owner?.address ?? null,
    phone_number: owner?.phone_number ?? null,
    ownership_percentage: owner?.ownership_percentage ?? 100,
    is_active: owner?.is_active ?? true,
    amount: owner?.amount ?? null, 
    selected_currency: owner?.selected_currency ?? null,
    rate: owner?.rate ?? 1,
})

const submitAction = ref(null)
const createLoading = computed(() => form.processing && submitAction.value === 'create')
const createAndNewLoading = computed(() => form.processing && submitAction.value === 'create_and_new')

watch(currencies, (list) => {
  if (list && list.length && !form.opening_currency_id) {
    const base = list.find(c => c.is_base_currency)
    if (base) {
      form.selected_currency = base
      form.opening_currency_id = base.id
      form.rate = base.exchange_rate
    }
  }
}, { immediate: true })


function handleSelectChange(field, value) {
  form[field] = value
  if (field === 'opening_currency_id') {
    console.log('this is the opening currency id', value)
    const chosen = (currencies.value || []).find(c => c.id === value)
    form.opening_currency_id = value.id
    if (chosen) form.rate = chosen.exchange_rate
  }
}

const handleSubmit = () => {
    form.patch(route('owners.update', owner.id), {
        onSuccess: () => {
            toast.success(t('general.success'), {
                description: t('general.update_success', { name: t('owner.owner') }),
                class: 'bg-green-600',
            });
        },
    }); 
}
</script>

<template>
    <AppLayout :title="t('general.update', { name: t('owner.owner') })">
    <form @submit.prevent="handleSubmit">
      <div class="mb-5 rounded-xl border p-4 shadow-sm border-primary relative">
        <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
          {{ t('general.update', { name: t('owner.owner') }) }}
        </div>
        <ModuleHelpButton module="owner" />
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
          <NextInput :label="t('general.name')" v-model="form.name" :error="form.errors?.name" />
          <NextInput :label="t('owner.father_name')" v-model="form.father_name" :error="form.errors?.father_name" />
          <NextInput :label="t('owner.nic')" v-model="form.nic" :error="form.errors?.nic" />
          <NextInput :label="t('owner.email')" v-model="form.email" type="email" :error="form.errors?.email"/>
          <NextInput :label="t('owner.phone_number')" v-model="form.phone_number" type="text" :error="form.errors?.phone_number"/>
          <NextInput :label="t('general.address')" v-model="form.address" type="text" :error="form.errors?.address"/>
          <NextInput :label="t('owner.ownership_percentage')" v-model="form.ownership_percentage" type="number" :error="form.errors?.ownership_percentage"/>
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
          
          <div class="col-span-1 flex items-center gap-2">
            <label class="text-sm font-medium text-gray-700">{{ t('general.status') }}</label>
            <input type="checkbox" v-model="form.is_active" />
          </div>
        </div>
        <div class="md:col-span-3 mt-4">
          <div class="pt-2">
              <span class="font-bold">{{ t('owner.capital_contribution') }}</span>
              <div class="mt-3">
                  <div class="grid grid-cols-3 gap-2">
                    <NextSelect
                    :options="bankAccounts"
                    label-key="name"
                    value-key="id"
                    :reduce="account => account"
                    v-model="form.selected_bank_account"
                    @update:modelValue="(value) => handleSelectChange('bank_account_id', value?.id)"
                    :floating-text="t('general.account')"
                    :error="form.errors?.bank_account_id"
                    :searchable="true"
                    resource-type="accounts"
                    :search-fields="['name', 'number', 'slug']"
                  />
                      <div class="grid grid-cols-2 gap-2">
                        <NextSelect
                        :options="currencies"
                        v-model="form.selected_opening_currency"
                        label-key="code"
                        value-key="id"
                        @update:modelValue="(value) => handleSelectChange('opening_currency_id', value)"
                        :reduce="currency => currency"
                        :floating-text="t('admin.currency.currency')"
                        :error="form.errors?.opening_currency_id"
                        :searchable="true"
                        resource-type="currencies"
                        :search-fields="['name', 'code', 'symbol']"
                         />
                        <NextInput placeholder="Rate" :disabled="form.selected_opening_currency?.is_base_currency == true" :error="form.errors?.rate" type="number" step="any" v-model="form.rate" :label="t('general.rate')" />
                        
                      </div>
                      <NextInput placeholder="Amount" :error="form.errors?.amount" type="number" step="any" v-model="form.amount" :label="t('general.amount')" />
                  </div>
              </div>
          </div>
      </div>
      </div>
      <div class="mt-4 flex gap-2">
        <button type="submit" class="btn btn-primary px-4 py-2 rounded-md bg-primary text-white" :disabled="form.processing">{{ t('general.update') }}</button>
        <button type="button" class="btn px-4 py-2 rounded-md border" @click="() => $inertia.visit('/owners')">{{ t('general.cancel') }}</button>
    </div>
    </form>
  </AppLayout>
</template>


