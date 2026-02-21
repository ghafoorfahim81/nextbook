<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import SubmitButtons from '@/Components/SubmitButtons.vue';
import ModuleHelpButton from '@/Components/ModuleHelpButton.vue'
import { useForm, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ref, computed, watch } from 'vue';
import { toast } from 'vue-sonner';
import { useLazyProps } from '@/composables/useLazyProps'
const { t } = useI18n();
const page = usePage();
const accounts = computed(() => page.props.accounts?.data || [])
const accountTypes = computed(() => page.props.accountTypes?.data || [])
const currencies = computed(() => page.props.currencies?.data || [])
const homeCurrency = computed(() => page.props.homeCurrency || {})
useLazyProps(page.props, ['accounts'])
  
const form = useForm({
    name: '',
    local_name: '',
    number: '',
    remark: '',
    parent_id: null,
    selected_currency: null,
    currency_id: null,
    rate: 1,
    amount: 0,
    selected_account_type: null,
    account_type_id: null,
});
watch(homeCurrency, (list) => {
    if (homeCurrency.value && !form.currency_id) {
        form.selected_currency = homeCurrency.value
        form.currency_id = homeCurrency.value.id
        form.rate = homeCurrency.value.exchange_rate
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
                description: t('general.create_success', { name: t('account.account') }),
                class: 'bg-green-600',
            });
            if (isCreateAndNew) {
                form.reset();
                if (typeof buildOpenings === 'function') {
                    form.openings = buildOpenings();
                }
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
        .post(route('chart-of-accounts.store'), postOptions);
};

const handleCancel = () => {
    router.visit(route('chart-of-accounts.index'));
};

const handleSelectChange = (field, value) => {
    if(field === 'currency_id') {
        form.rate = value?.exchange_rate??0;
        form.currency_id = value?.id; 
    }
    else{ 
        form[field] = value.id;
    }
};
</script>

<template>
    <AppLayout :title="t('account.chart_of_accounts')">
        <form @submit.prevent="handleSubmitAction(false)">
            <div class="mb-5 rounded-xl border p-4 shadow-sm relative">
                <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
                    {{ t('general.create', { name: t('account.account') }) }}
                </div>
                <ModuleHelpButton module="chart_of_accounts" />

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                    <NextInput
                        :label="t('general.name')"
                        v-model="form.name"
                        :error="form.errors?.name"
                        :placeholder="t('general.enter', { text: t('general.name') })"
                    />
                    <NextInput
                        :label="t('account.local_name')"
                        v-model="form.local_name"
                        :error="form.errors?.local_name"
                        :placeholder="t('general.enter', { text: t('account.local_name') })"
                    />
                    <NextInput
                        :label="t('general.number')"
                        v-model="form.number"
                        :error="form.errors?.number"
                        :placeholder="t('general.enter', { text: t('general.number') })"
                    />

                    <NextSelect
                        :options="accountTypes"
                        v-model="form.selected_account_type"
                        label-key="name"
                        value-key="id"
                        @update:modelValue="(value) => handleSelectChange('account_type_id', value)"
                        id="account_type"
                        :reduce="accountType => accountType"
                        :floating-text="t('account.account_type')"
                        :searchable="true"
                        resource-type="account_types"
                        :search-fields="['name']"
                        :error="form.errors?.account_type_id"
                    />
                    <NextSelect
                        :options="accounts"
                        v-model="form.selected_parent_account"
                        label-key="name"
                        value-key="id"
                        @update:modelValue="(value) => handleSelectChange('parent_id', value)"
                        id="parent_account"
                        :reduce="account => account"
                        :floating-text="t('account.parent_account')"
                        :error="form.errors?.parent_id"
                        :searchable="true"
                        resource-type="accounts"
                        :search-fields="['name', 'number', 'slug']"
                    />
                      <NextTextarea
                        v-model="form.remark"
                        :label="t('general.remark')"
                        :placeholder="t('general.enter', { text: t('general.remark') })"
                        :error="form.errors?.remark"
                        class="md:col-span-3"
                    />
                </div>
                <div class="md:col-span-3 mt-4" v-if="form?.selected_account_type?.slug=='cash-or-bank'">
                    <div class="pt-2">
                        <span class="font-bold">{{ t('item.opening') }} </span>
                        <div class="mt-3">
                            <div class="grid grid-cols-3 gap-2">
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

            <SubmitButtons
                :create-label="t('general.create')"
                :create-and-new-label="t('general.create_and_new')"
                :cancel-label="t('general.cancel')"
                :creating-label="t('general.creating', { name: t('account.account') })"
                :create-loading="createLoading"
                :create-and-new-loading="createAndNewLoading"
                @create-and-new="handleSubmitAction(true)"
                @cancel="handleCancel"
            />
        </form>
    </AppLayout>
</template>
