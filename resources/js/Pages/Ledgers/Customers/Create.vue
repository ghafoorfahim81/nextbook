<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { useFormGuard } from '@/composables/useFormGuard'
import NextInput from '@/Components/next/NextInput.vue';
import NextPhoneInput from '@/Components/next/NextPhoneInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import SubmitButtons from '@/Components/SubmitButtons.vue';
import FormPageToolbar from '@/Components/FormPageToolbar.vue'
import { useForm, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ref, computed, watch } from 'vue';
import { toast } from 'vue-sonner';
const { t } = useI18n();

const props = defineProps({
    currencies: {
        type: Array,
        required: true,
    },
    homeCurrency: {
        type: Object,
        required: true,
    },
    customerGroups: { type: Array, default: () => [] },
    paymentTerms: { type: Array, default: () => [] },
    countries: { type: Array, default: () => [] },
    provinces: { type: Array, default: () => [] },
    nextCode: { type: String, default: '' },
});

const form = useForm({
    name: '',
    code: props.nextCode,
    phone_no: '',
    contact_person: '',
    email: '',
    address: '',
    currency_id: null,
    selected_currency: null,
    group_id: null,
    payment_term_id: null,
    country_id: null,
    province_id: null,
    credit_limit: null,
    credit_limit_status: 'Indicate',
    discount: null,
    whatsapp_number: '',
    amount: '',
    rate: '',
})

watch(props.homeCurrency, (list) => {
    if (props.homeCurrency && !form.currency_id) {
        form.currency_id = props.homeCurrency.id
    }
}, { immediate: true })

const submitAction = ref(null);
const createLoading = computed(() => form.processing && submitAction.value === 'create');
const createAndNewLoading = computed(() => form.processing && submitAction.value === 'create_and_new');
 
const handleSubmitAction = (createAndNew = false) => {
    const isCreateAndNew = createAndNew === true;
    submitAction.value = isCreateAndNew ? 'create_and_new' : 'create';

    // Always show toast on success, regardless of which button is used
    const postOptions = {
        onSuccess: () => {
            toast.success(t('general.success'), {
                description: t('general.create_success', { name: t('ledger.customer.customer') }),
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
        .post(route('customers.store'), postOptions);
};

const handleCancel = () => {
    router.visit(route('customers.index'))
}


const handleSelectChange = (field, value) => {
    if(field === 'currency_id') {
        form.rate = value?.exchange_rate??0;
        form.currency_id = value?.id;
    }
    else {
        form[field] = value;
    }
};

useFormGuard(form)
</script>

<template>
    <AppLayout :title="t('ledger.customer.customer')">
        <FormPageToolbar confirm-module="ledger" back-route="customers.index" module="ledgers" />
        <form @submit.prevent="handleSubmitAction(false)">
            <div class="mb-5 rounded-xl border p-4 shadow-sm border-primary relative">
                <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
                    {{ t('general.create', { name: t('ledger.customer.customer') }) }}
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                    <NextInput is-required :label="t('general.name')" v-model="form.name" :error="form.errors?.name" :placeholder="t('general.enter', { text: t('general.name') })" />
                    <NextInput :label="t('admin.currency.code')" v-model="form.code" :error="form.errors?.code" />
                    <NextInput :label="t('ledger.contact_person')" v-model="form.contact_person" :error="form.errors?.contact_person" :placeholder="t('general.enter', { text: t('ledger.contact_person') })" />
                    <NextInput :label="t('general.email')" v-model="form.email" :error="form.errors?.email" :placeholder="t('general.enter', { text: t('general.email') })" />
                    <NextPhoneInput :label="t('general.phone')" v-model="form.phone_no" :error="form.errors?.phone_no" />
                    <NextPhoneInput :label="t('ledger.whatsapp_number')" v-model="form.whatsapp_number" :error="form.errors?.whatsapp_number" />
                    <NextInput :label="t('general.address')" v-model="form.address" :error="form.errors?.address" :placeholder="t('general.enter', { text: t('general.address') })" />
                    <NextSelect
                        :options="currencies.data"
                        v-model="form.currency_id"
                        label-key="code"
                        value-key="id"
                        id="currency"
                        :floating-text="t('admin.currency.currency')"
                        :searchable="true"
                        resource-type="currencies"
                        :search-fields="['name', 'code', 'symbol']"
                        :error="form.errors.currency_id"
                    />
                    <NextSelect :options="customerGroups" v-model="form.group_id" label-key="localized_name" value-key="id" :floating-text="t('ledger.customer_group')" :searchable="true" />
                    <NextSelect :options="paymentTerms" v-model="form.payment_term_id" label-key="name" value-key="id" :floating-text="t('ledger.payment_term')" :searchable="true" />
                    <NextSelect :options="countries" v-model="form.country_id" label-key="localized_name" value-key="id" :floating-text="t('ledger.country')" :searchable="true" />
                    <NextSelect :options="provinces.filter((province) => !form.country_id || province.country_id === form.country_id)" v-model="form.province_id" label-key="localized_name" value-key="id" :floating-text="t('ledger.province')" :searchable="true" />
                    <NextInput type="number" step="any" :label="t('ledger.credit_limit')" v-model="form.credit_limit" :error="form.errors?.credit_limit" />
                    <NextSelect :options="[{ id: 'Block', name: t('ledger.credit_limit_block') }, { id: 'Indicate', name: t('ledger.credit_limit_indicate') }]" v-model="form.credit_limit_status" label-key="name" value-key="id" :floating-text="t('ledger.credit_limit_status')" />
                    <NextInput type="number" step="any" :label="t('ledger.discount')" v-model="form.discount" :error="form.errors?.discount" />
                </div>
                <div class="md:col-span-3 mt-4">
                    <div class="pt-2">
                        <span class="font-bold">{{ t('item.opening') }}</span>
                        <div class="mt-3">
                            <div class="grid grid-cols-2 gap-2">
                                <NextInput placeholder="Rate" :disabled="form.currency_id === homeCurrency.id" :error="form.errors?.rate" type="number" step="any" v-model="form.rate" :label="t('general.rate')" />
                                <NextInput placeholder="Amount" :error="form.errors?.amount" type="number" step="any" v-model="form.amount" :label="t('general.amount')" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <progress v-if="form.progress" :value="form.progress.percentage" max="100">
                {{ form.progress.percentage }}%
            </progress>

            <SubmitButtons module="ledger"
                :create-label="t('general.create')"
                :create-and-new-label="t('general.create_and_new')"
                :cancel-label="t('general.cancel')"
                :creating-label="t('general.creating', { name: t('ledger.customer.customer') })"
                :create-loading="createLoading"
                :create-and-new-loading="createAndNewLoading"
                @create-and-new="handleSubmitAction(true)"
                @cancel="handleCancel"
            />
        </form>
    </AppLayout>
</template>
