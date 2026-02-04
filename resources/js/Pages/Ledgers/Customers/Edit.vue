<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { Button } from '@/Components/ui/button';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from "@/Components/next/NextTextarea.vue";
import { useForm, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ref, computed, watch } from 'vue';
const props = defineProps({
    customer: { type: Object, required: true },
    currencies: { type: Array, required: true },
    homeCurrency: { type: Object, required: true },
});

const { t } = useI18n();
 

const form = useForm({
    ...props.customer.data,
    currency_id: props.customer.data.currency_id,
    selected_currency: props.customer.data?.currency,
    currency_id: props.customer.data.currency_id,
    selected_opening_currency: props.customer.data?.opening?.currency,
    opening_currency_id: props.customer.data?.opening?.currency_id,
    rate: props.customer.data?.opening?.rate,
    amount: props.customer.data?.opening?.amount,
})

console.log('customer', props.customer);
watch(props.homeCurrency, (list) => {
    if (props.homeCurrency && !form.currency_id) { 
        form.currency_id = props.homeCurrency.id  
    }
}, { immediate: true })
const handleUpdate = () => {
    form.patch(route('customers.update', form.id))
}

const handleCancel = () => {
    router.visit(route('customers.index'))
}

const handleSelectChange = (field, value) => {
    if(field === 'currency_id') {
        form.rate = value?.exchange_rate??0;
        form.currency_id = value?.id; 
    }
    else if(field === 'opening_currency_id') {
        form.rate = value?.exchange_rate??0;
        form.opening_currency_id = value?.id; 
    }
    else{
        form[field] = value;
    }
};
</script>

<template>
    <AppLayout :title="t('general.edit', { name: t('ledger.customer.customer') })">
        <form @submit.prevent="handleUpdate">
            <div class="mb-5 rounded-xl border p-4 shadow-sm relative">
                <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
                    {{ t('general.edit', { name: t('ledger.customer.customer') }) }}
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
                            <div class="grid grid-cols-3 gap-2">
                                <NextSelect
                                :options="currencies.data"
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
                                <NextInput placeholder="Rate" :disabled="form.opening_currency_id === homeCurrency.id" :error="form.errors?.rate" type="number" step="any" v-model="form.rate" :label="t('general.rate')" />
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
                <button type="submit" class="btn btn-primary px-4 py-2 rounded-md bg-primary text-white">{{ t('general.update') }}</button>
                <button type="button" class="btn px-4 py-2 rounded-md border" @click="handleCancel">{{ t('general.cancel') }}</button>
            </div>
        </form>
    </AppLayout>
</template>
