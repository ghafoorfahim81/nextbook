<script setup>
import {useForm } from '@inertiajs/vue3';
import { onMounted, computed } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Button } from '@/Components/ui/button';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextInput from '@/Components/next/NextInput.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';

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
    console.log('form', form);
    form.post(route('company.store'), {
        onSuccess: () => {
            form.reset();
        },
        onError: () => {
            console.log(form.errors);
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

const handleSelectChange = (field, value) => {
    if(field === 'currency_id') {
        form.currency_id = value;
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
                                    <h3 class="text-lg font-medium">Basic Information</h3>

                                    <NextInput :error="form.errors?.name" type="text" v-model="form.name_en" label="Company Name (English)*" />
                                    <NextInput :error="form.errors?.name" type="text" v-model="form.name_fa" label="Company Name (Persian)" />
                                    <NextInput :error="form.errors?.name" type="text" v-model="form.name_pa" label="Company Name (Pashto)" />

                                    <NextInput :error="form.errors?.name" type="text" v-model="form.abbreviation" label="Abbreviation" />

                                </div>

                                <!-- Contact Information -->
                                <div class="space-y-4">
                                    <h3 class="text-lg font-medium">Contact Information</h3>

                                    <NextInput :error="form.errors?.name" type="text" v-model="form.email" label="Email" />
                                    <NextInput :error="form.errors?.name" type="text" v-model="form.phone" label="Phone" />

                                    <div class="grid grid-cols-2 gap-4">
                                        <NextInput :error="form.errors?.name" type="text" v-model="form.country" label="Country" />
                                        <NextInput :error="form.errors?.name" type="text" v-model="form.city" label="City" />
                                    </div>
                                    <NextInput :error="form.errors?.name" type="text" v-model="form.website" label="Website" />
                                </div>
                            </div>

                            <!-- Settings -->
                            <div class="pt-6 mt-6 border-t border-gray-200">
                                <h3 class="text-lg font-medium">Company Settings</h3>

                                <div class="grid grid-cols-1 gap-6 mt-4 md:grid-cols-3">
                                    <!-- <NextSelect
                                        v-model="form.parent_id"
                                        :options="categories"
                                        label-key="name"
                                        @update:modelValue="(value) => handleParentSelectChange(value)"
                                        value-key="id"
                                        id="parent"
                                        :floating-text="t('admin.shared.parent')"
                                        :error="form.errors?.parent_id"
                                        :searchable="true"
                                        resource-type="categories"
                                        :search-fields="['name']"
                                        /> -->

                                    <NextSelect
                                        :options="businessTypes"
                                        v-model="form.selected_business_type"
                                        label-key="name"
                                        value-key="id"
                                        @update:modelValue="(value) => handleSelectChange('business_type', value)"
                                        floating-text="Business Type"
                                        :error="form.errors?.business_type"
                                    />
                                    <NextSelect
                                        :options="calendarTypes"
                                        v-model="form.selected_calendar_type"
                                        label-key="name"
                                        value-key="id"
                                        @update:modelValue="(value) => handleSelectChange('calendar_type', value)"
                                        floating-text="Calendar Type"
                                        :error="form.errors?.calendar_type"
                                    />
                                    <NextSelect
                                        :options="workingStyles"
                                        v-model="form.selected_working_style"
                                        label-key="name"
                                        value-key="id"
                                        @update:modelValue="(value) => handleSelectChange('working_style', value)"
                                        floating-text="Working Style"
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
                                        floating-text="Default Language"
                                        :error="form.errors?.locale"
                                    />
                                    <NextSelect
                                        :options="currencies.data"
                                        v-model="form.selected_currency"
                                        label-key="name"
                                        value-key="id"
                                        @update:modelValue="(value) => handleSelectChange('currency_id', value)"
                                        :reduce="currency => currency.id"
                                        floating-text="Currency"
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
                                        floating-text="Invoice Description"
                                        :error="form.errors?.invoice_description"
                                    />
                                </div>
                            </div>

                            <!-- Logo Upload -->
                            <div class="pt-6 mt-6 border-t border-gray-200">
                                <h3 class="text-lg font-medium">Company Logo</h3>

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
                                            <span>Upload Logo</span>
                                            <input id="logo" name="logo" type="file" class="sr-only" @change="previewImage" accept="image/*">
                                        </label>
                                        <p class="mt-1 text-xs text-gray-500">PNG, JPG, GIF up to 2MB</p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end pt-6 mt-6 border-t border-gray-200">
                                <Button
                                    type="submit"
                                    variant="outline"
                                    :disabled="form.processing"
                                    class="bg-primary text-white"
                                >
                                    <span v-if="form.processing">Saving...</span>
                                    <span v-else>Save</span>
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
