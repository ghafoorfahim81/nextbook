<script setup>
/**
 * The company wizard (phase 5), in two modes.
 *
 * create: sign-up, and a super-admin adding another company from the companies
 * page. Both post to a route that ends in CompanySetupService.
 *
 * edit: the same steps, laid out the same way, filled in from an existing
 * company and saved with one request to companies.update. Every step is open
 * from the start, and the settings and invoice-design steps join the others.
 *
 * Every step is checked by the server before the wizard moves on — the store
 * routes are precognitive, and a step sends only its own fields for checking
 * (Precognition-Validate-Only). The set-up screen opens only once the whole
 * form has passed the same check, so it is never shown for a form the server
 * would turn away.
 */
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import axios from 'axios'
import {
    ArrowLeft, ArrowRight, BookOpen, Building2, Calculator, Car, Check, Coffee, Cpu, Factory, FlaskConical,
    Gem, Hotel, ImagePlus, Layers, Loader2, Pencil, Pill, Save, Shirt, ShoppingBasket, ShoppingCart,
    Smartphone, Store, Trash2, Tv, UtensilsCrossed,
} from 'lucide-vue-next'
import CompanySetupProgress from '@/Components/Onboarding/CompanySetupProgress.vue'
import CompanySettingsForm from '@/Components/Company/CompanySettingsForm.vue'
import InvoiceDesigner from '@/Pages/Preferences/InvoiceDesigner.vue'
import NextInput from '@/Components/next/NextInput.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import NextTextarea from '@/Components/next/NextTextarea.vue'
import { useToast } from '@/Components/ui/toast/use-toast'

// Inertia hands a page every shared prop; the wizard has several roots, so the
// ones it does not declare must not fall through onto them.
defineOptions({ inheritAttrs: false })

const props = defineProps({
    calendarTypes: { type: Array, default: () => [] },
    workingStyles: { type: Array, default: () => [] },
    businessTypes: { type: Array, default: () => [] },
    locales: { type: Array, default: () => [] },
    costingMethods: { type: Array, default: () => [] },
    // The catalog, not a company's currencies: someone signing up has none.
    currencyDefinitions: { type: Array, default: () => [] },
    // company.store for sign-up, companies.store from the companies page.
    submitRoute: { type: String, default: 'company.store' },
    // The company settings a new company starts from (phase 6).
    defaultSettings: { type: Object, default: () => ({}) },
    // Each trade's module switches, applied when the trade is picked.
    moduleDefaults: { type: Object, default: () => ({}) },

    mode: { type: String, default: 'create' },
    // edit: the company's values, as Administration\CompanyController::edit sends them.
    company: { type: Object, default: null },
    // edit: the company's own currencies, to pick its base currency from.
    companyCurrencies: { type: Array, default: () => [] },
    // edit: the settings it has now, and whether this user may change them.
    settings: { type: Object, default: () => ({}) },
    canEditSettings: { type: Boolean, default: true },
    cashAccounts: { type: Array, default: () => [] },
    invoiceThemes: { type: Array, default: () => [] },
    invoiceFormats: { type: Array, default: () => [] },
    invoiceFormatDefaults: { type: Object, default: () => ({}) },
    currentInvoiceTheme: { type: String, default: 'format1' },
})

const { t } = useI18n()
const { toast } = useToast()
const page = usePage()

const isEdit = props.mode === 'edit'
const isSignup = !isEdit && props.submitRoute === 'company.store'
const seed = props.company ?? {}
const saving = ref(false)
const invoiceTheme = ref(props.currentInvoiceTheme)

const form = reactive({
    name_en: seed.name_en ?? '',
    name_fa: seed.name_fa ?? '',
    name_pa: seed.name_pa ?? '',
    abbreviation: seed.abbreviation ?? '',
    business_type: seed.business_type ?? '',
    // A new company names a currency from the catalog; an existing one picks
    // among the currencies it already has.
    ...(isEdit ? { currency_id: seed.currency_id ?? '' } : { base_currency: 'AFN' }),
    locale: seed.locale ?? (page.props.locale || 'fa'),
    calendar_type: seed.calendar_type ?? 'jalali',
    working_style: seed.working_style ?? 'normal',
    costing_method: seed.costing_method ?? 'fifo',
    phone: seed.phone ?? '',
    email: seed.email ?? '',
    website: seed.website ?? '',
    country: seed.country ?? '',
    city: seed.city ?? '',
    address: seed.address ?? '',
    license_number: seed.license_number ?? '',
    license_issued_at: seed.license_issued_at ?? '',
    license_expires_at: seed.license_expires_at ?? '',
    invoice_description: seed.invoice_description ?? '',
})
const errors = ref({})
const logoFile = ref(null)
const logoPreview = ref(seed.logo_url ?? null)

