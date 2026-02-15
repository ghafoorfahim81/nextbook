<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { ref, computed, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card'
import ModuleHelpButton from '@/Components/ModuleHelpButton.vue'
import { Label } from '@/Components/ui/label'
import { Input } from '@/Components/ui/input'
import { Switch } from '@/Components/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select'
import { Textarea } from '@/Components/ui/textarea'
import { Checkbox } from '@/Components/ui/checkbox'
import { useToast } from '@/Components/ui/toast'
import {
    Palette, Package, ShoppingCart, ShoppingBag, CreditCard, Calculator,
    Bell, Shield, Database, Globe, Monitor, RotateCcw, Download, Upload,
    Save, Plug, SlidersHorizontal as preferencesIcon
} from 'lucide-vue-next'

const props = defineProps({
    preferences: Object,
    defaultPreferences: Object,
    cashAccounts: Array,
    sidebarMenus: Array,
    timezones: Object,
    unitMeasures: { type: [Array, Object], required: true },
    categories: { type: [Array, Object], required: true },
    stores: { type: [Array, Object], required: true },
    sizes: { type: [Array, Object], required: true },
    currencies: { type: [Array, Object], required: true },
    ledgers: { type: [Array, Object], required: true },
})

const { t } = useI18n()
const { toast } = useToast()

const activeTab = ref('appearance')

const tabs = [
    { id: 'appearance', label: 'preferences.tabs.appearance', icon: Palette },
    { id: 'item_management', label: 'preferences.tabs.item_management', icon: Package },
    { id: 'sale', label: 'preferences.tabs.sale', icon: ShoppingCart },
    { id: 'purchase', label: 'preferences.tabs.purchase', icon: ShoppingBag },
    { id: 'receipt_payment', label: 'preferences.tabs.receipt_payment', icon: CreditCard },
    { id: 'tax_currency', label: 'preferences.tabs.tax_currency', icon: Calculator },
    { id: 'notifications', label: 'preferences.tabs.notifications', icon: Bell },
    { id: 'security', label: 'preferences.tabs.security', icon: Shield },
    { id: 'install_plugins', label: 'preferences.tabs.install_plugins', icon: Plug },
    { id: 'backup', label: 'preferences.tabs.backup', icon: Database },
    { id: 'localization', label: 'preferences.tabs.localization', icon: Globe },
    { id: 'display', label: 'preferences.tabs.display', icon: Monitor },
]

const form = useForm({ ...props.preferences })

const allUnitMeasures = computed(() => props.unitMeasures?.data ?? props.unitMeasures ?? [])
const allCategories = computed(() => props.categories?.data ?? props.categories ?? [])
const allStores = computed(() => props.stores?.data ?? props.stores ?? [])
const allSizes = computed(() => props.sizes?.data ?? props.sizes ?? [])
const allCurrencies = computed(() => props.currencies?.data ?? props.currencies ?? [])
const allLedgers = computed(() => props.ledgers?.data ?? props.ledgers ?? [])

const customerLedgers = computed(() => allLedgers.value.filter(l => l.type === 'customer'))
const supplierLedgers = computed(() => allLedgers.value.filter(l => l.type === 'supplier'))

const activeIds = (list) => (Array.isArray(list) ? list : []).filter(x => x?.is_active === true).map(x => x.id)

const pluginForm = useForm({
    unit_measures: activeIds(allUnitMeasures.value),
    categories: activeIds(allCategories.value),
    stores: activeIds(allStores.value),
    sizes: activeIds(allSizes.value),
    currencies: activeIds(allCurrencies.value),
    ledgers: activeIds(allLedgers.value),
})
const pluginSaving = computed(() => pluginForm.processing)

const togglePluginIds = (field, id, checked) => {
    const current = new Set(pluginForm[field] || [])
    if (checked) current.add(id)
    else current.delete(id)
    pluginForm[field] = Array.from(current)
}

const savePlugins = () => {
    pluginForm.put(route('preferences.install-plugins.update'), {
        preserveScroll: true,
        onSuccess: () => {
            toast({
                title: t('preferences.saved'),
                description: t('preferences.install_plugins.saved_description'),
                variant: 'success',
            })
        },
    })
}

const save = () => {
    form.put(route('preferences.update'), {
        preserveScroll: true,
        onSuccess: () => {
            toast({
                 title: t('preferences.saved'),
                description: t('preferences.saved_description'),
                variant: 'success'
            })
        // Store form values to local storage after a successful save
        localStorage.setItem('user_preferences', JSON.stringify(form));
        },
    })
}

const resetCategory = (category) => {
    if (confirm(t('preferences.confirm_reset'))) {
        router.post(route('preferences.reset', { category }), {}, {
            preserveScroll: true,
            onSuccess: () => {
                form[category] = props.defaultPreferences[category]
                toast({ title: t('preferences.reset_success') })
            },
        })
    }
}

const exportPreferences = () => {
    window.location.href = route('preferences.export')
}

const fileInput = ref(null)
const importPreferences = () => {
    fileInput.value?.click()
}

const handleFileUpload = (event) => {
    const file = event.target.files[0]
    if (!file) return

    const formData = new FormData()
    formData.append('file', file)

    router.post(route('preferences.import'), formData, {
        onSuccess: () => {
            toast({ title: t('preferences.import_success') })
        },
        onError: () => {
            toast({ title: t('preferences.import_error'), variant: 'destructive' })
        },
    })
}

// Transaction Types for Sale and Purchases
const saleTransactionTypes = ['sale', 'sale_order', 'sale_return', 'sale_quotation']
const purchaseTransactionTypes = ['purchase', 'purchase_order', 'purchase_return', 'purchase_quotation']
const activeSaleType = ref('sale')
const activePurchaseType = ref('purchase')

const generalFields = [
    { key: 'number', label: 'preferences.fields.number' },
    { key: 'date', label: 'preferences.fields.date' },
    { key: 'currency', label: 'preferences.fields.currency' },
    { key: 'type', label: 'preferences.fields.type' },
    { key: 'store', label: 'preferences.fields.store' },
]

const itemColumns = [
    { key: 'packing', label: 'preferences.fields.packing' },
    { key: 'colors', label: 'preferences.fields.colors' },
    { key: 'size', label: 'preferences.fields.size' },
    { key: 'brand', label: 'preferences.fields.brand' },
    { key: 'rack_no', label: 'preferences.fields.rack_no' },
    { key: 'category', label: 'preferences.fields.category' },
    { key: 'rates', label: 'preferences.fields.rates' },
    { key: 'batch', label: 'preferences.fields.batch' },
    { key: 'expiry', label: 'preferences.fields.expiry' },
    { key: 'on_hand', label: 'preferences.fields.on_hand' },
    { key: 'measure', label: 'preferences.fields.measure' },
    { key: 'discount', label: 'preferences.fields.discount' },
    { key: 'tax', label: 'preferences.fields.tax' },
    { key: 'free', label: 'preferences.fields.free' },


]

const itemManagementFields = [
    { key: 'code', label: 'preferences.item_fields.code' },
    { key: 'generic_name', label: 'preferences.item_fields.generic_name' },
    { key: 'packing', label: 'preferences.item_fields.packing' },
    { key: 'colors', label: 'preferences.item_fields.colors' },
    { key: 'size', label: 'preferences.item_fields.size' },
    { key: 'brand', label: 'preferences.item_fields.brand' },
    { key: 'minimum_stock', label: 'preferences.item_fields.minimum_stock' },
    { key: 'maximum_stock', label: 'preferences.item_fields.maximum_stock' },
    { key: 'file_upload', label: 'preferences.item_fields.file_upload' },
    { key: 'rate_a', label: 'preferences.item_fields.rate_a' },
    { key: 'rate_b', label: 'preferences.item_fields.rate_b' },
    { key: 'rate_c', label: 'preferences.item_fields.rate_c' },
    { key: 'barcode', label: 'preferences.item_fields.barcode' },
    { key: 'rack_no', label: 'preferences.item_fields.rack_no' },
    { key: 'fast_search', label: 'preferences.item_fields.fast_search' },
    { key: 'item_type', label: 'preferences.fields.item_type' },
    { key: 'sku', label: 'preferences.fields.sku' },
]

const receiptPaymentFields = [
    { key: 'number', label: 'preferences.fields.number' },
    { key: 'currency', label: 'preferences.fields.currency' },
    { key: 'cheque_number', label: 'preferences.receipt_fields.cheque_number' },
    { key: 'debit_account', label: 'preferences.receipt_fields.debit_account' },
    { key: 'ledger_old_balance', label: 'preferences.receipt_fields.ledger_old_balance' },
]
</script>

<template>
    <AppLayout :title="t('preferences.title')">
        <div class="container mx-auto py-6 space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <preferencesIcon class="w-8 h-8 text-primary" />
                    <div>
                        <h1 class="text-2xl font-bold">{{ t('preferences.title') }}</h1>
                        <p class="text-muted-foreground">{{ t('preferences.description') }}</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <ModuleHelpButton module="preferences" positionClass="" />
                    <input ref="fileInput" type="file" accept=".json" class="hidden" @change="handleFileUpload" />
                    <Button variant="outline" size="sm" @click="importPreferences">
                        <Upload class="w-4 h-4 mr-2" />
                        {{ t('preferences.import') }}
                    </Button>
                    <Button variant="outline" size="sm" @click="exportPreferences">
                        <Download class="w-4 h-4 mr-2" />
                        {{ t('preferences.export') }}
                    </Button>
                    <Button @click="save" :disabled="form.processing">
                        <Save class="w-4 h-4 mr-2" />
                        {{ t('preferences.save') }}
                    </Button>
                </div>
            </div>

            <div class="flex gap-6">
                <!-- Sidebar Navigation -->
                <div class="w-64 shrink-0">
                    <nav class="space-y-1 sticky top-4">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            @click="activeTab = tab.id"
                            :class="[
                                'w-full flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-lg transition-colors',
                                activeTab === tab.id
                                    ? 'bg-primary text-primary-foreground'
                                    : 'hover:bg-muted text-muted-foreground hover:text-foreground'
                            ]"
                        >
                            <component :is="tab.icon" class="w-5 h-5" />
                            {{ t(tab.label) }}
                        </button>
                    </nav>
                </div>

                <!-- Content Area -->
                <div class="flex-1 min-w-0">
                    <!-- Appearance preferences -->
                    <Card v-show="activeTab === 'appearance'" class="animate-in fade-in duration-200">
                        <CardHeader class="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>{{ t('preferences.tabs.appearance') }}</CardTitle>
                                <CardDescription>{{ t('preferences.appearance.description') }}</CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" @click="resetCategory('appearance')">
                                <RotateCcw class="w-4 h-4 mr-2" />
                                {{ t('preferences.reset') }}
                            </Button>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div class="grid grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label>{{ t('preferences.appearance.font_size') }}</Label>
                                    <Input v-model.number="form.appearance.font_size" type="number" min="10" max="24" />
                                </div>
                                <div class="space-y-2">
                                        <Label>{{ t('preferences.appearance.theme') }}</Label>
                                    <Select v-model="form.appearance.theme">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="light">{{ t('preferences.appearance.light') }}</SelectItem>
                                                <SelectItem value="dark">{{ t('preferences.appearance.dark') }}</SelectItem>
                                            <SelectItem value="system">{{ t('preferences.appearance.system') }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ t('preferences.appearance.decimal_places') }}</Label>
                                    <Select v-model="form.appearance.decimal_places">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem :value="0">0</SelectItem>
                                            <SelectItem :value="2">2</SelectItem>
                                            <SelectItem :value="3">3</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <Label>{{ t('preferences.appearance.sidebar_menus') }}</Label>
                                <div class="grid grid-cols-3 gap-3">
                                    <div v-for="menu in sidebarMenus" :key="menu.value" class="flex items-center gap-2">
                                        <Checkbox
                                            :id="`menu-${menu.value}`"
                                            :checked="form.appearance.sidebar_menus?.includes(menu.value)"
                                            @update:checked="(checked) => {
                                                if (!form.appearance.sidebar_menus) form.appearance.sidebar_menus = []
                                                if (checked) {
                                                    form.appearance.sidebar_menus.push(menu.value)
                                                } else {
                                                    form.appearance.sidebar_menus = form.appearance.sidebar_menus.filter(m => m !== menu.value)
                                                }
                                            }"
                                        />
                                        <Label :for="`menu-${menu.value}`" class="font-normal cursor-pointer">{{ menu.label }}</Label>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Item Management preferences -->
                    <Card v-show="activeTab === 'item_management'" class="animate-in fade-in duration-200">
                        <CardHeader class="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>{{ t('preferences.tabs.item_management') }}</CardTitle>
                                <CardDescription>{{ t('preferences.item_management.description') }}</CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" @click="resetCategory('item_management')">
                                <RotateCcw class="w-4 h-4 mr-2" />
                                {{ t('preferences.reset') }}
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-3">
                                <Label class="text-base font-medium">{{ t('preferences.item_management.visible_fields') }}</Label>
                                <div class="grid grid-cols-3 gap-4">
                                    <div v-for="field in itemManagementFields" :key="field.key" class="flex items-center gap-2">
                                        <Checkbox
                                            :id="`item-${field.key}`"
                                            :checked="form.item_management.visible_fields[field.key]"
                                            @update:checked="(checked) => form.item_management.visible_fields[field.key] = checked"
                                        />
                                        <Label :for="`item-${field.key}`" class="font-normal cursor-pointer">{{ t(field.label) }}</Label>
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-2 mt-3">
                                <Label>{{ t('preferences.tax_currency.spec_text') }}</Label>
                                <Input v-model="form.item_management.spec_text" rows="3" />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Sale preferences -->
                    <Card v-show="activeTab === 'sale'" class="animate-in fade-in duration-200">
                        <CardHeader class="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>{{ t('preferences.tabs.sale') }}</CardTitle>
                                <CardDescription>{{ t('preferences.sale.description') }}</CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" @click="resetCategory(activeSaleType)">
                                <RotateCcw class="w-4 h-4 mr-2" />
                                {{ t('preferences.reset') }}
                            </Button>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <!-- Transaction Type Tabs -->
                            <div class="flex gap-2 border-b pb-4">
                                <Button
                                    v-for="type in saleTransactionTypes"
                                    :key="type"
                                    :variant="activeSaleType === type ? 'default' : 'outline'"
                                    size="sm"
                                    @click="activeSaleType = type"
                                >
                                        {{ t(`preferences.sale.types.${type}`) }}
                                </Button>
                            </div>

                            <!-- General Fields -->
                            <div class="space-y-3">
                                <Label class="text-base font-medium">{{ t('preferences.sale.general_fields') }}</Label>
                                <div class="flex flex-wrap gap-4">
                                    <div v-for="field in generalFields" :key="field.key" class="flex items-center gap-2">
                                        <Checkbox
                                            :id="`sale-general-${field.key}`"
                                            :checked="form[activeSaleType]?.general_fields?.[field.key]"
                                            @update:checked="(checked) => {
                                                if (!form[activeSaleType].general_fields) form[activeSaleType].general_fields = {}
                                                form[activeSaleType].general_fields[field.key] = checked
                                            }"
                                        />
                                        <Label :for="`sale-general-${field.key}`" class="font-normal cursor-pointer">{{ t(field.label) }}</Label>
                                    </div>
                                </div>
                            </div>

                            <!-- Item Columns -->
                            <div class="space-y-3">
                                <Label class="text-base font-medium">{{ t('preferences.sale.item_columns') }}</Label>
                                <div class="flex flex-wrap gap-4">
                                    <div v-for="col in itemColumns" :key="col.key" class="flex items-center gap-2">
                                        <Checkbox
                                            :id="`sale-col-${col.key}`"
                                            :checked="form[activeSaleType]?.item_columns?.[col.key]"
                                            @update:checked="(checked) => {
                                                if (!form[activeSaleType].item_columns) form[activeSaleType].item_columns = {}
                                                form[activeSaleType].item_columns[col.key] = checked
                                            }"
                                        />
                                        <Label :for="`sale-col-${col.key}`" class="font-normal cursor-pointer">{{ t(col.label) }}</Label>
                                    </div>
                                </div>
                            </div>

                            <!-- Additional preferences -->
                            <div class="grid grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label>{{ t('preferences.sale.invoice_prefix') }}</Label>
                                    <Input v-model="form[activeSaleType].invoice_prefix" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ t('preferences.sale.start_number') }}</Label>
                                    <Input v-model.number="form[activeSaleType].start_number" type="number" min="1" />
                                </div>
                            </div>

                            <template v-if="activeSaleType === 'sale' || activeSaleType === 'sale_order'">
                                <div class="grid grid-cols-2 gap-6">
                                    <div class="space-y-2">
                                        <Label>{{ t('preferences.sale.due_days') }}</Label>
                                        <Input v-model.number="form[activeSaleType].due_days" type="number" min="0" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>{{ t('preferences.sale.terms') }}</Label>
                                        <Textarea v-model="form[activeSaleType].terms" rows="2" />
                                    </div>
                                </div>
                            </template>

                            <template v-if="activeSaleType === 'sale'">
                                <div class="grid grid-cols-3 gap-6">
                                    <div class="flex items-center gap-3">
                                        <Switch
                                            :model-value="form.sale.auto_reminders"
                                            @update:model-value="(v) => form.sale.auto_reminders = v"
                                        />
                                        <Label>{{ t('preferences.sale.auto_reminders') }}</Label>
                                    </div>
                                    <div class="space-y-2">
                                        <Label>{{ t('preferences.sale.reminder_days') }}</Label>
                                        <Input v-model.number="form.sale.reminder_days" type="number" min="1" :disabled="!form.sale.auto_reminders" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>{{ t('preferences.sale.late_fee_percentage') }}</Label>
                                        <Input v-model.number="form.sale.late_fee_percentage" type="number" min="0" max="100" step="0.1" />
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-6">
                                    <div class="space-y-2">
                                        <Label>{{ t('preferences.sale.tax_percentage') }}</Label>
                                        <Input v-model.number="form.sale.tax_percentage" type="number" min="0" max="100" step="0.1" />
                                    </div>
                                    <div class="flex items-center gap-3 pt-8">
                                        <Switch
                                            :model-value="form.sale.auto_calculate_tax"
                                            @update:model-value="(v) => form.sale.auto_calculate_tax = v"
                                        />
                                        <Label>{{ t('preferences.sale.auto_calculate_tax') }}</Label>
                                    </div>
                                </div>
                                <div class="flex gap-6">
                                    <div class="flex items-center gap-3">
                                        <Switch
                                            :model-value="form.sale.show_ledger_transactions"
                                            @update:model-value="(v) => form.sale.show_ledger_transactions = v"
                                        />
                                        <Label>{{ t('preferences.sale.show_ledger_transactions') }}</Label>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <Switch
                                            :model-value="form.sale.show_item_transactions"
                                            @update:model-value="(v) => form.sale.show_item_transactions = v"
                                        />
                                        <Label>{{ t('preferences.sale.show_item_transactions') }}</Label>
                                    </div>
                                </div>
                            </template>
                        </CardContent>
                    </Card>

                    <!-- Purchase preferences -->
                    <Card v-show="activeTab === 'purchase'" class="animate-in fade-in duration-200">
                        <CardHeader class="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>{{ t('preferences.tabs.purchase') }}</CardTitle>
                                <CardDescription>{{ t('preferences.purchase.description') }}</CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" @click="resetCategory(activePurchaseType)">
                                <RotateCcw class="w-4 h-4 mr-2" />
                                {{ t('preferences.reset') }}
                            </Button>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <!-- Transaction Type Tabs -->
                            <div class="flex gap-2 border-b pb-4">
                                <Button
                                    v-for="type in purchaseTransactionTypes"
                                    :key="type"
                                    :variant="activePurchaseType === type ? 'default' : 'outline'"
                                    size="sm"
                                    @click="activePurchaseType = type"
                                >
                                    {{ t(`preferences.purchase.types.${type}`) }}
                                </Button>
                            </div>

                            <!-- General Fields -->
                            <div class="space-y-3">
                                <Label class="text-base font-medium">{{ t('preferences.purchase.general_fields') }}</Label>
                                <div class="flex flex-wrap gap-4">
                                    <div v-for="field in generalFields" :key="field.key" class="flex items-center gap-2">
                                        <Checkbox
                                            :id="`purchase-general-${field.key}`"
                                            :checked="form[activePurchaseType]?.general_fields?.[field.key]"
                                            @update:checked="(checked) => {
                                                if (!form[activePurchaseType].general_fields) form[activePurchaseType].general_fields = {}
                                                form[activePurchaseType].general_fields[field.key] = checked
                                            }"
                                        />
                                        <Label :for="`purchase-general-${field.key}`" class="font-normal cursor-pointer">{{ t(field.label) }}</Label>
                                    </div>
                                </div>
                            </div>

                            <!-- Item Columns -->
                            <div class="space-y-3">
                                <Label class="text-base font-medium">{{ t('preferences.purchase.item_columns') }}</Label>
                                <div class="flex flex-wrap gap-4">
                                    <div v-for="col in itemColumns" :key="col.key" class="flex items-center gap-2">
                                        <Checkbox
                                            :id="`purchase-col-${col.key}`"
                                            :checked="form[activePurchaseType]?.item_columns?.[col.key]"
                                            @update:checked="(checked) => {
                                                if (!form[activePurchaseType].item_columns) form[activePurchaseType].item_columns = {}
                                                form[activePurchaseType].item_columns[col.key] = checked
                                            }"
                                        />
                                        <Label :for="`purchase-col-${col.key}`" class="font-normal cursor-pointer">{{ t(col.label) }}</Label>
                                    </div>
                                </div>
                            </div>

                            <!-- Additional preferences -->
                            <div class="grid grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label>{{ t('preferences.purchase.invoice_prefix') }}</Label>
                                    <Input v-model="form[activePurchaseType].invoice_prefix" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ t('preferences.purchase.start_number') }}</Label>
                                    <Input v-model.number="form[activePurchaseType].start_number" type="number" min="1" />
                                </div>
                            </div>

                            <template v-if="activePurchaseType === 'purchase'">
                                <div class="grid grid-cols-2 gap-6">
                                    <div class="space-y-2">
                                            <Label>{{ t('preferences.purchase.due_days') }}</Label>
                                        <Input v-model.number="form.purchase.due_days" type="number" min="0" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>{{ t('preferences.purchase.terms') }}</Label>
                                        <Textarea v-model="form.purchase.terms" rows="2" />
                                    </div>
                                </div>
                                <div class="grid grid-cols-3 gap-6">
                                    <div class="flex items-center gap-3">
                                        <Switch
                                                :model-value="form.purchase.auto_reminders"
                                            @update:model-value="(v) => form.purchase.auto_reminders = v"
                                        />
                                        <Label>{{ t('preferences.purchase.auto_reminders') }}</Label>
                                    </div>
                                    <div class="space-y-2">
                                        <Label>{{ t('preferences.purchase.reminder_days') }}</Label>
                                        <Input v-model.number="form.purchase.reminder_days" type="number" min="1" :disabled="!form.purchase.auto_reminders" />
                                    </div>
                                    <div class="space-y-2">
                                            <Label>{{ t('preferences.purchase.late_fee_percentage') }}</Label>
                                        <Input v-model.number="form.purchase.late_fee_percentage" type="number" min="0" max="100" step="0.1" />
                                    </div>
                                </div>
                                <div class="flex gap-6">
                                    <div class="flex items-center gap-3">
                                        <Switch
                                                :model-value="form.purchase.show_ledger_transactions"
                                            @update:model-value="(v) => form.purchase.show_ledger_transactions = v"
                                        />
                                        <Label>{{ t('preferences.purchase.show_ledger_transactions') }}</Label>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <Switch
                                            :model-value="form.purchase.show_item_transactions"
                                            @update:model-value="(v) => form.purchase.show_item_transactions = v"
                                        />
                                        <Label>{{ t('preferences.purchase.show_item_transactions') }}</Label>
                                    </div>
                                </div>
                            </template>
                        </CardContent>
                    </Card>

                    <!-- Receipt & Payment preferences -->
                    <Card v-show="activeTab === 'receipt_payment'" class="animate-in fade-in duration-200">
                        <CardHeader class="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>{{ t('preferences.tabs.receipt_payment') }}</CardTitle>
                                <CardDescription>{{ t('preferences.receipt_payment.description') }}</CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" @click="resetCategory('receipt_payment')">
                                <RotateCcw class="w-4 h-4 mr-2" />
                                {{ t('preferences.reset') }}
                            </Button>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div class="space-y-3">
                                <Label class="text-base font-medium">{{ t('preferences.receipt_payment.visible_fields') }}</Label>
                                <div class="flex flex-wrap gap-4">
                                    <div v-for="field in receiptPaymentFields" :key="field.key" class="flex items-center gap-2">
                                        <Checkbox
                                            :id="`receipt-${field.key}`"
                                            :checked="form.receipt_payment.visible_fields[field.key]"
                                            @update:checked="(checked) => form.receipt_payment.visible_fields[field.key] = checked"
                                        />
                                        <Label :for="`receipt-${field.key}`" class="font-normal cursor-pointer">{{ t(field.label) }}</Label>
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label>{{ t('preferences.receipt_payment.default_cash_account') }}</Label>
                                    <Select v-model="form.receipt_payment.default_cash_account">
                                        <SelectTrigger>
                                            <SelectValue :placeholder="t('preferences.receipt_payment.select_account')" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="account in cashAccounts" :key="account.id" :value="account.id">
                                                {{ account.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ t('preferences.receipt_payment.lock_after_days') }}</Label>
                                    <Input v-model.number="form.receipt_payment.lock_after_days" type="number" min="0" />
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-6">
                                <div class="flex items-center gap-3">
                                    <Switch
                                        :model-value="form.receipt_payment.auto_sequence"
                                        @update:model-value="(v) => form.receipt_payment.auto_sequence = v"
                                    />
                                    <Label>{{ t('preferences.receipt_payment.auto_sequence') }}</Label>
                                </div>
                                <div class="flex items-center gap-3">
                                    <Switch
                                        :model-value="form.receipt_payment.require_approval"
                                        @update:model-value="(v) => form.receipt_payment.require_approval = v"
                                    />
                                    <Label>{{ t('preferences.receipt_payment.require_approval') }}</Label>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Tax & Currency preferences -->
                    <Card v-show="activeTab === 'tax_currency'" class="animate-in fade-in duration-200">
                        <CardHeader class="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>{{ t('preferences.tabs.tax_currency') }}</CardTitle>
                                <CardDescription>{{ t('preferences.tax_currency.description') }}</CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" @click="resetCategory('tax_currency')">
                                <RotateCcw class="w-4 h-4 mr-2" />
                                {{ t('preferences.reset') }}
                            </Button>
                        </CardHeader>
                        <CardContent class="space-y-6">
                                <div class="flex flex-wrap gap-6">
                                <div class="flex items-center gap-3">
                                    <Switch
                                        :model-value="form.tax_currency.tax_plus"
                                        @update:model-value="(v) => form.tax_currency.tax_plus = v"
                                    />
                                    <Label>{{ t('preferences.tax_currency.tax_plus') }}</Label>
                                </div>
                                <div class="flex items-center gap-3">
                                    <Switch
                                        :model-value="form.tax_currency.tax_minus"
                                        @update:model-value="(v) => form.tax_currency.tax_minus = v"
                                    />
                                    <Label>{{ t('preferences.tax_currency.tax_minus') }}</Label>
                                </div>
                                <div class="flex items-center gap-3">
                                    <Switch
                                        :model-value="form.tax_currency.multi_currency_opening"
                                        @update:model-value="(v) => form.tax_currency.multi_currency_opening = v"
                                    />
                                    <Label>{{ t('preferences.tax_currency.multi_currency_opening') }}</Label>
                                </div>
                            </div>

                        </CardContent>
                    </Card>

                    <!-- Notification preferences -->
                    <Card v-show="activeTab === 'notifications'" class="animate-in fade-in duration-200">
                        <CardHeader class="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>{{ t('preferences.tabs.notifications') }}</CardTitle>
                                <CardDescription>{{ t('preferences.notifications.description') }}</CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" @click="resetCategory('notifications')">
                                <RotateCcw class="w-4 h-4 mr-2" />
                                {{ t('preferences.reset') }}
                            </Button>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-4">
                                <div class="flex items-center justify-between py-2 border-b">
                                    <div>
                                        <Label class="text-base">{{ t('preferences.notifications.email_notifications') }}</Label>
                                        <p class="text-sm text-muted-foreground">{{ t('preferences.notifications.email_notifications_desc') }}</p>
                                    </div>
                                    <Switch
                                        :model-value="form.notifications.email_notifications"
                                        @update:model-value="(v) => form.notifications.email_notifications = v"
                                    />
                                </div>
                                <div class="flex items-center justify-between py-2 border-b">
                                    <div>
                                        <Label class="text-base">{{ t('preferences.notifications.low_balance_alert') }}</Label>
                                        <p class="text-sm text-muted-foreground">{{ t('preferences.notifications.low_balance_alert_desc') }}</p>
                                    </div>
                                    <Switch
                                        :model-value="form.notifications.low_balance_alert"
                                        @update:model-value="(v) => form.notifications.low_balance_alert = v"
                                    />
                                </div>
                                <div class="flex items-center justify-between py-2 border-b">
                                    <div>
                                        <Label class="text-base">{{ t('preferences.notifications.overdue_invoice_alert') }}</Label>
                                        <p class="text-sm text-muted-foreground">{{ t('preferences.notifications.overdue_invoice_alert_desc') }}</p>
                                    </div>
                                    <Switch
                                        :model-value="form.notifications.overdue_invoice_alert"
                                        @update:model-value="(v) => form.notifications.overdue_invoice_alert = v"
                                    />
                                </div>
                                <div class="flex items-center justify-between py-2 border-b">
                                    <div>
                                        <Label class="text-base">{{ t('preferences.notifications.new_transaction_alert') }}</Label>
                                        <p class="text-sm text-muted-foreground">{{ t('preferences.notifications.new_transaction_alert_desc') }}</p>
                                    </div>
                                    <Switch
                                        :model-value="form.notifications.new_transaction_alert"
                                        @update:model-value="(v) => form.notifications.new_transaction_alert = v"
                                    />
                                </div>
                                <div class="flex items-center justify-between py-2 border-b">
                                    <div>
                                        <Label class="text-base">{{ t('preferences.notifications.daily_summary_report') }}</Label>
                                        <p class="text-sm text-muted-foreground">{{ t('preferences.notifications.daily_summary_report_desc') }}</p>
                                    </div>
                                    <Switch
                                        :model-value="form.notifications.daily_summary_report"
                                        @update:model-value="(v) => form.notifications.daily_summary_report = v"
                                    />
                                </div>
                                <div class="flex items-center justify-between py-2">
                                    <div>
                                        <Label class="text-base">{{ t('preferences.notifications.weekly_financial_summary') }}</Label>
                                        <p class="text-sm text-muted-foreground">{{ t('preferences.notifications.weekly_financial_summary_desc') }}</p>
                                    </div>
                                    <Switch
                                        :model-value="form.notifications.weekly_financial_summary"
                                        @update:model-value="(v) => form.notifications.weekly_financial_summary = v"
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Security preferences -->
                    <Card v-show="activeTab === 'security'" class="animate-in fade-in duration-200">
                        <CardHeader class="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>{{ t('preferences.tabs.security') }}</CardTitle>
                                <CardDescription>{{ t('preferences.security.description') }}</CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" @click="resetCategory('security')">
                                <RotateCcw class="w-4 h-4 mr-2" />
                                {{ t('preferences.reset') }}
                            </Button>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div class="grid grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label>{{ t('preferences.security.session_timeout') }}</Label>
                                    <Input v-model.number="form.security.session_timeout" type="number" min="5" max="1440" />
                                    <p class="text-sm text-muted-foreground">{{ t('preferences.security.session_timeout_hint') }}</p>
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ t('preferences.security.login_attempts_limit') }}</Label>
                                    <Input v-model.number="form.security.login_attempts_limit" type="number" min="3" max="10" />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label>{{ t('preferences.security.password_min_length') }}</Label>
                                    <Input v-model.number="form.security.password_min_length" type="number" min="6" max="32" />
                                </div>
                                <div class="flex items-center gap-3 pt-8">
                                    <Switch
                                        :model-value="form.security.password_special_chars"
                                        @update:model-value="(v) => form.security.password_special_chars = v"
                                    />
                                    <Label>{{ t('preferences.security.password_special_chars') }}</Label>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <Switch
                                    :model-value="form.security.two_factor_auth"
                                    @update:model-value="(v) => form.security.two_factor_auth = v"
                                />
                                <Label>{{ t('preferences.security.two_factor_auth') }}</Label>
                            </div>
                            <div class="border-t pt-6 space-y-4">
                                <Label class="text-base font-medium">{{ t('preferences.security.report_lock') }}</Label>
                                <div class="flex items-center gap-3">
                                    <Switch
                                        :model-value="form.security.lock_reports"
                                        @update:model-value="(v) => form.security.lock_reports = v"
                                    />
                                    <Label>{{ t('preferences.security.lock_reports') }}</Label>
                                </div>
                                <div v-if="form.security.lock_reports" class="space-y-2 max-w-sm">
                                    <Label>{{ t('preferences.security.lock_password') }}</Label>
                                    <Input v-model="form.security.lock_password" type="password" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Install Plugins -->
                    <Card v-show="activeTab === 'install_plugins'" class="animate-in fade-in duration-200">
                        <CardHeader class="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>{{ t('preferences.tabs.install_plugins') }}</CardTitle>
                                <CardDescription>{{ t('preferences.install_plugins.description') }}</CardDescription>
                            </div>
                            <Button variant="default" size="sm" @click="savePlugins" :disabled="pluginSaving">
                                <Save class="w-4 h-4 mr-2" />
                                {{ t('preferences.install_plugins.save') }}
                            </Button>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div class="space-y-2">
                                <Label class="text-base font-medium">{{ t('preferences.install_plugins.measures_title') }}</Label>
                                <p class="text-sm text-muted-foreground">{{ t('preferences.install_plugins.measures_hint') }}</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                    <div
                                        v-for="m in allUnitMeasures"
                                        :key="m.id"
                                        class="flex items-center gap-2 rounded-md border p-2"
                                    >
                                        <Checkbox
                                            :id="`fav-measure-${m.id}`"
                                            :checked="pluginForm.unit_measures?.includes(m.id)"
                                            @update:checked="(checked) => togglePluginIds('unit_measures', m.id, checked)"
                                        />
                                        <Label :for="`fav-measure-${m.id}`" class="font-normal cursor-pointer">
                                            {{ m.name }}
                                        </Label>
                                        <span v-if="m.symbol" class="ml-auto text-xs text-muted-foreground">
                                            {{ m.symbol }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label class="text-base font-medium">{{ t('preferences.install_plugins.stores_title') }}</Label>
                                <p class="text-sm text-muted-foreground">{{ t('preferences.install_plugins.stores_hint') }}</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                    <div
                                        v-for="s in allStores"
                                        :key="s.id"
                                        class="flex items-center gap-2 rounded-md border p-2"
                                    >
                                        <Checkbox
                                            :id="`fav-store-${s.id}`"
                                            :checked="pluginForm.stores?.includes(s.id)"
                                            @update:checked="(checked) => togglePluginIds('stores', s.id, checked)"
                                        />
                                        <Label :for="`fav-store-${s.id}`" class="font-normal cursor-pointer">
                                            {{ s.name }}
                                        </Label>
                                        <span v-if="s.is_main === true" class="ml-auto text-xs text-muted-foreground">
                                            {{ t('preferences.install_plugins.main_badge') }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label class="text-base font-medium">{{ t('preferences.install_plugins.categories_title') }}</Label>
                                <p class="text-sm text-muted-foreground">{{ t('preferences.install_plugins.categories_hint') }}</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                    <div
                                        v-for="c in allCategories"
                                        :key="c.id"
                                        class="flex items-center gap-2 rounded-md border p-2"
                                    >
                                        <Checkbox
                                            :id="`fav-category-${c.id}`"
                                            :checked="pluginForm.categories?.includes(c.id)"
                                            @update:checked="(checked) => togglePluginIds('categories', c.id, checked)"
                                        />
                                        <Label :for="`fav-category-${c.id}`" class="font-normal cursor-pointer">
                                            {{ c.name }}
                                        </Label>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label class="text-base font-medium">{{ t('preferences.install_plugins.sizes_title') }}</Label>
                                <p class="text-sm text-muted-foreground">{{ t('preferences.install_plugins.sizes_hint') }}</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                    <div
                                        v-for="z in allSizes"
                                        :key="z.id"
                                        class="flex items-center gap-2 rounded-md border p-2"
                                    >
                                        <Checkbox
                                            :id="`fav-size-${z.id}`"
                                            :checked="pluginForm.sizes?.includes(z.id)"
                                            @update:checked="(checked) => togglePluginIds('sizes', z.id, checked)"
                                        />
                                        <Label :for="`fav-size-${z.id}`" class="font-normal cursor-pointer">
                                            {{ z.name }}
                                        </Label>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label class="text-base font-medium">{{ t('preferences.install_plugins.currencies_title') }}</Label>
                                <p class="text-sm text-muted-foreground">{{ t('preferences.install_plugins.currencies_hint') }}</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                    <div
                                        v-for="cu in allCurrencies"
                                        :key="cu.id"
                                        class="flex items-center gap-2 rounded-md border p-2"
                                    >
                                        <Checkbox
                                            :id="`fav-currency-${cu.id}`"
                                            :checked="pluginForm.currencies?.includes(cu.id)"
                                            @update:checked="(checked) => togglePluginIds('currencies', cu.id, checked)"
                                        />
                                        <Label :for="`fav-currency-${cu.id}`" class="font-normal cursor-pointer">
                                            {{ cu.code || cu.name }}
                                            <span v-if="cu.is_base_currency === true" class="text-xs text-muted-foreground">
                                                ({{ t('preferences.install_plugins.base_badge') }})
                                            </span>
                                        </Label>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label class="text-base font-medium">{{ t('preferences.install_plugins.ledgers_title') }}</Label>
                                <p class="text-sm text-muted-foreground">{{ t('preferences.install_plugins.ledgers_hint') }}</p>

                                <div class="space-y-3">
                                    <div class="space-y-2">
                                        <Label class="text-sm font-medium">{{ t('preferences.install_plugins.customers_title') }}</Label>
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                            <div
                                                v-for="l in customerLedgers"
                                                :key="l.id"
                                                class="flex items-center gap-2 rounded-md border p-2"
                                            >
                                                <Checkbox
                                                    :id="`fav-ledger-customer-${l.id}`"
                                                    :checked="pluginForm.ledgers?.includes(l.id)"
                                                    @update:checked="(checked) => togglePluginIds('ledgers', l.id, checked)"
                                                />
                                                <Label :for="`fav-ledger-customer-${l.id}`" class="font-normal cursor-pointer">
                                                    {{ l.name }}
                                                </Label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="space-y-2">
                                        <Label class="text-sm font-medium">{{ t('preferences.install_plugins.suppliers_title') }}</Label>
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                            <div
                                                v-for="l in supplierLedgers"
                                                :key="l.id"
                                                class="flex items-center gap-2 rounded-md border p-2"
                                            >
                                                <Checkbox
                                                    :id="`fav-ledger-supplier-${l.id}`"
                                                    :checked="pluginForm.ledgers?.includes(l.id)"
                                                    @update:checked="(checked) => togglePluginIds('ledgers', l.id, checked)"
                                                />
                                                <Label :for="`fav-ledger-supplier-${l.id}`" class="font-normal cursor-pointer">
                                                    {{ l.name }}
                                                </Label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Backup preferences -->
                    <Card v-show="activeTab === 'backup'" class="animate-in fade-in duration-200">
                        <CardHeader class="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>{{ t('preferences.tabs.backup') }}</CardTitle>
                                <CardDescription>{{ t('preferences.backup.description') }}</CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" @click="resetCategory('backup')">
                                <RotateCcw class="w-4 h-4 mr-2" />
                                {{ t('preferences.reset') }}
                            </Button>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div class="grid grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label>{{ t('preferences.backup.auto_backup') }}</Label>
                                    <Select v-model="form.backup.auto_backup">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">{{ t('preferences.backup.none') }}</SelectItem>
                                            <SelectItem value="daily">{{ t('preferences.backup.daily') }}</SelectItem>
                                            <SelectItem value="weekly">{{ t('preferences.backup.weekly') }}</SelectItem>
                                            <SelectItem value="monthly">{{ t('preferences.backup.monthly') }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ t('preferences.backup.backup_retention_days') }}</Label>
                                    <Input v-model.number="form.backup.backup_retention_days" type="number" min="1" max="365" />
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div class="flex items-center gap-3">
                                    <Switch
                                        :model-value="form.backup.cloud_backup"
                                        @update:model-value="(v) => form.backup.cloud_backup = v"
                                    />
                                    <Label>{{ t('preferences.backup.cloud_backup') }}</Label>
                                </div>
                                <div v-if="form.backup.cloud_backup" class="space-y-2 max-w-sm">
                                    <Label>{{ t('preferences.backup.cloud_provider') }}</Label>
                                    <Select v-model="form.backup.cloud_provider">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="aws">Amazon S3</SelectItem>
                                            <SelectItem value="google">Google Cloud</SelectItem>
                                            <SelectItem value="azure">Azure Blob</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <Label class="text-base font-medium">{{ t('preferences.backup.export_formats') }}</Label>
                                <div class="flex gap-6">
                                    <div class="flex items-center gap-2">
                                        <Checkbox
                                            id="export-pdf"
                                            :checked="form.backup.export_pdf"
                                            @update:checked="(v) => form.backup.export_pdf = v"
                                        />
                                        <Label for="export-pdf" class="font-normal cursor-pointer">PDF</Label>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Checkbox
                                            id="export-excel"
                                            :checked="form.backup.export_excel"
                                            @update:checked="(v) => form.backup.export_excel = v"
                                        />
                                        <Label for="export-excel" class="font-normal cursor-pointer">Excel</Label>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Checkbox
                                            id="export-csv"
                                            :checked="form.backup.export_csv"
                                            @update:checked="(v) => form.backup.export_csv = v"
                                        />
                                        <Label for="export-csv" class="font-normal cursor-pointer">CSV</Label>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Localization preferences -->
                    <Card v-show="activeTab === 'localization'" class="animate-in fade-in duration-200">
                        <CardHeader class="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>{{ t('preferences.tabs.localization') }}</CardTitle>
                                <CardDescription>{{ t('preferences.localization.description') }}</CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" @click="resetCategory('localization')">
                                <RotateCcw class="w-4 h-4 mr-2" />
                                {{ t('preferences.reset') }}
                            </Button>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div class="grid grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label>{{ t('preferences.localization.language') }}</Label>
                                    <Select v-model="form.localization.language">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="en">English</SelectItem>
                                            <SelectItem value="fa">فارسی (Persian)</SelectItem>
                                            <SelectItem value="ps">پښتو (Pashto)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ t('preferences.localization.timezone') }}</Label>
                                    <Select v-model="form.localization.timezone">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="(label, value) in timezones" :key="value" :value="value">
                                                {{ label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label>{{ t('preferences.localization.date_format') }}</Label>
                                    <Select v-model="form.localization.date_format">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="Y-m-d">2024-01-15</SelectItem>
                                            <SelectItem value="d/m/Y">15/01/2024</SelectItem>
                                            <SelectItem value="m/d/Y">01/15/2024</SelectItem>
                                            <SelectItem value="d-m-Y">15-01-2024</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ t('preferences.localization.time_format') }}</Label>
                                    <Select v-model="form.localization.time_format">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="12h">12-hour (AM/PM)</SelectItem>
                                            <SelectItem value="24h">24-hour</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label>{{ t('preferences.localization.number_format') }}</Label>
                                    <Select v-model="form.localization.number_format">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="1,000.00">1,000.00</SelectItem>
                                            <SelectItem value="1.000,00">1.000,00</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ t('preferences.localization.first_day_of_week') }}</Label>
                                    <Select v-model="form.localization.first_day_of_week">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="sunday">{{ t('preferences.localization.sunday') }}</SelectItem>
                                            <SelectItem value="monday">{{ t('preferences.localization.monday') }}</SelectItem>
                                            <SelectItem value="saturday">{{ t('preferences.localization.saturday') }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Display preferences -->
                    <Card v-show="activeTab === 'display'" class="animate-in fade-in duration-200">
                        <CardHeader class="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>{{ t('preferences.tabs.display') }}</CardTitle>
                                <CardDescription>{{ t('preferences.display.description') }}</CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" @click="resetCategory('display')">
                                <RotateCcw class="w-4 h-4 mr-2" />
                                {{ t('preferences.reset') }}
                            </Button>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div class="grid grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label>{{ t('preferences.display.theme') }}</Label>
                                    <Select v-model="form.display.theme">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="light">{{ t('preferences.display.light') }}</SelectItem>
                                            <SelectItem value="dark">{{ t('preferences.display.dark') }}</SelectItem>
                                            <SelectItem value="system">{{ t('preferences.display.system') }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ t('preferences.display.records_per_page') }}</Label>
                                    <Select v-model="form.display.records_per_page">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem :value="10">10</SelectItem>
                                            <SelectItem :value="25">25</SelectItem>
                                            <SelectItem :value="50">50</SelectItem>
                                            <SelectItem :value="100">100</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div class="flex items-center justify-between py-2 border-b">
                                    <div>
                                        <Label class="text-base">{{ t('preferences.display.dashboard_charts') }}</Label>
                                        <p class="text-sm text-muted-foreground">{{ t('preferences.display.dashboard_charts_desc') }}</p>
                                    </div>
                                    <Switch
                                        :model-value="form.display.dashboard_charts"
                                        @update:model-value="(v) => form.display.dashboard_charts = v"
                                    />
                                </div>
                                <div class="flex items-center justify-between py-2 border-b">
                                    <div>
                                        <Label class="text-base">{{ t('preferences.display.show_currency_symbol') }}</Label>
                                        <p class="text-sm text-muted-foreground">{{ t('preferences.display.show_currency_symbol_desc') }}</p>
                                    </div>
                                    <Switch
                                        :model-value="form.display.show_currency_symbol"
                                        @update:model-value="(v) => form.display.show_currency_symbol = v"
                                    />
                                </div>
                                <div class="flex items-center justify-between py-2 border-b">
                                    <div>
                                        <Label class="text-base">{{ t('preferences.display.compact_view') }}</Label>
                                        <p class="text-sm text-muted-foreground">{{ t('preferences.display.compact_view_desc') }}</p>
                                    </div>
                                    <Switch
                                        :model-value="form.display.compact_view"
                                        @update:model-value="(v) => form.display.compact_view = v"
                                    />
                                </div>
                                <div class="flex items-center justify-between py-2">
                                    <div>
                                        <Label class="text-base">{{ t('preferences.display.sidebar_collapsed') }}</Label>
                                        <p class="text-sm text-muted-foreground">{{ t('preferences.display.sidebar_collapsed_desc') }}</p>
                                    </div>
                                    <Switch
                                        :model-value="form.display.sidebar_collapsed"
                                        @update:model-value="(v) => form.display.sidebar_collapsed = v"
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

