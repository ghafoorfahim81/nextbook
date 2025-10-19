<script setup>
import {useForm } from '@inertiajs/vue3';
import { onMounted, computed } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextInput from '@/Components/next/NextInput.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import { useI18n } from 'vue-i18n';
const { t } = useI18n();
const { toast } = useToast();
import { useToast } from '@/Components/ui/toast/use-toast';
const props = defineProps({
    calendarTypes: {
        type: Array,
        required: true,
    },
    workingStyles: {
        type: Array,
        required: true,
    },
    businessTypes: {
        type: Array,
        required: true,
    },
    locales: {
        type: Array,
        required: true,
    },
    currencies: {
        type: Array,
        required: true,
    },
});

console.log(props.calendarTypes);

const form = useForm({
    name_en: '',
    name_fa: '',
    name_pa: '',
    abbreviation: '',
    address: '',
    phone: '',
    country: '',
    city: '',
    logo: null,
    calendar_type: '',
    selected_currency: null,
    selected_business_type: null,
    selected_calendar_type: null,
    selected_working_style: null,
    working_style: '',
    business_type: '',
    locale: 'en',
    currency_id: '',
    email: '',
    website: '',
    invoice_description: '',
});

// Find base currency
// const baseCurrency = computed(() => {
//     if (props.currencies?.data) {
//         return props.currencies.data.find(currency => currency.is_base_currency);
//     }
//     return null;
// });

// // Set base currency as default when component mounts
// onMounted(() => {
//     if (baseCurrency.value && !form.currency_id) {
//         form.currency_id = baseCurrency.value.id;
//     }
// });

const submit = () => {
    form.post(route('company.store'), {
        onSuccess: () => {
            form.reset();
        },
        onError: () => {
            toast({
                title: 'Error',
                description: 'Something went wrong',
                variant: 'destructive',
                class:'bg-red-600 text-white',
                duration: Infinity,
            });
        }
    });
};

const previewImage = (event) => {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            form.logo = e.target.result;
        };
        reader.readAsDataURL(file);
    }
};

const setCalendarLocaleStorage = (selected) => {
    try {
        const id = typeof selected === 'object' && selected ? selected.id : selected
        const item = Array.isArray(props.calendarTypes)
            ? props.calendarTypes.find(ct => (ct?.id ?? ct?.value) === id)
            : null
        const name = (item?.name || item?.label || item?.value || '').toString().toLowerCase()
        const locale = /miladi|gregorian|english|en/.test(name) ? 'en' : 'fa'
        if (typeof localStorage !== 'undefined') localStorage.setItem('calendar_type', locale)
    } catch (e) {
        // noop
    }
}

const handleSelectChange = (field, value) => {
    if (field === 'currency_id') {
        form.currency_id = value;
    }
    if (field === 'calendar_type') {
        setCalendarLocaleStorage(value)
    }
    form[field] = value;
};
</script>

