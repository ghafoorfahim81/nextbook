<script setup>
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/Layout.vue';
import { Button } from '@/Components/ui/button';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextTextarea from '@/Components/next/NextTextarea.vue';
import { Edit, Save, X, Building2, Upload } from 'lucide-vue-next';
import { useToast } from '@/Components/ui/toast/use-toast';
import { useI18n } from 'vue-i18n';
import { useAuth } from '@/composables/useAuth';
const { toast } = useToast();
const { t } = useI18n();
const { can } = useAuth();
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
const fileInput = ref(null);
const existingLogo = ref(null);



const form = useForm({
    name_en: '',
    name_fa: '',
    name_pa: '',
    abbreviation: '',
    address: '',
    phone: '',
    country: '',
    city: '',
    logo: null, // will only ever be a File or null
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
    _method: 'put',
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
        // Don't set form.logo to existing filename; it should only hold a File when user selects one
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

        // Initialize logo state
        existingLogo.value = company.logo || null;
        logoPreview.value = null;

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
            logo: company.logo || null,
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
    // Reset logo state
    logoPreview.value = null;
    existingLogo.value = originalData.value.logo || null;
    form.logo = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
    form.clearErrors();
};

const saveChanges = () => {
    // Ensure logo is only ever a File or null before submit
    if (!(form.logo instanceof File)) {
        form.logo = null;
    }

    form.post(route('company.update', props.company.id), {
        onSuccess: () => {
            isEditing.value = false;
            originalData.value = { ...form.data(), logo: existingLogo.value };
            logoPreview.value = null;
            setCalendarLocaleStorage(form.calendar_type)
            toast({
                title: t('general.success'),
                description: t('general.update_success', { name: 'Company' }),
                class: 'bg-green-600 text-white',
            });
        },
        onError: () => {
            // Errors will be handled by the form
        }
    });
};

const handleFileChange = (event) => {
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
        existingLogo.value = null;

        console.log('Logo selected:', file.name, file.size, file.type);

        // Create preview
        const reader = new FileReader();
        reader.onload = (e) => {
            logoPreview.value = e.target.result;
        };
        reader.readAsDataURL(file);
    } else {
        form.logo = null;
    }
};

