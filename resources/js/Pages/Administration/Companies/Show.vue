<script setup>
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/Layout.vue';
import { Button } from '@/components/ui/button';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import { Edit, Save, X, Building2 } from 'lucide-vue-next';

const props = defineProps({
    company: Object,
    currencies: Object,
    locales: Object,
    businessTypes: Object,
    calendarTypes: Object,
    workingStyles: Object,
    errors: Object,
});

const isEditing = ref(false);
const originalData = ref({});
const logoPreview = ref(null);



const form = useForm({
    name_en: '',
    name_fa: '',
    name_pa: '',
    abbreviation: '',
    address: '',
    phone: '',
    country: '',
    city: '',
    logo: '',
    calendar_type: '',
    selected_calendar_type: '',
    currency_id: '',
    selected_currency: null,
    working_style: '',
    selected_working_style: '',
    business_type: '',
    selected_business_type: '',
    locale: '',
    email: '',
    website: '',
    invoice_description: '',
}, {
    forceFormData: true
});

// Initialize form with company data
watch(() => props.company, (company) => {
    if (company) {
        form.name_en = company.name_en || '';
        form.name_fa = company.name_fa || '';
        form.name_pa = company.name_pa || '';
        form.abbreviation = company.abbreviation || '';
        form.address = company.address || '';
        form.phone = company.phone || '';
        form.country = company.country || '';
        form.city = company.city || '';
        form.logo = company.logo || null;
        form.selected_calendar_type = props.calendarTypes.find(type => type.id === company.calendar_type)  || '';
        form.calendar_type = company.calendar_type || '';
        form.selected_currency = company.currency || null;
        form.selected_working_style = props.workingStyles.find(style => style.id === company.working_style) || '';
        form.working_style = company.working_style || '';
        form.selected_business_type = props.businessTypes.find(type => type.id === company.business_type)  || '';
        form.business_type = company.business_type || '';
        form.selected_locale = company.locale || '';
        form.locale = company.locale || '';
        form.currency_id = company.currency_id || '';
        form.email = company.email || '';
        form.website = company.website || '';
        form.invoice_description = company.invoice_description || '';

        originalData.value = {
            name_en: company.name_en || '',
            name_fa: company.name_fa || '',
            name_pa: company.name_pa || '',
            abbreviation: company.abbreviation || '',
            address: company.address || '',
            phone: company.phone || '',
            country: company.country || '',
            city: company.city || '',
            logo: company.logo || null,
            selected_calendar_type: company.calendar_type || '',
            calendar_type: company.calendar_type || '',
            selected_working_style: company.working_style || '',
            working_style: company.working_style || '',
            selected_business_type: company.business_type || '',
            business_type: company.business_type || '',
            selected_locale: company.locale || '',
            locale: company.locale || '',
            selected_business_type: company.business_type || '',
            selected_locale: company.locale || '',
            currency_id: company.currency_id || '',
            email: company.email || '',
            website: company.website || '',
            invoice_description: company.invoice_description || '',
        };
    }
}, { immediate: true });

const startEditing = () => {
    isEditing.value = true;
    originalData.value = { ...form.data() };
};

const cancelEditing = () => {
    isEditing.value = false;
    // Reset form to original data
    Object.keys(originalData.value).forEach(key => {
        form[key] = originalData.value[key];
    });
    // Clear logo preview
    logoPreview.value = null;
    form.clearErrors();
};

const saveChanges = () => {
    form.patch(route('company.update', props.company.id), {
        onSuccess: () => {
            isEditing.value = false;
            originalData.value = { ...form.data() };
            logoPreview.value = null; // Clear preview after successful save
        },
        onError: () => {
            // Errors will be handled by the form
        }
    });
};

const previewImage = (event) => {
    const file = event.target.files[0];

    if (file) {
        // Validate file size (2MB max)
        if (file.size > 2 * 1024 * 1024) {
            alert('File size must be less than 2MB');
            event.target.value = ''; // Clear the input
            return;
        }

        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
        if (!allowedTypes.includes(file.type)) {
            alert('Please select a valid image file (JPEG, PNG, JPG, or GIF)');
            event.target.value = ''; // Clear the input
            return;
        }

        // Set the file for upload
        form.logo = file;
        console.log('form', form);

        // Create preview
        const reader = new FileReader();
        reader.onload = (e) => {
            // Store preview URL for display
            logoPreview.value = e.target.result;
        };
        reader.readAsDataURL(file);
    } else {
        // Clear the form if no file selected
        form.logo = null;
        logoPreview.value = null;
    }
};

const handleSelectChange = (field, value) => {
        form[field] = value.id;
};

