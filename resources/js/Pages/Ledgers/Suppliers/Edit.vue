<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import { useForm, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    supplier: { type: Object, required: true },
    currencies: { type: Array, required: true },
    branches: { type: Array, required: true },
    transactionTypes: { type: Array, required: true },
});

const { t } = useI18n();

const buildOpenings = () => {
    const existing = props.supplier.data.openings || [];
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
            type: found.type ?? 'credit',
        };
    });
};

const isBaseCurrency = (currencyId) => {
    return (props.currencies?.data || []).some(
        (currency) => currency.id === currencyId && currency.is_base_currency
    );
};

const form = useForm({
    ...props.supplier.data,
    currency_id: props.supplier.data.currency_id,
    openings: buildOpenings(),
});

const handleUpdate = () => {
    form.patch(route('suppliers.update', form.id));
};

const handleCancel = () => {
    router.visit(route('suppliers.index'));
};
</script>

<template>
    <AppLayout :title="t('general.edit', { name: t('ledger.supplier.supplier') })">
        <form @submit.prevent="handleUpdate">
            <div class="mb-5 rounded-xl border p-4 shadow-sm relative">
                <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
                    {{ t('general.edit', { name: t('ledger.supplier.supplier') }) }}
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                    <NextInput :label="t('general.name')" v-model="form.name" :error="form.errors?.name" :placeholder="t('general.enter', { text: t('general.name') })" />
                    <NextInput :label="t('admin.currency.code')" v-model="form.code" :error="form.errors?.code" :placeholder="t('general.enter', { text: t('admin.currency.code') })" />
                    <NextInput :label="t('ledger.contact_person')" v-model="form.contact_person" :error="form.errors?.contact_person" :placeholder="t('general.enter', { text: t('ledger.contact_person') })" />
                    <NextInput :label="t('general.email')" v-model="form.email" :error="form.errors?.email" :placeholder="t('general.enter', { text: t('general.email') })" />
                    <NextInput :label="t('general.phone')" v-model="form.phone_no" :error="form.errors?.phone_no" :placeholder="t('general.enter', { text: t('general.phone') })" />
                    <NextInput :label="t('general.address')" v-model="form.address" :error="form.errors?.address" :placeholder="t('general.enter', { text: t('general.address') })" />

                    <NextSelect
                        :options="currencies.data"
                        v-model="form.currency_id"
                        label-key="name"
                        value-key="id"
                        id="currency"
                        :floating-text="t('admin.currency.currency')"
                        :searchable="true"
                        resource-type="currencies"
                        :search-fields="['name', 'code', 'symbol']"
                        :error="form.errors.currency_id"
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
                <button type="submit" class="btn btn-primary px-4 py-2 rounded-md bg-primary text-white">{{ t('general.update') }}</button>
                <button type="button" class="btn px-4 py-2 rounded-md border" @click="handleCancel">{{ t('general.cancel') }}</button>
            </div>
        </form>
    </AppLayout>
</template>

