<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import ModuleHelpButton from '@/Components/ModuleHelpButton.vue'
import { useForm, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n'; 
import { toast } from 'vue-sonner';
import { useLazyProps } from '@/composables/useLazyProps'

const { t } = useI18n();

const props = defineProps({
    account: { type: Object, required: true },
    currencies: {type: Object, required: true},
    accountTypes: {type: Object, required: false, default: () => ({ data: [] })},
    homeCurrency: {type: Object, required: true},
});

useLazyProps(props, ['accountTypes'])

const form = useForm({
    ...props.account.data,
    account_type_id: props.account.data.account_type_id,
    selected_account_type: props.account.data.account_type,
    currency_id: props.account.data.currency_id,
    selected_currency: props.account.data?.opening?.currency,
    currency_id: props.account.data?.opening?.currency_id,
    rate: props.account.data?.opening?.rate??null,
    amount: props.account.data?.opening?.amount??0,
});


const handleUpdate = () => {
    form.patch(route('chart-of-accounts.update', form.id), {
        onSuccess: () => {
            toast.success(t('general.success'), {
                description: t('general.update_success', { name: t('account.account') }),
                class: 'bg-green-600',
            });
        },
    });
}


const handleCancel = () => {
    router.visit(route('chart-of-accounts.index'));
};

const handleSelectChange = (field, value) => {
    if(field === 'currency_id') {
        form.rate = value?.exchange_rate??0;
        form.currency_id = value?.id;
    }
    else{
        console.log('this is value', value);
        form[field] = value.id;
    }

};
</script>

<template>
    <AppLayout :title="t('account.chart_of_accounts')">
        <form @submit.prevent="handleUpdate">
            <div class="mb-5 rounded-xl border p-4 shadow-sm relative">
                <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
                    {{ t('general.update', { name: t('account.account') }) }}
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
                        :label="t('general.number')"
                        v-model="form.number"
                        :error="form.errors?.number"
                        :placeholder="t('general.enter', { text: t('general.number') })"
                    />

                    <NextSelect
                        :options="accountTypes?.data"
                        v-model="form.selected_account_type"
                        @update:modelValue="(value) => handleSelectChange('account_type_id', value)"
                        label-key="name"
                        value-key="id"
                        id="account_type"
                        :reduce="accountType => accountType"
                        :floating-text="t('account.account_type')"
                        :searchable="true"
                        resource-type="account-types"
                        :search-fields="['name']"
                        :error="form.errors.account_type_id"
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
                        <span class="font-bold">{{ t('item.opening') }}  </span>
                        <div class="mt-3">
                            <div class="grid grid-cols-3 gap-2">
                                <NextSelect
                                :options="currencies.data"
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

            <div class="mt-4 flex gap-2">
                <button type="submit" class="btn btn-primary px-4 py-2 rounded-md bg-primary text-white">
                    {{ t('general.update') }}
                </button>

                <button
                    type="button"
                    class="btn px-4 py-2 rounded-md border"
                    @click="handleCancel"
                >
                    {{ t('general.cancel') }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