// The company's own settings (numbering, posting, formats…), edited in place
// by CompanySettingsForm and posted as one JSON field.
const initialSettings = isEdit ? props.settings : props.defaultSettings
const companySettings = reactive(JSON.parse(JSON.stringify(initialSettings)))
// What "unchanged" means: the starting settings, with the chosen trade's
// modules once they are applied below.
const settingsBaseline = ref(JSON.stringify(initialSettings))
const settingsChanged = computed(() => JSON.stringify(companySettings) !== settingsBaseline.value)

// A new company starts with its trade's modules on (business_profiles.php):
// picking a trade applies them, until the user switches one themselves.
watch(() => form.business_type, (type) => {
    const defaults = props.moduleDefaults?.[type]
    if (isEdit || !defaults) return
    if (JSON.stringify(companySettings) !== settingsBaseline.value) return

    companySettings.modules = { ...defaults }
    settingsBaseline.value = JSON.stringify(companySettings)
})
const settingsLocked = isEdit && !props.canEditSettings
// The server names them settings.<key>; the form wants <key>.
const settingsErrors = computed(() => Object.fromEntries(
    Object.entries(errors.value)
        .filter(([key]) => key.startsWith('settings.'))
        .map(([key, message]) => [key.slice('settings.'.length), message])
))

// The fields each step sends for checking; the review step sends everything.
// "settings" stands for every rule under it (CompanyStoreRequest).
const STEPS = [
    { key: 'identity', fields: ['name_en', 'name_fa', 'name_pa', 'abbreviation', 'logo', 'business_type'] },
    { key: 'settings', fields: [isEdit ? 'currency_id' : 'base_currency', 'locale', 'calendar_type', 'working_style', 'costing_method'] },
    {
        key: 'contact',
        fields: ['phone', 'email', 'website', 'country', 'city', 'address', 'license_number',
            'license_issued_at', 'license_expires_at', 'invoice_description'],
    },
    { key: 'company_settings', fields: ['settings'] },
    ...(isEdit ? [{ key: 'invoice', fields: [] }] : []),
    { key: 'review', fields: [] },
]
const belongsTo = (key, field) => key === field || key.startsWith(`${field}.`)
const step = ref(0)
// An existing company has nothing to unlock: every step is open.
const highestReached = ref(isEdit ? STEPS.length - 1 : 0)
const checking = ref(false)

const BUSINESS_ICONS = {
    pharmacy: Pill, supermarket: ShoppingCart, grocery: ShoppingBasket, manufacturing: Factory,
    retail: Store, accounting: Calculator, automobile: Car, book: BookOpen, clothing: Shirt,
    hotel: Hotel, carpet: Layers, electronics: Tv, computer: Cpu, jewellery: Gem, mobile: Smartphone,
    pharma_manufacturing: FlaskConical, ply_carpet: Layers, restaurant: UtensilsCrossed,
}
const businessIcon = (id) => BUSINESS_ICONS[id] ?? Coffee

const labelOf = (options, id) => options.find((option) => option.id === id)?.name ?? null
const currencyLabel = computed(() => isEdit
    ? (props.companyCurrencies.find((currency) => currency.id === form.currency_id)?.display_name ?? '')
    : (labelOf(props.currencyDefinitions, form.base_currency) ?? form.base_currency))
const currencyShort = computed(() => (isEdit ? currencyLabel.value : form.base_currency) || '—')
const displayName = computed(() => {
    if (form.locale === 'fa' && form.name_fa) return form.name_fa
    if (form.locale === 'ps' && form.name_pa) return form.name_pa
    return form.name_en || (isEdit ? seed.name : '') || t('company.wizard.title_new')
})
const initials = computed(() => (form.name_en || seed.name || '?').split(/\s+/).filter(Boolean).slice(0, 2)
    .map((word) => word[0]).join('').toUpperCase())

