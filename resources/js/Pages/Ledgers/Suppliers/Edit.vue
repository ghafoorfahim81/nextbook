<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { Button } from '@/Components/ui/button';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from "@/Components/next/NextTextarea.vue";
import LedgerOpeningRows from '@/Components/ledger/LedgerOpeningRows.vue';
import FormPageToolbar from '@/Components/FormPageToolbar.vue'
import { useForm, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ref, computed, watch } from 'vue';
import { toast } from 'vue-sonner';
import { buildOpeningRows } from '@/utils/ledgerOpenings';
const props = defineProps({
    supplier: { type: Object, required: true },
    currencies: { type: Array, required: true },
    homeCurrency: { type: Object, required: true },
});

const { t } = useI18n();


const form = useForm({
    ...props.supplier.data,
    currency_id: props.supplier.data.currency_id,
    selected_currency: props.supplier.data?.currency,
    // Every currency gets a row, pre-filled from the openings already posted.
    openings: buildOpeningRows(
        props.currencies,
        props.supplier.data?.openings ?? [],
        props.homeCurrency,
    ),
})

watch(props.homeCurrency, (list) => {
    if (props.homeCurrency && !form.currency_id) {
        form.currency_id = props.homeCurrency.id
    }
}, { immediate: true })
const handleSubmit = () => {
    form.patch(route('suppliers.update', form.id), {
        onSuccess: () => {
            toast.success(t('general.success'), {
                description: t('general.update_success', { name: t('ledger.supplier.supplier') }),
                class: 'bg-green-600',
            });
        },
    });
}

const handleCancel = () => {
    router.visit(route('suppliers.index'))
}

</script>

<template>
    <AppLayout :title="t('general.edit', { name: t('ledger.supplier.supplier') })">
        <FormPageToolbar back-route="suppliers.index" module="ledgers" />
        <form @submit.prevent="handleSubmit">
            <div class="mb-5 rounded-xl border p-4 shadow-sm border-primary relative">
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

            </div>

            <div class="mb-5">
                <LedgerOpeningRows
                    v-model="form.openings"
                    :currencies="currencies"
                    :home-currency="homeCurrency"
                    :errors="form.errors"
                />
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
