<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import { useForm, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ref, computed, watch } from 'vue';
import { useLazyProps } from '@/composables/useLazyProps'

const { t } = useI18n();

const props = defineProps({
    account: { type: Object, required: true },
    currencies: {type: Object, required: true},
    accountTypes: {type: Object, required: false, default: () => ({ data: [] })},
    transactionTypes: { type: Array, required: true },
});

useLazyProps(props, ['accountTypes'])



const form = useForm({
    ...props.account.data,
    account_type_id: props.account.data.account_type_id,
    currency_id: props.account.data.currency_id,
    selected_currency: props.account.data?.opening?.currency,
    currency_id: props.account.data?.opening?.currency_id,
    rate: props.account.data?.opening?.rate,
    amount: props.account.data?.opening?.amount,
    transaction_type: props.account.data?.opening?.transaction_type,
});


const handleUpdate = () => {
    form.patch(route('chart-of-accounts.update', form.id))
}


const handleCancel = () => {
    router.visit(route('chart-of-accounts.index'));
};

const handleSelectChange = (field, value) => {
    if(field === 'currency_id') {
        form.rate = value?.exchange_rate??0;
    }
    form[field] = value;

};
</script>

<template>
    <AppLayout :title="t('account.chart_of_accounts')">
        <form @submit.prevent="handleUpdate">
            <div class="mb-5 rounded-xl border p-4 shadow-sm relative">
                <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
                    {{ t('general.update', { name: t('account.account') }) }}
                </div>

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
                        v-model="form.account_type_id"
                        label-key="name"
                        value-key="id"
                        id="account_type"
                        :floating-text="t('account.account_type')"
                        :searchable="true"
                        resource-type="account-types"
                        :search-fields="['name']"
                        :error="form.errors.account_type_id"
                    />
                    <div class="grid grid-cols-2 gap-2">
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
                        <NextInput
                            v-model="form.rate"
                            type="number"
                            step="any"
                            :label="t('general.rate')"
                            :error="form.errors?.rate"
                        />
                    </div>
                    <NextSelect
                        :options="transactionTypes"
                        v-model="form.transaction_type"
                        label-key="name"
                        value-key="id"
                        :floating-text="t('general.transaction_type')"
                        :error="form.errors?.transaction_type_id"
                        @update:modelValue="(value) => handleSelectChange('transaction_type', value)"
                    />
                    <NextInput
                        v-model="form.amount"
                        type="number"
                        step="any"
                        :label="t('general.amount')"
                        :error="form.errors?.amount"
                    />
                    <NextTextarea
                        v-model="form.remark"
                        :label="t('general.remark')"
                        :placeholder="t('general.enter', { text: t('general.remark') })"
                        :error="form.errors?.remark"
                        class="md:col-span-3"
                    />
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