<template>
    <div>
        <div class="py-12">
            <div class="sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <form @submit.prevent="submit" class="space-y-6">
                            <div class="mb-5 grid grid-cols-2 gap-x-2 gap-y-5">
                                <!-- Basic Information -->
                                <div class="space-y-4">
                                    <h3 class="text-lg font-medium">{{ t('company.basic_information') }}</h3>

                                    <NextInput :error="form.errors?.name_en" type="text" v-model="form.name_en" :label="t('company.name_en') + '*'" />
                                    <NextInput :error="form.errors?.name" type="text" v-model="form.name_fa" :label="t('company.name_fa')" />
                                    <NextInput :error="form.errors?.name" type="text" v-model="form.name_pa" :label="t('company.name_pa')" />

                                    <NextInput :error="form.errors?.name" type="text" v-model="form.abbreviation" :label="t('company.abbreviation')" />

                                </div>

                                <!-- Contact Information -->
                                <div class="space-y-4">
                                    <h3 class="text-lg font-medium">{{ t('company.contact_information') }}</h3>

                                    <NextInput :error="form.errors?.name" type="text" v-model="form.email" :label="t('company.email')" />
                                    <NextInput :error="form.errors?.name" type="text" v-model="form.phone" :label="t('company.phone')" />

                                    <div class="grid grid-cols-2 gap-4">
                                        <NextInput :error="form.errors?.name" type="text" v-model="form.country" :label="t('company.country')" />
                                        <NextInput :error="form.errors?.name" type="text" v-model="form.city" :label="t('company.city')" />
                                    </div>
                                    <NextInput :error="form.errors?.name" type="text" v-model="form.website" :label="t('company.website')" />
                                </div>
                            </div>

                            <!-- Settings -->
                            <div class="pt-6 mt-6 border-t border-gray-200">
                                <h3 class="text-lg font-medium">{{ t('company.settings') }}</h3>

                                <div class="grid grid-cols-1 gap-6 mt-4 md:grid-cols-3">

                                    <NextSelect
                                        :options="businessTypes"
                                        v-model="form.selected_business_type"
                                        label-key="name"
                                        value-key="id"
                                        @update:modelValue="(value) => handleSelectChange('business_type', value)"
                                        :floating-text="t('company.business_type')"
                                        :error="form.errors?.business_type"
                                    />
                                    <NextSelect
                                        :options="calendarTypes"
                                        v-model="form.selected_calendar_type"
                                        label-key="name"
                                        value-key="id"
                                        @update:modelValue="(value) => handleSelectChange('calendar_type', value)"
                                        :floating-text="t('company.calendar_type')"
                                        :error="form.errors?.calendar_type"
                                    />
                                    <NextSelect
                                        :options="workingStyles"
                                        v-model="form.selected_working_style"
                                        label-key="name"
                                        value-key="id"
                                        @update:modelValue="(value) => handleSelectChange('working_style', value)"
                                        :floating-text="t('company.working_style')"
                                        :error="form.errors?.working_style"
                                    />
                                </div>

                                <div class="grid grid-cols-1 gap-6 mt-4 md:grid-cols-3">
                                    <NextSelect
                                        :options="locales"
                                        v-model="form.locale"
                                        label-key="name"
                                        value-key="id"
                                        @update:modelValue="(value) => handleSelectChange('locale', value)"
                                        :floating-text="t('company.locale')"
                                        :error="form.errors?.locale"
                                    />
                                    <NextSelect
                                        :options="currencies.data"
                                        v-model="form.selected_currency"
                                        label-key="name"
                                        value-key="id"
                                        @update:modelValue="(value) => handleSelectChange('currency_id', value)"
                                        :reduce="currency => currency.id"
                                        :floating-text="t('company.currency')"
                                        :error="form.errors?.currency_id"
                                        :searchable="true"
                                        resource-type="currencies"
                                        :search-fields="['name', 'code', 'symbol']"
                                    />
                                </div>

                                <div class="mt-4">
                                    <NextTextarea
                                        v-model="form.invoice_description"
                                        rows="3"
                                        :label="t('company.invoice_description')"
                                        :floating-text="t('company.invoice_description')"
                                        :error="form.errors?.invoice_description"
                                    /> 
                                </div>
                            </div>

                            <!-- Logo Upload -->
                            <div class="pt-6 mt-6 border-t border-gray-200">
                                <h3 class="text-lg font-medium">{{ t('company.logo') }}</h3>

                                <div class="flex items-center mt-4">
                                    <div class="mr-4">
                                        <div v-if="form.logo" class="w-24 h-24 overflow-hidden bg-gray-200 rounded-full">
                                            <img :src="form.logo" alt="Logo Preview" class="object-cover w-full h-full" />
                                        </div>
                                        <div v-else class="flex items-center justify-center w-24 h-24 bg-gray-200 rounded-full">
                                            <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm cursor-pointer hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                            <span>{{ t('company.upload_logo') }}</span>
                                            <input id="logo" name="logo" type="file" class="sr-only" @change="previewImage" accept="image/*">
                                        </label>
                                        <p class="mt-1 text-xs text-gray-500">{{ t('company.upload_logo_description') }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end pt-6 mt-6 border-t border-gray-200">
                                <button type="submit" class="btn btn-primary px-4 py-2 rounded-md bg-primary text-white disabled:bg-gray-300" :disabled="form.processing">{{ t('general.create') }}</button>

                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
