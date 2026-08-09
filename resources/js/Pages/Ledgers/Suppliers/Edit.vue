<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { Button } from '@/Components/ui/button';
import NextInput from '@/Components/next/NextInput.vue';
import NextPhoneInput from '@/Components/next/NextPhoneInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from "@/Components/next/NextTextarea.vue";
import LedgerOpeningRows from '@/Components/ledger/LedgerOpeningRows.vue';
import FormPageToolbar from '@/Components/FormPageToolbar.vue'
import AttachmentUploader from '@/Components/AttachmentUploader.vue';
import { Switch } from '@/Components/ui/switch';
import { Label } from '@/Components/ui/label';
import { useForm, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ref, computed, watch } from 'vue';
import { toast } from 'vue-sonner';
import { buildOpeningRows } from '@/utils/ledgerOpenings';
const props = defineProps({
    supplier: { type: Object, required: true },
    currencies: { type: Array, required: true },
    homeCurrency: { type: Object, required: true },
    customerGroups: { type: Array, default: () => [] },
    paymentTerms: { type: Array, default: () => [] },
    countries: { type: Array, default: () => [] },
    provinces: { type: Array, default: () => [] },
});

const { t } = useI18n();


const form = useForm({
    ...props.supplier.data,
    currency_id: props.supplier.data.currency_id,
    selected_currency: props.supplier.data?.currency,
    // Saved attachments are listed separately; this holds only new uploads.
    attachments: [],
    // Every currency gets a row, pre-filled from the openings already posted.
    openings: buildOpeningRows(
        props.currencies,
        props.supplier.data?.openings ?? [],
        props.homeCurrency,
    ),
})

const existingAttachments = ref(props.supplier.data?.attachments ?? [])
const removeExistingAttachment = (id) => {
    router.delete(route('attachments.destroy', id), {
        preserveScroll: true,
        onSuccess: () => {
            existingAttachments.value = existingAttachments.value.filter((a) => a.id !== id)
        },
    })
}

const creditTermOptions = computed(() => [
    { id: 'strict', name: t('ledger.credit_terms_strict') },
    { id: 'warning', name: t('ledger.credit_terms_warning') },
    { id: 'flexible', name: t('ledger.credit_terms_flexible') },
]);

// Provinces are country-scoped; show only the ones belonging to the picked country.
const availableProvinces = computed(() => props.provinces.filter(
    (province) => !form.country_id || province.country_id === form.country_id,
));

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
                    <NextInput is-required :label="t('general.name')" v-model="form.name" :error="form.errors?.name" :placeholder="t('general.enter', { text: t('general.name') })" />
                    <NextInput :label="t('admin.currency.code')" v-model="form.code" :error="form.errors?.code" :placeholder="t('general.enter', { text: t('admin.currency.code') })" />
                    <NextInput :label="t('ledger.contact_person')" v-model="form.contact_person" :error="form.errors?.contact_person" :placeholder="t('general.enter', { text: t('ledger.contact_person') })" />
                    <NextInput :label="t('general.email')" v-model="form.email" :error="form.errors?.email" :placeholder="t('general.enter', { text: t('general.email') })" />
                    <NextPhoneInput :label="t('general.phone')" v-model="form.phone_no" :error="form.errors?.phone_no" />
                    <NextPhoneInput :label="t('ledger.whatsapp_number')" v-model="form.whatsapp_number" :error="form.errors?.whatsapp_number" />
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
                    <NextSelect :options="customerGroups" v-model="form.group_id" label-key="localized_name" value-key="id" :floating-text="t('ledger.customer_group')" :searchable="true" :error="form.errors?.group_id" />
                    <NextSelect :options="paymentTerms" v-model="form.payment_term_id" label-key="name" value-key="id" :floating-text="t('ledger.payment_term')" :searchable="true" :error="form.errors?.payment_term_id" />
                    <NextSelect :options="countries" v-model="form.country_id" label-key="localized_name" value-key="id" :floating-text="t('ledger.country')" :searchable="true" :error="form.errors?.country_id" />
                    <NextSelect :options="availableProvinces" v-model="form.province_id" label-key="localized_name" value-key="id" :floating-text="t('ledger.province')" :searchable="true" :error="form.errors?.province_id" />
                    <NextInput type="number" step="any" :label="t('ledger.discount')" v-model="form.discount" :error="form.errors?.discount" />
                    <div class="flex items-center gap-2">
                        <Switch id="credit_limit_enabled" v-model="form.credit_limit_enabled" />
                        <Label for="credit_limit_enabled" class="cursor-pointer">{{ t('ledger.credit_limit_enabled') }}</Label>
                    </div>
                    <NextInput v-if="form.credit_limit_enabled" type="number" step="any" :label="t('ledger.credit_limit')" v-model="form.credit_limit" :error="form.errors?.credit_limit" />
                    <NextSelect
                        v-if="form.credit_limit_enabled"
                        :options="creditTermOptions"
                        v-model="form.credit_terms"
                        label-key="name"
                        value-key="id"
                        :clearable="false"
                        :floating-text="t('ledger.credit_terms')"
                        :error="form.errors?.credit_terms"
                    />
                </div>

                <div class="mt-4">
                    <AttachmentUploader
                        v-model="form.attachments"
                        :existing="existingAttachments"
                        :label="t('general.attachments')"
                        :error="form.errors['attachments.0']"
                        @remove-existing="removeExistingAttachment"
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
