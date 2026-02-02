<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import SubmitButtons from '@/Components/SubmitButtons.vue';
import { useForm, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ref, computed, watch } from 'vue';
import { useToast } from '@/Components/ui/toast/use-toast';
import { useLazyProps } from '@/composables/useLazyProps'
const { t } = useI18n();
const { toast } = useToast();
const props = defineProps({
    accountTypes: {
        type: Object,
        required: false,
        default: () => ({ data: [] }),
    },
    currencies: {
        type: Object,
        required: true,
    },
    transactionTypes: {
        type: Array,
        required: true,
    },
    homeCurrency: {
        type: Object,
        required: true,
    },
});

useLazyProps(props, ['accountTypes'])
const form = useForm({
    name: '',
    number: '',
    remark: '',
    selected_currency: null,
    currency_id: null,
    rate: 1,
    amount: 0,
    account_type_id: null,
    transaction_type: null,
});
watch(props.homeCurrency, (list) => {
    if (props.homeCurrency && !form.currency_id) {
        form.selected_currency = props.homeCurrency
        form.currency_id = props.homeCurrency.id
        form.rate = props.homeCurrency.exchange_rate
    }
}, { immediate: true })

const submitAction = ref(null);
const createLoading = computed(() => form.processing && submitAction.value === 'create');
const createAndNewLoading = computed(() => form.processing && submitAction.value === 'create_and_new');

const handleSubmitAction = (createAndNew = false) => {
    const isCreateAndNew = createAndNew === true;
    submitAction.value = isCreateAndNew ? 'create_and_new' : 'create';

    // Shared post options for both actions
    const postOptions = isCreateAndNew
        ? {
            onSuccess: () => {
                toast({
                    title: t('general.success'),
                    description: t('general.create_success', { name: t('account.account') }),
                    variant: 'success',
                    class: 'bg-green-600 text-white',
                });
                form.reset();
                form.openings = buildOpenings();
                form.transform((d) => d); // Reset transform to identity
            },
            // Any shared callbacks like onError can go here
        }
        : undefined;

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
    }
    form[field] = value;
    console.log('this is value', field);

};
</script>

<template>
    <AppLayout :title="t('account.chart_of_accounts')">
        <form @submit.prevent="handleSubmitAction(false)">
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
                        :options="props.accountTypes?.data || []"
                        v-model="form.account_type_id"
                        label-key="name"
                        value-key="id"
                        id="account_type"
                        :floating-text="t('account.account_type')"
                        :searchable="true"
                        resource-type="account_types"
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
                    <NextInput placeholder="Rate" :error="form.errors?.rate" type="number" step="any" v-model="form.rate" :label="t('general.rate')" />
                    </div>
                    <NextSelect
                        :options="transactionTypes"
                        v-model="form.selected_transaction_type"
                        label-key="name"
                        value-key="id"
                        :floating-text="t('general.transaction_type')"
                        :error="form.errors?.transaction_type_id"
                        @update:modelValue="(value) => handleSelectChange('transaction_type', value)"
                    />
                    <NextInput placeholder="Amount" :error="form.errors?.amount" type="number" step="any" v-model="form.amount" :label="t('general.amount')" />
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