function chooseLogo(event) {
    const file = event.target.files?.[0]
    if (!file) return
    logoFile.value = file
    const reader = new FileReader()
    reader.onload = (e) => { logoPreview.value = e.target.result }
    reader.readAsDataURL(file)
    event.target.value = ''
}

function pickBusinessType(id) {
    form.business_type = id
    const { business_type, ...rest } = errors.value
    errors.value = rest
}

function removeLogo() {
    logoFile.value = null
    logoPreview.value = null
}

function payload() {
    const data = new FormData()
    Object.entries(form).forEach(([key, value]) => {
        if (value !== null && (isEdit || value !== '')) data.append(key, value)
    })
    if (logoFile.value) data.append('logo', logoFile.value)
    if (!settingsLocked) data.append('settings', JSON.stringify(companySettings))
    if (isEdit) data.append('_method', 'PUT')
    return data
}

const firstErrors = (raw) => Object.fromEntries(
    Object.entries(raw ?? {}).map(([key, messages]) => [key, Array.isArray(messages) ? messages[0] : messages])
)

/**
 * Ask the server whether these fields would pass, without creating anything.
 * Resolves true when they do; otherwise shows the errors and resolves false.
 */
async function precheck(fields) {
    checking.value = true
    try {
        await axios.post(route(props.submitRoute), payload(), {
            headers: {
                Accept: 'application/json',
                Precognition: 'true',
                ...(fields.length ? { 'Precognition-Validate-Only': fields.join(',') } : {}),
            },
        })
        errors.value = Object.fromEntries(Object.entries(errors.value)
            .filter(([key]) => fields.length && !fields.some((field) => belongsTo(key, field))))
        return true
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = { ...errors.value, ...firstErrors(error.response.data.errors) }
            return false
        }
        toast({ title: t('general.error'), description: t('company.setup.failed'), variant: 'destructive' })
        return false
    } finally {
        checking.value = false
    }
}

async function next() {
    if (checking.value) return
    // An edit is checked once, when it is saved.
    if (!isEdit && !(await precheck(STEPS[step.value].fields))) return
    step.value = Math.min(step.value + 1, STEPS.length - 1)
    highestReached.value = Math.max(highestReached.value, step.value)
}

function back() {
    step.value = Math.max(step.value - 1, 0)
}

function goTo(index) {
    if (index <= highestReached.value && !checking.value && !setup.open) step.value = index
}

// The set-up screen. Same keys and order as CompanySetupService::SUMMARY_STEPS.
const SETUP_STEPS = [
    'company', 'branch', 'accounts', 'currencies', 'units', 'catalog',
    'cash_customer', 'expenses', 'hr', 'financial_period', 'warehouse', 'access',
]
const setup = reactive({ open: false, activeIndex: 0, summary: null })
let setupTimer = null
const stopSetupTimer = () => {
    if (setupTimer) clearInterval(setupTimer)
    setupTimer = null
}
onBeforeUnmount(stopSetupTimer)

async function create() {
    if (checking.value || setup.open) return

    // The whole form, checked first: the set-up screen opens only for a form
    // the server will accept.
    if (!(await precheck([]))) {
        const failing = failingStep()
        if (failing >= 0) step.value = failing
        return
    }

    Object.assign(setup, { open: true, activeIndex: 0, summary: null })
    // One request does the whole set-up; walk the steps while it works, and
    // hold the last one until the server has answered.
    setupTimer = setInterval(() => {
        if (setup.activeIndex < SETUP_STEPS.length - 1) setup.activeIndex++
    }, 650)

    try {
        const { data } = await axios.post(route(props.submitRoute), payload(), {
            headers: { Accept: 'application/json' },
        })
        stopSetupTimer()
        setup.activeIndex = SETUP_STEPS.length
        setup.summary = data.summary ?? {}
        setTimeout(() => router.visit(data.redirect), 1800)
    } catch (error) {
        stopSetupTimer()
        setup.open = false
        if (error.response?.status === 422) errors.value = firstErrors(error.response.data.errors)
        toast({ title: t('general.error'), description: t('company.setup.failed'), variant: 'destructive' })
    }
}

