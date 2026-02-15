<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from "@/Components/next/NextTextarea.vue";
import SubmitButtons from '@/Components/SubmitButtons.vue';
import ModuleHelpButton from '@/Components/ModuleHelpButton.vue'
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
});

const form = useForm({
    name: '',
    code: '',
    phone_no: '',
    contact_person: '',
    email: '',
    address: '',
    currency_id: null,
    selected_currency: null,
    selected_opening_currency: null,
    opening_currency_id: null,
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
                description: t('general.create_success', { name: t('ledger.supplier.supplier') }),
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
        .post(route('suppliers.store'), postOptions);
};

const handleCancel = () => {
    router.visit(route('suppliers.index'))
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
    <AppLayout :title="t('ledger.supplier.supplier')">
        <form @submit.prevent="handleSubmitAction(false)">
            <div class="mb-5 rounded-xl border p-4 shadow-sm border-primary relative">
                <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
                    {{ t('general.create', { name: t('ledger.supplier.supplier') }) }}
                </div>
                <ModuleHelpButton module="ledgers" />
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
                        label-key="code"
                        value-key="id"
                        id="currency"
                        :floating-text="t('admin.currency.currency')"
                        :searchable="true"
                        resource-type="currencies"
                        :search-fields="['name', 'code', 'symbol']"
                        :error="form.errors.currency_id"
                    />

                </div>
                <div class="md:col-span-3 mt-4">
                    <div class="pt-2">
                        <span class="font-bold">{{ t('item.opening') }}</span>
                        <div class="mt-3">
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

            <SubmitButtons
                :create-label="t('general.create')"
                :create-and-new-label="t('general.create_and_new')"
                :cancel-label="t('general.cancel')"
                :creating-label="t('general.creating', { name: t('ledger.supplier.supplier') })"
                :create-loading="createLoading"
                :create-and-new-loading="createAndNewLoading"
                @create-and-new="handleSubmitAction(true)"
                @cancel="handleCancel"
            />
        </form>
    </AppLayout>
</template>
