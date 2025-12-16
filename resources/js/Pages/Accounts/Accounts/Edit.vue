<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import { useForm, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    account: { type: Object, required: true }, 
    currencies: {type: Object, required: true},
    accountTypes: {type: Object, required: true},
    transactionTypes: { type: Array, required: true },
});

const isBaseCurrency = (currencyId) => {
    return (props.currencies?.data || []).some(
        (currency) => currency.id === currencyId && currency.is_base_currency
    );
};

const buildOpenings = () => {
    const existing = props.account.data.openings || [];
    const byCurrency = existing.reduce((acc, o) => {
        const currencyId = o.currency_id || o.currency?.id;
        if (currencyId) {
            acc[currencyId] = o;
        }
        return acc;
    }, {});

    return (props.currencies?.data || []).map(currency => {
        const found = byCurrency[currency.id] || {};
        return {
            currency_id: currency.id,
            currency_name: currency.name,
            amount: found.amount ?? 0,
            rate: found.rate ?? 0,
            type: found.type ?? 'debit',
        };
    });
};

const form = useForm({
    ...props.account.data,
    account_type_id: props.account.data.account_type_id,
    openings: buildOpenings(),

});

console.log('transactionTypes', props.account);
 
const handleUpdate = () => {
    form.patch(route('chart-of-accounts.update', form.id))
}
 

const handleCancel = () => {
    router.visit(route('chart-of-accounts.index'));
};
</script>

<template>
    <AppLayout :title="t('account.chart_of_accounts')">
        <form @submit.prevent="handleUpdate">
            <div class="mb-5 rounded-xl border p-4 shadow-sm relative">
                <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
                    {{ t('general.create', { name: t('account.account') }) }}
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

                    <NextTextarea
                        v-model="form.remark"
                        :label="t('general.remark')"
                        :placeholder="t('general.enter', { text: t('general.remark') })"
                        :error="form.errors?.remark"
                        class="md:col-span-3"
                    />
                </div>

                <div class="md:col-span-4 mt-4">
                    <div class="pt-2">
                        <span class="font-bold">{{ t('item.opening') }}</span>
                        <div class="mt-3 space-y-3">
                            <div
                                v-for="(opening, index) in form.openings"
                                :key="opening.currency_id"
                                class="grid grid-cols-1 md:grid-cols-4 gap-4 items-start"
                            >
                                <NextInput
                                    :label="`${t('general.amount')} (${opening.currency_name})`"
                                    type="number"
                                    step="any"
                                    v-model="opening.amount"
                                    :placeholder="t('general.enter', { text: t('general.amount') })"
                                />
                                <NextInput
                                    :label="`${t('general.rate')} (${opening.currency_name})`"
                                    type="number"
                                    :disabled="isBaseCurrency(opening.currency_id)"
                                    step="any"
                                    v-model="opening.rate"
                                    :placeholder="t('general.enter', { text: t('general.rate') })"
                                />

                                <NextSelect
                                    :options="transactionTypes"
                                    v-model="opening.type"
                                    label-key="name"
                                    value-key="id"
                                    :reduce="transactionType => transactionType.id"
                                    :id="`transaction_type_${index}`"
                                    :floating-text="t('general.transaction_type')"
                                />

                                <div class=" text-sm text-gray-700 border rounded-md p-2">
                                    {{ opening.currency_name }}
                                </div>
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