const failingStep = () => STEPS.findIndex((s) => s.fields.some((field) => Object.keys(errors.value).some((key) => belongsTo(key, field))))

/** Edit mode: one request saves the company and its settings. */
function save() {
    if (saving.value) return

    router.post(route('companies.update', props.company.id), payload(), {
        forceFormData: true,
        preserveScroll: true,
        onStart: () => { saving.value = true },
        onFinish: () => { saving.value = false },
        onError: (raw) => {
            errors.value = firstErrors(raw)
            const failing = failingStep()
            if (failing >= 0) step.value = failing
            toast({ title: t('general.error'), description: Object.values(errors.value)[0] || t('company.setup.failed'), variant: 'destructive' })
        },
    })
}

async function selectInvoiceTheme(theme) {
    try {
        await axios.patch('/company/invoice-theme', { theme })
        invoiceTheme.value = theme
        toast({ title: t('general.success'), description: t('preferences.preferences_saved'), class: 'bg-green-600 text-white' })
    } catch (error) {
        toast({
            title: t('general.error'),
            description: error?.response?.data?.message || t('preferences.invoice_designer.save_error'),
            variant: 'destructive',
        })
    }
}

const reviewSections = computed(() => [
    {
        step: 0,
        title: t('company.wizard.steps.identity'),
        rows: [
            [t('company.name_en'), form.name_en],
            [t('company.name_fa'), form.name_fa],
            [t('company.name_pa'), form.name_pa],
            [t('company.abbreviation'), form.abbreviation],
            [t('company.business_type'), labelOf(props.businessTypes, form.business_type)],
        ],
    },
    {
        step: 1,
        title: t('company.wizard.steps.settings'),
        rows: [
            [t('company.currency'), currencyLabel.value],
            [t('company.wizard.language'), labelOf(props.locales, form.locale)],
            [t('company.wizard.calendar'), labelOf(props.calendarTypes, form.calendar_type)],
            [t('company.wizard.working_style'), labelOf(props.workingStyles, form.working_style)],
            [t('company.costing_method'), labelOf(props.costingMethods, form.costing_method)],
        ],
    },
    {
        step: 2,
        title: t('company.wizard.steps.contact'),
        rows: [
            [t('company.phone'), form.phone],
            [t('company.email'), form.email],
            [t('company.website'), form.website],
            [t('company.city'), [form.city, form.country].filter(Boolean).join(', ')],
            [t('company.address'), form.address],
            [t('company.wizard.license_number'), form.license_number],
            [t('company.wizard.license_issued_at'), form.license_issued_at],
            [t('company.wizard.license_expires_at'), form.license_expires_at],
        ],
    },
    {
        step: 3,
        title: t('company.wizard.steps.company_settings'),
        rows: [
            [t('company.settings.title'), settingsChanged.value ? t('company.wizard.settings_customised') : t('company.wizard.settings_defaults')],
            [t('preferences.sale.types.sale'), companySettings.sale?.invoice_prefix],
            [t('preferences.purchase.types.purchase'), companySettings.purchase?.invoice_prefix],
        ],
    },
])

const pageTitle = isEdit
    ? t('company.wizard.title_edit')
    : (isSignup ? t('company.wizard.title_signup') : t('company.wizard.title_new'))

const optionCard = (selected) => [
    'relative flex cursor-pointer items-center gap-3 rounded-xl border p-3 text-start text-sm transition',
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/50',
    selected
        ? 'border-primary bg-primary/5 text-foreground ring-1 ring-primary/40'
        : 'border-border bg-card text-muted-foreground hover:border-primary/40 hover:text-foreground',
]
</script>

