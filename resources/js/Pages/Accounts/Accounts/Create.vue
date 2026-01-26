<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import SubmitButtons from '@/Components/SubmitButtons.vue';
import { useForm, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ref, computed } from 'vue';
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
    branches: {
        type: Array,
        required: true,
    },
    transactionTypes: {
        type: Array,
        required: true,
    },
});

useLazyProps(props, ['accountTypes'])
const isBaseCurrency = (currencyId) => {
    return (props.currencies?.data || []).some(
        (currency) => currency.id === currencyId && currency.is_base_currency
    );
};

const buildOpenings = () => {
    return (props.currencies?.data || []).map(currency => ({
        currency_id: currency.id,
        currency_name: currency.name,
        amount: '',
        rate: isBaseCurrency(currency.id) ? 1 : currency.exchange_rate,
        type: props.transactionTypes.find(type => type.id === 'debit')?.id,
    }));
};

const form = useForm({
    name: '',
    number: '',
    remark: '',
    account_type_id: null,
    openings: buildOpenings(),
});


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

const handleOpeningSelectChange = (index, value) => {
    form.openings[index].type = value;
    console.log('form', form.openings);
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
                                    :id="`transaction_type_${index}`"
                                    :floating-text="t('general.transaction_type')"
                                    :error="form.errors?.transaction_type"
                                    @update:modelValue="(value) => handleOpeningSelectChange(index, value)"
                                />

                                <div class="text-sm text-gray-700 border rounded-md p-2">
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
