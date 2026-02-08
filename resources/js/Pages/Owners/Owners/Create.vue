<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { useForm, usePage } from '@inertiajs/vue3'
import NextInput from '@/Components/next/NextInput.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import SubmitButtons from '@/Components/SubmitButtons.vue'
import { useI18n } from 'vue-i18n'
import { watch, computed, ref } from 'vue'
import { useLazyProps } from '@/composables/useLazyProps'
import { toast } from 'vue-sonner'
const { t } = useI18n()

const page = usePage()
// Pull shared data from HandleInertiaRequests middleware
const allAccounts = computed(() => page.props.accounts?.data ?? page.props.accounts ?? [])
const currencies = computed(() => page.props.currencies?.data ?? page.props.currencies ?? [])

useLazyProps(page.props, ['accounts', 'capitalAccounts', 'drawingAccounts'])

// Derive lists for selects
const capitalAccounts = computed(() => {
  const shared = page.props.capitalAccounts?.data ?? page.props.capitalAccounts
  if (Array.isArray(shared) && shared.length) {
    return shared
  }
  const list = Array.isArray(allAccounts.value) ? allAccounts.value : []
  return list.filter(a => a?.slug === 'owners-capital' || (a?.name || '').toLowerCase().includes('capital'))
})
const drawingAccounts = computed(() => {
  const shared = page.props.drawingAccounts?.data ?? page.props.drawingAccounts
  if (Array.isArray(shared) && shared.length) {
    return shared
  }
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

const submitAction = ref(null)
const createLoading = computed(() => form.processing && submitAction.value === 'create')
const createAndNewLoading = computed(() => form.processing && submitAction.value === 'create_and_new')

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
const handleSubmitAction = (createAndNew = false) => {
    const isCreateAndNew = createAndNew === true;
    submitAction.value = isCreateAndNew ? 'create_and_new' : 'create';

    // Always show toast on success, regardless of which button is used
    const postOptions = {
        onSuccess: () => {
            toast.success(t('general.success'), {
                description: t('general.create_success', { name: t('owner.owner') }),
                class: 'bg-green-600',
            });
            if (isCreateAndNew) {
                form.reset(); 
                form.transform((d) => d); // Reset transform to identity
            }
        },
        // Any shared callbacks like onError can go here
    };

    const transformFn = isCreateAndNew
        ? (data) => ({ ...data, create_and_new: true, stay: true })
        : (data) => data;

    form
        .transform(transformFn)
        .post(route('owners.store'), postOptions);
};
</script>

<template>
    <AppLayout :title="t('general.create', { name: t('owner.owner') })">
    <form @submit.prevent="handleSubmitAction(false)">
      <div class="mb-5 rounded-xl border p-4 shadow-sm border-primary relative">
        <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
          {{ t('general.create', { name: t('owner.owner') }) }}
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
          <NextInput :label="t('general.name')" v-model="form.name" :error="form.errors?.name" />
          <NextInput :label="t('owner.father_name')" v-model="form.father_name" :error="form.errors?.father_name" />
          <NextInput :label="t('owner.nic')" v-model="form.nic" :error="form.errors?.nic" />
          <NextInput :label="t('owner.email')" v-model="form.email" type="email" :error="form.errors?.email"/>
          <NextInput :label="t('owner.phone_number')" v-model="form.phone_number" type="text" :error="form.errors?.phone_number"/>
          <NextInput :label="t('general.address')" v-model="form.address" type="text" :error="form.errors?.address"/>
          <NextInput :label="t('owner.ownership_percentage')" v-model="form.ownership_percentage" type="number" :error="form.errors?.ownership_percentage"/>
          <NextInput :label="t('general.amount')" v-model="form.amount" type="number" :error="form.errors?.amount"/>
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
              <NextInput :label="t('general.rate')" :disabled="form.selected_currency.is_base_currency == true" v-model="form.rate" type="number" :error="form.errors?.rate"/>
            </div>
          <div class="col-span-1 flex items-center gap-2">
            <label class="text-sm font-medium text-gray-700">{{ t('general.status') }}</label>
            <input type="checkbox" v-model="form.is_active" />
          </div>
        </div>
      </div>
      <SubmitButtons
        :create-label="t('general.create')"
        :create-and-new-label="t('general.create_and_new')"
        :cancel-label="t('general.cancel')"
        :creating-label="t('general.creating', { name: t('owner.owner') })"
        :create-loading="createLoading"
        :create-and-new-loading="createAndNewLoading"
        @create-and-new="handleSubmitAction(true)"
        @cancel="() => $inertia.visit('/owners')"
      />
    </form>
  </AppLayout>
</template>