<template>
    <Head :title="pageTitle" />

    <CompanySetupProgress
        v-if="!isEdit"
        :open="setup.open"
        :steps="SETUP_STEPS"
        :active-index="setup.activeIndex"
        :summary="setup.summary"
    />

    <div class="min-h-screen bg-gradient-to-br from-primary/10 via-background to-amber-500/5 px-4 py-8 sm:px-6 lg:py-12">
        <div class="w-full">
            <Link
                v-if="isEdit"
                :href="route('companies.show', company.id)"
                class="mb-6 inline-flex items-center gap-1.5 text-sm text-muted-foreground transition hover:text-foreground"
            >
                <ArrowLeft class="size-4 rtl:rotate-180" /> {{ t('company.wizard.back_to_company') }}
            </Link>
            <Link
                v-else-if="!isSignup"
                :href="route('workspace.companies')"
                class="mb-6 inline-flex items-center gap-1.5 text-sm text-muted-foreground transition hover:text-foreground"
            >
                <ArrowLeft class="size-4 rtl:rotate-180" /> {{ t('company.wizard.back_to_companies') }}
            </Link>

            <div class="grid gap-6 lg:grid-cols-[300px_1fr] 2xl:grid-cols-[340px_1fr]">
                <!-- Steps and a live preview of what is being created. -->
                <aside class="space-y-6">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-foreground">{{ pageTitle }}</h1>
                        <p class="mt-2 text-sm text-muted-foreground">{{ isEdit ? t('company.wizard.subtitle_edit') : t('company.wizard.subtitle') }}</p>
                    </div>

                    <ol class="space-y-1">
                        <li v-for="(s, index) in STEPS" :key="s.key">
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-start transition"
                                :class="[
                                    index === step ? 'bg-card shadow-sm ring-1 ring-border' : '',
                                    index <= highestReached ? 'cursor-pointer hover:bg-card/70' : 'cursor-default opacity-60',
                                ]"
                                :disabled="index > highestReached"
                                @click="goTo(index)"
                            >
                                <span
                                    class="flex size-8 shrink-0 items-center justify-center rounded-full border text-sm font-semibold transition"
                                    :class="index < step
                                        ? 'border-emerald-500 bg-emerald-500 text-white'
                                        : index === step ? 'border-primary bg-primary text-primary-foreground' : 'border-border text-muted-foreground'"
                                >
                                    <Check v-if="index < step" class="size-4" />
                                    <template v-else>{{ index + 1 }}</template>
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-foreground">{{ t(`company.wizard.steps.${s.key}`) }}</span>
                                    <span class="block truncate text-xs text-muted-foreground">{{ t(`company.wizard.steps.${s.key}_hint`) }}</span>
                                </span>
                            </button>
                        </li>
                    </ol>

                    <div class="hidden rounded-2xl border border-border bg-card/80 p-5 shadow-sm backdrop-blur lg:block">
                        <div class="flex items-center gap-3">
                            <img v-if="logoPreview" :src="logoPreview" alt="" class="size-12 rounded-xl object-cover" />
                            <div v-else class="flex size-12 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-violet-400 text-base font-semibold text-white">
                                {{ initials }}
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-foreground">{{ displayName }}</p>
                                <p class="truncate text-xs text-muted-foreground">{{ labelOf(businessTypes, form.business_type) || '—' }}</p>
                            </div>
                        </div>
                        <dl class="mt-4 space-y-2 text-xs">
                            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">{{ t('company.currency') }}</dt><dd class="truncate font-medium text-foreground">{{ currencyShort }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">{{ t('company.wizard.calendar') }}</dt><dd class="truncate font-medium text-foreground">{{ labelOf(calendarTypes, form.calendar_type) }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">{{ t('company.wizard.language') }}</dt><dd class="truncate font-medium text-foreground">{{ labelOf(locales, form.locale) }}</dd></div>
                        </dl>
                    </div>
                </aside>

                <!-- The current step. -->
                <section class="flex min-h-[560px] flex-col rounded-2xl border border-border bg-card shadow-sm">
                    <div class="border-b border-border px-6 py-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-primary">
                            {{ t('company.wizard.step_of', { current: step + 1, total: STEPS.length }) }}
                        </p>
                        <h2 class="mt-1 text-lg font-semibold text-foreground">{{ t(`company.wizard.steps.${STEPS[step].key}`) }}</h2>
                    </div>

                    <!-- A div, not a form: the app autofocuses the first field of every form,
                         and on step 2 that is the currency select, which opens on focus. The
                         wizard posts with axios and moves on its buttons, so it needs no form. -->
                    <div class="flex-1 space-y-8 px-6 py-6">
                        <Transition mode="out-in" enter-active-class="transition duration-200 ease-out" enter-from-class="translate-y-2 opacity-0" leave-active-class="transition duration-150 ease-in" leave-to-class="opacity-0">
                            <!-- 1. Identity -->
                            <div v-if="step === 0" key="identity" class="space-y-8">
                                <div>
                                    <h3 class="text-sm font-semibold text-foreground">{{ t('company.wizard.names') }}</h3>
                                    <p class="mb-4 text-xs text-muted-foreground">{{ t('company.wizard.names_hint') }}</p>
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <NextInput v-model="form.name_en" :label="t('company.name_en')" is-required autofocus :error="errors.name_en" />
                                        <NextInput v-model="form.abbreviation" :label="t('company.abbreviation')" :error="errors.abbreviation" />
                                        <NextInput v-model="form.name_fa" :label="t('company.name_fa')" :error="errors.name_fa" />
                                        <NextInput v-model="form.name_pa" :label="t('company.name_pa')" :error="errors.name_pa" />
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-4">
                                    <img v-if="logoPreview" :src="logoPreview" alt="" class="size-16 rounded-xl object-cover ring-1 ring-border" />
                                    <div v-else class="flex size-16 items-center justify-center rounded-xl border-2 border-dashed border-border text-muted-foreground">
                                        <ImagePlus class="size-6" />
                                    </div>
                                    <div class="space-y-1">
                                        <div class="flex gap-2">
                                            <label class="inline-flex cursor-pointer items-center rounded-lg border border-border px-3 py-1.5 text-sm font-medium text-foreground transition hover:bg-muted">
                                                {{ t('company.wizard.logo_choose') }}
                                                <input type="file" accept="image/png,image/jpeg,image/gif" class="sr-only" @change="chooseLogo" />
                                            </label>
                                            <button v-if="logoFile" type="button" class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm text-red-600 transition hover:bg-red-500/10" @click="removeLogo">
                                                <Trash2 class="size-4" /> {{ t('company.wizard.logo_remove') }}
                                            </button>
                                        </div>
                                        <p class="text-xs text-muted-foreground">{{ t('company.wizard.logo_hint') }}</p>
                                        <p v-if="errors.logo" class="text-xs text-red-600">{{ errors.logo }}</p>
                                    </div>
                                </div>

                                <div>
                                    <h3 class="text-sm font-semibold text-foreground">
                                        {{ t('company.business_type') }} <span class="text-red-500">*</span>
                                    </h3>
                                    <p class="mb-4 text-xs text-muted-foreground">{{ t('company.wizard.business_type_hint') }}</p>
                                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-6" role="radiogroup">
                                        <button
                                            v-for="type in businessTypes"
                                            :key="type.id"
                                            type="button"
                                            role="radio"
                                            :aria-checked="form.business_type === type.id"
                                            :class="optionCard(form.business_type === type.id)"
                                            @click="pickBusinessType(type.id)"
                                        >
                                            <component :is="businessIcon(type.id)" class="size-5 shrink-0" :class="form.business_type === type.id ? 'text-primary' : ''" />
                                            <span class="line-clamp-2">{{ type.name }}</span>
                                        </button>
                                    </div>
                                    <p v-if="errors.business_type" class="mt-2 text-xs text-red-600">{{ errors.business_type }}</p>
                                </div>
                            </div>

                            <!-- 2. Books & language -->
                            <div v-else-if="step === 1" key="settings" class="space-y-8">
                                <div v-if="isEdit">
                                    <NextSelect
                                        :options="companyCurrencies"
                                        v-model="form.currency_id"
                                        label-key="display_name"
                                        value-key="id"
                                        :reduce="currency => currency.id"
                                        :floating-text="t('company.currency')"
                                        is-required
                                        :error="errors.currency_id"
                                        :searchable="true"
                                    />
                                    <p class="mt-2 text-xs text-muted-foreground">{{ t('company.wizard.currency_hint_edit') }}</p>
                                </div>
                                <div v-else>
                                    <NextSelect
                                        :options="currencyDefinitions"
                                        v-model="form.base_currency"
                                        label-key="display_name"
                                        value-key="id"
                                        :reduce="currency => currency.id"
                                        :floating-text="t('company.currency')"
                                        is-required
                                        :error="errors.base_currency"
                                        :searchable="true"
                                    />
                                    <p class="mt-2 text-xs text-muted-foreground">{{ t('company.wizard.base_currency_hint') }}</p>
                                </div>

                                <div v-for="group in [
                                    { field: 'locale', label: t('company.wizard.language'), options: locales, cols: 'sm:grid-cols-3' },
                                    { field: 'calendar_type', label: t('company.wizard.calendar'), options: calendarTypes, cols: 'sm:grid-cols-2' },
                                    { field: 'working_style', label: t('company.wizard.working_style'), options: workingStyles, cols: 'sm:grid-cols-2' },
                                    { field: 'costing_method', label: t('company.costing_method'), options: costingMethods, cols: 'sm:grid-cols-2', hint: t('company.wizard.costing_method_hint') },
                                ]" :key="group.field">
                                    <h3 class="text-sm font-semibold text-foreground">{{ group.label }}</h3>
                                    <p v-if="group.hint" class="text-xs text-muted-foreground">{{ group.hint }}</p>
                                    <div class="mt-3 grid gap-2" :class="group.cols" role="radiogroup">
                                        <button
                                            v-for="option in group.options"
                                            :key="option.id"
                                            type="button"
                                            role="radio"
                                            :aria-checked="form[group.field] === option.id"
                                            :class="optionCard(form[group.field] === option.id)"
                                            @click="form[group.field] = option.id"
                                        >
                                            <span
                                                class="flex size-4 shrink-0 items-center justify-center rounded-full border"
                                                :class="form[group.field] === option.id ? 'border-primary bg-primary' : 'border-muted-foreground/40'"
                                            >
                                                <span v-if="form[group.field] === option.id" class="size-1.5 rounded-full bg-white" />
                                            </span>
                                            <span>{{ option.name }}</span>
                                        </button>
                                    </div>
                                    <p v-if="errors[group.field]" class="mt-2 text-xs text-red-600">{{ errors[group.field] }}</p>
                                </div>
                            </div>

                            <!-- 3. Contact & licence -->
                            <div v-else-if="step === 2" key="contact" class="space-y-8">
                                <div>
                                    <h3 class="mb-4 text-sm font-semibold text-foreground">{{ t('company.wizard.contact') }}</h3>
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <NextInput v-model="form.phone" :label="t('company.phone')" :error="errors.phone" />
                                        <NextInput v-model="form.email" type="email" :label="t('company.email')" :error="errors.email" />
                                        <NextInput v-model="form.website" :label="t('company.website')" :error="errors.website" placeholder="https://" />
                                        <NextInput v-model="form.country" :label="t('company.country')" :error="errors.country" />
                                        <NextInput v-model="form.city" :label="t('company.city')" :error="errors.city" />
                                        <NextInput v-model="form.address" :label="t('company.address')" :error="errors.address" />
                                    </div>
                                </div>
                                <div>
                                    <h3 class="text-sm font-semibold text-foreground">{{ t('company.wizard.license') }}</h3>
                                    <p class="mb-4 text-xs text-muted-foreground">{{ t('company.wizard.license_hint') }}</p>
                                    <div class="grid gap-4 sm:grid-cols-3">
                                        <NextInput v-model="form.license_number" :label="t('company.wizard.license_number')" :error="errors.license_number" />
                                        <NextDate v-model="form.license_issued_at" :label="t('company.wizard.license_issued_at')" :error="errors.license_issued_at" />
                                        <NextDate v-model="form.license_expires_at" :label="t('company.wizard.license_expires_at')" :error="errors.license_expires_at" />
                                    </div>
                                </div>
                                <div>
                                    <NextTextarea v-model="form.invoice_description" :label="t('company.invoice_description')" :rows="3" :error="errors.invoice_description" />
                                    <p class="mt-2 text-xs text-muted-foreground">{{ t('company.wizard.invoice_description_hint') }}</p>
                                </div>
                            </div>

                            <!-- 4. Company settings, from the defaults -->
                            <div v-else-if="step === 3" key="company_settings" class="space-y-5">
                                <p class="text-sm text-muted-foreground">
                                    {{ settingsLocked ? t('company.wizard.settings_locked') : t('company.wizard.company_settings_intro') }}
                                </p>
                                <CompanySettingsForm
                                    :settings="companySettings"
                                    :errors="settingsErrors"
                                    :disabled="settingsLocked"
                                    :cash-accounts="cashAccounts"
                                />
                            </div>

                            <!-- 5. Invoice design (edit only) -->
                            <div v-else-if="isEdit && STEPS[step].key === 'invoice'" key="invoice" class="space-y-5">
                                <p class="text-sm text-muted-foreground">{{ t('company.wizard.invoice_intro') }}</p>
                                <InvoiceDesigner
                                    :invoice-themes="invoiceThemes"
                                    :invoice-formats="invoiceFormats"
                                    :invoice-format-defaults="invoiceFormatDefaults"
                                    :current-theme="invoiceTheme"
                                    :editable="true"
                                    @select-theme="selectInvoiceTheme"
                                />
                            </div>

                            <!-- 5. Review -->
                            <div v-else key="review" class="space-y-5">
                                <p class="text-sm text-muted-foreground">{{ t('company.wizard.review_hint') }}</p>
                                <div v-for="section in reviewSections" :key="section.step" class="rounded-xl border border-border">
                                    <div class="flex items-center justify-between border-b border-border px-4 py-2.5">
                                        <h3 class="text-sm font-semibold text-foreground">{{ section.title }}</h3>
                                        <button type="button" class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline" @click="goTo(section.step)">
                                            <Pencil class="size-3.5" /> {{ t('company.wizard.edit') }}
                                        </button>
                                    </div>
                                    <dl class="grid gap-x-6 gap-y-2 px-4 py-3 text-sm sm:grid-cols-2">
                                        <div v-for="[label, value] in section.rows" :key="label" class="flex justify-between gap-3">
                                            <dt class="text-muted-foreground">{{ label }}</dt>
                                            <dd class="truncate text-end font-medium" :class="value ? 'text-foreground' : 'text-muted-foreground/60'">
                                                {{ value || t('company.wizard.not_set') }}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>
                        </Transition>

                    </div>

                    <div class="flex items-center justify-between gap-3 border-t border-border px-6 py-4">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground disabled:invisible"
                            :disabled="step === 0 || checking"
                            @click="back"
                        >
                            <ArrowLeft class="size-4 rtl:rotate-180" /> {{ t('company.wizard.back') }}
                        </button>

                        <div class="flex items-center gap-2">
                        <button
                            v-if="isEdit && step < STEPS.length - 1"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-primary/40 px-4 py-2 text-sm font-medium text-primary transition hover:bg-primary/5 disabled:opacity-60"
                            :disabled="saving"
                            @click="save"
                        >
                            <Loader2 v-if="saving" class="size-4 motion-safe:animate-spin" />
                            <Save v-else class="size-4" /> {{ t('company.wizard.save') }}
                        </button>
                        <button
                            v-if="step < STEPS.length - 1"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-5 py-2 text-sm font-medium text-primary-foreground shadow-sm transition hover:bg-primary/90 disabled:opacity-60"
                            :disabled="checking"
                            @click="next"
                        >
                            <template v-if="checking"><Loader2 class="size-4 motion-safe:animate-spin" /> {{ t('company.wizard.checking') }}</template>
                            <template v-else>{{ t('company.wizard.next') }} <ArrowRight class="size-4 rtl:rotate-180" /></template>
                        </button>
                        <button
                            v-else-if="isEdit"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-5 py-2 text-sm font-medium text-primary-foreground shadow-sm transition hover:bg-primary/90 disabled:opacity-60"
                            :disabled="saving"
                            @click="save"
                        >
                            <template v-if="saving"><Loader2 class="size-4 motion-safe:animate-spin" /> {{ t('company.wizard.saving') }}</template>
                            <template v-else><Save class="size-4" /> {{ t('company.wizard.save') }}</template>
                        </button>
                        <button
                            v-else
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-5 py-2 text-sm font-medium text-primary-foreground shadow-sm transition hover:bg-primary/90 disabled:opacity-60"
                            :disabled="checking || setup.open"
                            @click="create"
                        >
                            <template v-if="checking"><Loader2 class="size-4 motion-safe:animate-spin" /> {{ t('company.wizard.checking') }}</template>
                            <template v-else><Building2 class="size-4" /> {{ t('company.wizard.create') }}</template>
                        </button>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