const getDisplayValue = (field) => {
    const value = props.company?.[field];
    if (!value) return '-';

    // Handle enum values by finding the display name
    if (field === 'calendar_type') {
        const option = props.calendarTypes.find(type => type.id === value);
        return option?.name || value;
    }
    if (field === 'working_style') {
        const option = props.workingStyles.find(style => style.id === value);
        return option?.name || value;
    }
    if (field === 'business_type') {
        const option = props.businessTypes.find(type => type.id === value);
        return option?.name || value;
    }
    if (field === 'locale') {
        const option = props.locales.find(locale => locale.id === value);
        return option?.name || value;
    }
    if (field === 'currency_id') {
        const option = props.currencies.data.find(currency => currency.id === value);
        return option?.name || value;
    }

    return value;
};
</script>

<template>
    <AppLayout title="Company Information">
        <div class="">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <Building2 class="w-8 h-8 text-primary" />
                    <h1 class="text-2xl font-bold text-gray-900">Company Information</h1>
                </div>
                <div class="flex gap-2">
                    <Button
                        v-if="!isEditing"
                        @click="startEditing"
                        variant="outline"
                        class="bg-primary text-white"
                    >
                        <Edit class="w-4 h-4" />
                        Edit
                    </Button>
                    <template v-else>
                        <Button
                            @click="saveChanges"
                            :disabled="form.processing"
                            class="flex items-center gap-2  hover:bg-primary/90"
                        >
                            <Save class="w-4 h-4" />
                            {{ form.processing ? 'Saving...' : 'Save' }}
                        </Button>
                        <Button
                            @click="cancelEditing"
                            variant="outline"
                            class="flex items-center gap-2"
                        >
                            <X class="w-4 h-4" />
                            Cancel
                        </Button>
                    </template>
                </div>
            </div>

            <!-- Company Information Card -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="p-6">
                    <form @submit.prevent="saveChanges">
                        <!-- Basic Information Section -->
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">
                                Basic Information
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <NextInput
                                        v-if="isEditing"
                                        v-model="form.name_en"
                                        :error="form.errors?.name_en"
                                        label="Company Name (English)"
                                        type="text"
                                    />
                                    <div v-else>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Company Name (English)
                                        </label>
                                        <div class="py-2 px-3 text-gray-900 bg-gray-50 rounded-md">
                                            {{ getDisplayValue('name_en') }}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <NextInput
                                        v-if="isEditing"
                                        v-model="form.name_fa"
                                        :error="form.errors?.name_fa"
                                        label="Company Name (Persian)"
                                        type="text"
                                    />
                                    <div v-else>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Company Name (Persian)
                                        </label>
                                        <div class="py-2 px-3 text-gray-900 bg-gray-50 rounded-md">
                                            {{ getDisplayValue('name_fa') }}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <NextInput
                                        v-if="isEditing"
                                        v-model="form.name_pa"
                                        :error="form.errors?.name_pa"
                                        label="Company Name (Pashto)"
                                        type="text"
                                    />
                                    <div v-else>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Company Name (Pashto)
                                        </label>
                                        <div class="py-2 px-3 text-gray-900 bg-gray-50 rounded-md">
                                            {{ getDisplayValue('name_pa') }}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <NextInput
                                        v-if="isEditing"
                                        v-model="form.abbreviation"
                                        :error="form.errors?.abbreviation"
                                        label="Abbreviation"
                                        type="text"
                                    />
                                    <div v-else>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Abbreviation
                                        </label>
                                        <div class="py-2 px-3 text-gray-900 bg-gray-50 rounded-md">
                                            {{ getDisplayValue('abbreviation') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Contact Information Section -->
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">
                                Contact Information
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <NextInput
                                        v-if="isEditing"
                                        v-model="form.email"
                                        :error="form.errors?.email"
                                        label="Email"
                                        type="email"
                                    />
                                    <div v-else>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Email
                                        </label>
                                        <div class="py-2 px-3 text-gray-900 bg-gray-50 rounded-md">
                                            {{ getDisplayValue('email') }}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <NextInput
                                        v-if="isEditing"
                                        v-model="form.phone"
                                        :error="form.errors?.phone"
                                        label="Phone"
                                        type="text"
                                    />
                                    <div v-else>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Phone
                                        </label>
                                        <div class="py-2 px-3 text-gray-900 bg-gray-50 rounded-md">
                                            {{ getDisplayValue('phone') }}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <NextInput
                                        v-if="isEditing"
                                        v-model="form.country"
                                        :error="form.errors?.country"
                                        label="Country"
                                        type="text"
                                    />
                                    <div v-else>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Country
                                        </label>
                                        <div class="py-2 px-3 text-gray-900 bg-gray-50 rounded-md">
                                            {{ getDisplayValue('country') }}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <NextInput
                                        v-if="isEditing"
                                        v-model="form.city"
                                        :error="form.errors?.city"
                                        label="City"
                                        type="text"
                                    />
                                    <div v-else>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            City
                                        </label>
                                        <div class="py-2 px-3 text-gray-900 bg-gray-50 rounded-md">
                                            {{ getDisplayValue('city') }}
                                        </div>
                                    </div>
                                </div>

                                <div class="md:col-span-2">
                                    <NextInput
                                        v-if="isEditing"
                                        v-model="form.address"
                                        :error="form.errors?.address"
                                        label="Address"
                                        type="text"
                                    />
                                    <div v-else>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Address
                                        </label>
                                        <div class="py-2 px-3 text-gray-900 bg-gray-50 rounded-md">
                                            {{ getDisplayValue('address') }}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <NextInput
                                        v-if="isEditing"
                                        v-model="form.website"
                                        :error="form.errors?.website"
                                        label="Website"
                                        type="url"
                                    />
                                    <div v-else>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Website
                                        </label>
                                        <div class="py-2 px-3 text-gray-900 bg-gray-50 rounded-md">
                                            {{ getDisplayValue('website') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Company Settings Section -->
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">
                                Company Settings
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <NextSelect
                                        v-if="isEditing"
                                        :options="businessTypes"
                                        v-model="form.selected_business_type"
                                        label-key="name"
                                        @update:modelValue="(value) => handleSelectChange('business_type', value)"
                                        :reduce="type => type.id"
                                        floating-text="Business Type"
                                        :error="form.errors?.business_type"
                                    />
                                    <div v-else>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Business Type
                                        </label>
                                        <div class="py-2 px-3 text-gray-900 bg-gray-50 rounded-md">
                                            {{ getDisplayValue('business_type') }}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <NextSelect
                                        v-if="isEditing"
                                        :options="calendarTypes"
                                        v-model="form.selected_calendar_type"
                                        label-key="name"
                                        value-key="id"
                                        @update:modelValue="(value) => handleSelectChange('calendar_type', value)"
                                        :reduce="type => type.id"
                                        floating-text="Calendar Type"
                                        :error="form.errors?.calendar_type"
                                    />
                                    <div v-else>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Calendar Type
                                        </label>
                                        <div class="py-2 px-3 text-gray-900 bg-gray-50 rounded-md">
                                            {{ getDisplayValue('calendar_type') }}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <NextSelect
                                        v-if="isEditing"
                                        :options="workingStyles"
                                        v-model="form.selected_working_style"
                                        label-key="name"
                                        value-key="id"
                                        @update:modelValue="(value) => handleSelectChange('working_style', value)"
                                        :reduce="type => type.id"
                                        floating-text="Working Style"
                                        :error="form.errors?.working_style"
                                    />
                                    <div v-else>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Working Style
                                        </label>
                                        <div class="py-2 px-3 text-gray-900 bg-gray-50 rounded-md">
                                            {{ getDisplayValue('working_style') }}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <NextSelect
                                        v-if="isEditing"
                                        :options="locales"
                                        v-model="form.selected_locale"
                                        label-key="name"
                                        value-key="id"
                                        @update:modelValue="(value) => handleSelectChange('locale', value)"
                                        :reduce="type => type.id"
                                        floating-text="Default Language"
                                        :error="form.errors?.locale"
                                    />
                                    <div v-else>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Default Language
                                        </label>
                                        <div class="py-2 px-3 text-gray-900 bg-gray-50 rounded-md">
                                            {{ getDisplayValue('locale') }}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <NextSelect
                                        v-if="isEditing"
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
                                    <div v-else>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Currency
                                        </label>
                                        <div class="py-2 px-3 text-gray-900 bg-gray-50 rounded-md">
                                            {{ getDisplayValue('currency_id') }}
                                        </div>
                                    </div>
                                </div>

                                <div class="md:col-span-2">
                                    <NextTextarea
                                        v-if="isEditing"
                                        v-model="form.invoice_description"
                                        name="invoice_description"
                                        label="Invoice Description"
                                    />
                                    <div v-else>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Invoice Description
                                        </label>
                                        <div class="py-2 px-3 text-gray-900 bg-gray-50 rounded-md min-h-[80px]">
                                            {{ getDisplayValue('invoice_description') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Company Logo Section -->
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">
                                Company Logo
                            </h3>
                            <div class="flex items-center gap-6">
                                <div class="flex-shrink-0">
                                    <div v-if="logoPreview || form.logo || company?.logo_url" class="w-24 h-24 overflow-hidden bg-gray-200 rounded-full">
                                        <img
                                            :src="logoPreview || (company?.logo_url || (company?.logo ? `/storage/${company.logo}` : null))"
                                            alt="Company Logo"
                                            class="object-cover w-full h-full"
                                        />
                                    </div>
                                    <div v-else class="flex items-center justify-center w-24 h-24 bg-gray-200 rounded-full">
                                        <Building2 class="w-12 h-12 text-gray-400" />
                                    </div>
                                </div>
                                <div v-if="isEditing">
                                    <label class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm cursor-pointer hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        <span>Upload Logo</span>
                                        <input
                                            id="logo"
                                            name="logo"
                                            type="file"
                                            class="sr-only"
                                            @change="previewImage"
                                            accept="image/*"
                                        >
                                    </label>
                                    <p class="mt-1 text-xs text-gray-500">PNG, JPG, GIF up to 2MB</p>
                                </div>
                                <div v-else class="text-sm text-gray-500">
                                    Logo can be updated in edit mode
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