const removeLogo = () => {
    form.logo = null;
    existingLogo.value = null;
    logoPreview.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const handleSelectChange = (field, value) => {
        form[field] = value;
        // if (field === 'calendar_type') {
        //     setCalendarLocaleStorage(value)
        // }
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
</script>

<template>
    <AppLayout :title="t('company.company_information')">
        <div class="">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <Building2 class="w-8 h-8 text-primary" />
                    <h1 class="text-2xl font-bold text-foreground">{{ t('company.company_information') }}</h1>
                </div>
                <div class="flex gap-2" v-if="can('companies.update')">
                    <Button
                        v-if="!isEditing"
                        @click="startEditing"
                        variant="outline"
                        class="bg-primary text-white"
                    >
                        <Edit class="w-4 h-4" />
                        {{ t('general.edit', { name: t('admin.company.company') }) }}
                    </Button>
                    <template v-else>
                        <Button
                            @click="saveChanges"
                            :disabled="form.processing"
                            class="flex items-center gap-2  hover:bg-primary/90"
                        >
                            <Save class="w-4 h-4" />
                            {{ form.processing ? t('general.saving') : t('general.save') }}
                        </Button>
                        <Button
                            @click="cancelEditing"
                            variant="outline"
                            class="flex items-center gap-2"
                        >
                            <X class="w-4 h-4" />
                            {{ t('general.cancel') }}
                        </Button>
                    </template>
                </div>
            </div>

            <!-- Company Information Card -->
            <div class="bg-card rounded-lg shadow-sm border border-border">
                <div class="p-6">
                    <form @submit.prevent="saveChanges">
                        <!-- Basic Information Section -->
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-foreground mb-4 pb-2 border-b border-border">
                                {{ t('company.basic_information') }}
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <NextInput
                                        v-if="isEditing"
                                        v-model="form.name_en"
                                        :error="form.errors?.name_en"
                                        :label="t('company.name_en')"
                                        type="text"
                                    />
                                    <div v-else>
                                        <label class="block text-sm font-medium text-muted-foreground mb-1">
                                            {{ t('company.name_en') }}
                                        </label>
                                        <div class="py-2 px-3 bg-muted text-foreground rounded-md">
                                            {{ getDisplayValue('name_en') }}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <NextInput
                                        v-if="isEditing"
                                        v-model="form.name_fa"
                                        :error="form.errors?.name_fa"
                                        :label="t('company.name_fa')"
                                        type="text"
                                    />
                                    <div v-else>
                                        <label class="block text-sm font-medium text-muted-foreground mb-1">
                                            {{ t('company.name_fa') }}
                                        </label>
                                        <div class="py-2 px-3 bg-muted text-foreground rounded-md">
                                            {{ getDisplayValue('name_fa') }}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <NextInput
                                        v-if="isEditing"
                                        v-model="form.name_pa"
                                        :error="form.errors?.name_pa"
                                        :label="t('company.name_pa')"
                                        type="text"
                                    />
                                    <div v-else>
                                        <label class="block text-sm font-medium text-muted-foreground mb-1">
                                            {{ t('company.name_pa') }}
                                        </label>
                                        <div class="py-2 px-3 bg-muted text-foreground rounded-md">
                                            {{ getDisplayValue('name_pa') }}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <NextInput
                                        v-if="isEditing"
                                        v-model="form.abbreviation"
                                        :error="form.errors?.abbreviation"
                                        :label="t('company.abbreviation')"
                                        type="text"
                                    />
                                    <div v-else>
                                        <label class="block text-sm font-medium text-muted-foreground mb-1">
                                            {{ t('company.abbreviation') }}
                                        </label>
                                        <div class="py-2 px-3 bg-muted text-foreground rounded-md">
                                            {{ getDisplayValue('abbreviation') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Contact Information Section -->
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-foreground mb-4 pb-2 border-b border-border">
                                {{ t('company.contact_information') }}
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <NextInput
                                        v-if="isEditing"
                                        v-model="form.email"
                                        :error="form.errors?.email"
                                        :label="t('general.email')"
                                        type="email"
                                    />
                                    <div v-else>
                                        <label class="block text-sm font-medium text-muted-foreground mb-1">
                                            {{ t('general.email') }}
                                        </label>
                                        <div class="py-2 px-3 bg-muted text-foreground rounded-md">
                                            {{ getDisplayValue('email') }}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <NextInput
                                        v-if="isEditing"
                                        v-model="form.phone"
                                        :error="form.errors?.phone"
                                        :label="t('general.phone')"
                                        type="text"
                                    />
                                    <div v-else>
                                        <label class="block text-sm font-medium text-muted-foreground mb-1">
                                            {{ t('general.phone') }}
                                        </label>
                                        <div class="py-2 px-3 bg-muted text-foreground rounded-md">
                                            {{ getDisplayValue('phone') }}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <NextInput
                                        v-if="isEditing"
                                        v-model="form.country"
                                        :error="form.errors?.country"
                                        :label="t('company.country')"
                                        type="text"
                                    />
                                    <div v-else>
                                        <label class="block text-sm font-medium text-muted-foreground mb-1">
                                            {{ t('company.country') }}
                                        </label>
                                        <div class="py-2 px-3 bg-muted text-foreground rounded-md">
                                            {{ getDisplayValue('country') }}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <NextInput
                                        v-if="isEditing"
                                        v-model="form.city"
                                        :error="form.errors?.city"
                                        :label="t('company.city')"
                                        type="text"
                                    />
                                    <div v-else>
                                        <label class="block text-sm font-medium text-muted-foreground mb-1">
                                            {{ t('company.city') }}
                                        </label>
                                        <div class="py-2 px-3 bg-muted text-foreground rounded-md">
                                            {{ getDisplayValue('city') }}
                                        </div>
                                    </div>
                                </div>

                                <div class="md:col-span-2">
                                    <NextInput
                                        v-if="isEditing"
                                        v-model="form.address"
                                        :error="form.errors?.address"
                                        :label="t('general.address')"
                                        type="text"
                                    />
                                    <div v-else>
                                        <label class="block text-sm font-medium text-muted-foreground mb-1">
                                            {{ t('company.address') }}
                                        </label>
                                        <div class="py-2 px-3 bg-muted text-foreground rounded-md">
                                            {{ getDisplayValue('address') }}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <NextInput
                                        v-if="isEditing"
                                        v-model="form.website"
                                        :error="form.errors?.website"
                                        :label="t('company.website')"
                                        type="url"
                                    />
                                    <div v-else>
                                        <label class="block text-sm font-medium text-muted-foreground mb-1">
                                            {{ t('company.website') }}
                                        </label>
                                        <div class="py-2 px-3 bg-muted text-foreground rounded-md">
                                            {{ getDisplayValue('website') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Company Settings Section -->
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-foreground mb-4 pb-2 border-b border-border">
                                {{ t('company.preferences') }}
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
                                        :floating-text="t('company.business_type')"
                                        :error="form.errors?.business_type"
                                    />
                                    <div v-else>
                                        <label class="block text-sm font-medium text-muted-foreground mb-1">
                                            {{ t('company.business_type') }}
                                        </label>
                                        <div class="py-2 px-3 bg-muted text-foreground rounded-md">
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
                                        :floating-text="t('company.calendar_type')"
                                        :error="form.errors?.calendar_type"
                                    />
                                    <div v-else>
                                        <label class="block text-sm font-medium text-muted-foreground mb-1">
                                            {{ t('company.calendar_type') }}
                                        </label>
                                        <div class="py-2 px-3 bg-muted text-foreground rounded-md">
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
                                        :floating-text="t('company.working_style')"
                                        :error="form.errors?.working_style"
                                    />
                                    <div v-else>
                                        <label class="block text-sm font-medium text-muted-foreground mb-1">
                                            {{ t('company.working_style') }}
                                        </label>
                                        <div class="py-2 px-3 bg-muted text-foreground rounded-md">
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
                                        :floating-text="t('company.locale')"
                                        :error="form.errors?.locale"
                                    />
                                    <div v-else>
                                        <label class="block text-sm font-medium text-muted-foreground mb-1">
                                            {{ t('company.locale') }}
                                        </label>
                                        <div class="py-2 px-3 bg-muted text-foreground rounded-md">
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
                                        :floating-text="t('company.currency')"
                                        :error="form.errors?.currency_id"
                                        :searchable="true"
                                        resource-type="currencies"
                                        :search-fields="['name', 'code', 'symbol']"
                                    />
                                    <div v-else>
                                        <label class="block text-sm font-medium text-muted-foreground mb-1">
                                            {{ t('company.currency') }}
                                        </label>
                                        <div class="py-2 px-3 bg-muted text-foreground rounded-md">
                                            {{ getDisplayValue('currency_id') }}
                                        </div>
                                    </div>
                                </div>

                                <div class="md:col-span-2">
                                    <NextTextarea
                                        v-if="isEditing"
                                        v-model="form.invoice_description"
                                        name="invoice_description"
                                        :label="t('company.invoice_description')"
                                    />
                                    <div v-else>
                                        <label class="block text-sm font-medium text-muted-foreground mb-1">
                                            {{ t('company.invoice_description') }}
                                        </label>
                                        <div class="py-2 px-3 bg-muted text-foreground rounded-md min-h-[80px]">
                                            {{ getDisplayValue('invoice_description') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Company Logo Section -->
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-foreground mb-4 pb-2 border-b border-border">
                                {{ t('company.logo') }}
                            </h3>
                            <div class="flex items-center gap-6">
                                <div class="flex-shrink-0">
                                    <div
                                        v-if="logoPreview || company?.logo_url || company?.logo"
                                        class="w-24 h-24 overflow-hidden bg-muted rounded-full"
                                    >
                                        <img
                                            :src="logoPreview || company?.logo_url || (company?.logo ? `/storage/${company.logo}` : null)"
                                            alt="Company Logo"
                                            class="object-cover w-full h-full"
                                        />
                                    </div>
                                    <div v-else class="flex items-center justify-center w-24 h-24 bg-muted rounded-full">
                                        <Building2 class="w-12 h-12 text-muted-foreground" />
                                    </div>
                                </div>
                                <div v-if="isEditing" class="flex-1">
                                    <div class="flex items-center gap-4">
                                        <input
                                            ref="fileInput"
                                            type="file"
                                            @change="handleFileChange"
                                            class="hidden"
                                            accept="image/*"
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            @click="() => fileInput?.click()"
                                        >
                                            <Upload class="w-4 h-4 mr-2" />
                                            {{ existingLogo ? t('general.change_file') : t('general.upload_file') }}
                                        </Button>
                                        <span v-if="form.logo" class="text-sm text-muted-foreground">
                                            {{ form.logo.name }}
                                            <button
                                                type="button"
                                                @click="removeLogo"
                                                class="ml-2 text-red-500 hover:text-red-700"
                                            >
                                                <X class="w-4 h-4 inline" />
                                            </button>
                                        </span>
                                        <span v-else-if="existingLogo" class="text-sm text-muted-foreground">
                                            {{ t('general.current_file') }}
                                            <button
                                                type="button"
                                                @click="removeLogo"
                                                class="ml-2 text-red-500 hover:text-red-700"
                                            >
                                                <X class="w-4 h-4 inline" />
                                            </button>
                                        </span>
                                    </div>
                                    <p class="mt-1 text-xs text-muted-foreground">{{ t('company.upload_logo_description') }}</p>
                                </div>
                                <div v-else class="text-sm text-muted-foreground">
                                    {{ t('company.logo_can_be_updated_in_edit_mode') }}
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
