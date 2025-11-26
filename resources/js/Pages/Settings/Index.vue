<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { ref, computed, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card'
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
    Save, Settings as SettingsIcon
} from 'lucide-vue-next'

const props = defineProps({
    preferences: Object,
    defaultPreferences: Object,
    cashAccounts: Array,
    sidebarMenus: Array,
    timezones: Object,
})

const { t } = useI18n()
const { toast } = useToast()

const activeTab = ref('appearance')

const tabs = [
    { id: 'appearance', label: 'settings.tabs.appearance', icon: Palette },
    { id: 'item_management', label: 'settings.tabs.item_management', icon: Package },
    { id: 'sales', label: 'settings.tabs.sales', icon: ShoppingCart },
    { id: 'purchases', label: 'settings.tabs.purchases', icon: ShoppingBag },
    { id: 'receipt_payment', label: 'settings.tabs.receipt_payment', icon: CreditCard },
    { id: 'tax_currency', label: 'settings.tabs.tax_currency', icon: Calculator },
    { id: 'notifications', label: 'settings.tabs.notifications', icon: Bell },
    { id: 'security', label: 'settings.tabs.security', icon: Shield },
    { id: 'backup', label: 'settings.tabs.backup', icon: Database },
    { id: 'localization', label: 'settings.tabs.localization', icon: Globe },
    { id: 'display', label: 'settings.tabs.display', icon: Monitor },
]

const form = useForm({ ...props.preferences })

const save = () => {
    form.put(route('settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            toast({ title: t('settings.saved'), description: t('settings.saved_description') })
        },
    })
}

const resetCategory = (category) => {
    if (confirm(t('settings.confirm_reset'))) {
        router.post(route('settings.reset', { category }), {}, {
            preserveScroll: true,
            onSuccess: () => {
                form[category] = props.defaultPreferences[category]
                toast({ title: t('settings.reset_success') })
            },
        })
    }
}

const exportSettings = () => {
    window.location.href = route('settings.export')
}

const fileInput = ref(null)
const importSettings = () => {
    fileInput.value?.click()
}

const handleFileUpload = (event) => {
    const file = event.target.files[0]
    if (!file) return
    
    const formData = new FormData()
    formData.append('file', file)
    
    router.post(route('settings.import'), formData, {
        onSuccess: () => {
            toast({ title: t('settings.import_success') })
        },
        onError: () => {
            toast({ title: t('settings.import_error'), variant: 'destructive' })
        },
    })
}

// Transaction Types for Sales and Purchases
const salesTransactionTypes = ['sales', 'sales_order', 'sales_return', 'sales_quotation']
const purchaseTransactionTypes = ['purchases', 'purchase_order', 'purchase_return', 'purchase_quotation']
const activeSalesType = ref('sales')
const activePurchaseType = ref('purchases')

const generalFields = [
    { key: 'number', label: 'settings.fields.number' },
    { key: 'date', label: 'settings.fields.date' },
    { key: 'currency', label: 'settings.fields.currency' },
    { key: 'type', label: 'settings.fields.type' },
    { key: 'store', label: 'settings.fields.store' },
]

const itemColumns = [
    { key: 'packing', label: 'settings.fields.packing' },
    { key: 'batch', label: 'settings.fields.batch' },
    { key: 'expiry', label: 'settings.fields.expiry' },
    { key: 'on_hand', label: 'settings.fields.on_hand' },
    { key: 'measure', label: 'settings.fields.measure' },
    { key: 'discount', label: 'settings.fields.discount' },
    { key: 'tax', label: 'settings.fields.tax' },
    { key: 'free', label: 'settings.fields.free' },
]

const itemManagementFields = [
    { key: 'code', label: 'settings.item_fields.code' },
    { key: 'generic_name', label: 'settings.item_fields.generic_name' },
    { key: 'packing', label: 'settings.item_fields.packing' },
    { key: 'colors', label: 'settings.item_fields.colors' },
    { key: 'size', label: 'settings.item_fields.size' },
    { key: 'brand', label: 'settings.item_fields.brand' },
    { key: 'minimum_stock', label: 'settings.item_fields.minimum_stock' },
    { key: 'maximum_stock', label: 'settings.item_fields.maximum_stock' },
    { key: 'file_upload', label: 'settings.item_fields.file_upload' },
    { key: 'rate_a', label: 'settings.item_fields.rate_a' },
    { key: 'rate_b', label: 'settings.item_fields.rate_b' },
    { key: 'rate_c', label: 'settings.item_fields.rate_c' },
    { key: 'barcode', label: 'settings.item_fields.barcode' },
    { key: 'rack_no', label: 'settings.item_fields.rack_no' },
    { key: 'fast_search', label: 'settings.item_fields.fast_search' },
]

const receiptPaymentFields = [
    { key: 'number', label: 'settings.fields.number' },
    { key: 'currency', label: 'settings.fields.currency' },
    { key: 'cheque_number', label: 'settings.receipt_fields.cheque_number' },
    { key: 'debit_account', label: 'settings.receipt_fields.debit_account' },
    { key: 'ledger_old_balance', label: 'settings.receipt_fields.ledger_old_balance' },
]
</script>

<template>
    <AppLayout :title="t('settings.title')">
        <div class="container mx-auto py-6 space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <SettingsIcon class="w-8 h-8 text-primary" />
                    <div>
                        <h1 class="text-2xl font-bold">{{ t('settings.title') }}</h1>
                        <p class="text-muted-foreground">{{ t('settings.description') }}</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <input ref="fileInput" type="file" accept=".json" class="hidden" @change="handleFileUpload" />
                    <Button variant="outline" size="sm" @click="importSettings">
                        <Upload class="w-4 h-4 mr-2" />
                        {{ t('settings.import') }}
                    </Button>
                    <Button variant="outline" size="sm" @click="exportSettings">
                        <Download class="w-4 h-4 mr-2" />
                        {{ t('settings.export') }}
                    </Button>
                    <Button @click="save" :disabled="form.processing">
                        <Save class="w-4 h-4 mr-2" />
                        {{ t('settings.save') }}
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
                    <!-- Appearance Settings -->
                    <Card v-show="activeTab === 'appearance'" class="animate-in fade-in duration-200">
                        <CardHeader class="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>{{ t('settings.tabs.appearance') }}</CardTitle>
                                <CardDescription>{{ t('settings.appearance.description') }}</CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" @click="resetCategory('appearance')">
                                <RotateCcw class="w-4 h-4 mr-2" />
                                {{ t('settings.reset') }}
                            </Button>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div class="grid grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label>{{ t('settings.appearance.font_size') }}</Label>
                                    <Input v-model.number="form.appearance.font_size" type="number" min="10" max="24" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ t('settings.appearance.theme') }}</Label>
                                    <Select v-model="form.appearance.theme">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="light">{{ t('settings.appearance.light') }}</SelectItem>
                                            <SelectItem value="dark">{{ t('settings.appearance.dark') }}</SelectItem>
                                            <SelectItem value="system">{{ t('settings.appearance.system') }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ t('settings.appearance.decimal_places') }}</Label>
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
                                <Label>{{ t('settings.appearance.sidebar_menus') }}</Label>
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

                    <!-- Item Management Settings -->
                    <Card v-show="activeTab === 'item_management'" class="animate-in fade-in duration-200">
                        <CardHeader class="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>{{ t('settings.tabs.item_management') }}</CardTitle>
                                <CardDescription>{{ t('settings.item_management.description') }}</CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" @click="resetCategory('item_management')">
                                <RotateCcw class="w-4 h-4 mr-2" />
                                {{ t('settings.reset') }}
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-3">
                                <Label class="text-base font-medium">{{ t('settings.item_management.visible_fields') }}</Label>
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
                        </CardContent>
                    </Card>

                    <!-- Sales Settings -->
                    <Card v-show="activeTab === 'sales'" class="animate-in fade-in duration-200">
                        <CardHeader class="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>{{ t('settings.tabs.sales') }}</CardTitle>
                                <CardDescription>{{ t('settings.sales.description') }}</CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" @click="resetCategory(activeSalesType)">
                                <RotateCcw class="w-4 h-4 mr-2" />
                                {{ t('settings.reset') }}
                            </Button>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <!-- Transaction Type Tabs -->
                            <div class="flex gap-2 border-b pb-4">
                                <Button
                                    v-for="type in salesTransactionTypes"
                                    :key="type"
                                    :variant="activeSalesType === type ? 'default' : 'outline'"
                                    size="sm"
                                    @click="activeSalesType = type"
                                >
                                    {{ t(`settings.sales.types.${type}`) }}
                                </Button>
                            </div>

                            <!-- General Fields -->
                            <div class="space-y-3">
                                <Label class="text-base font-medium">{{ t('settings.sales.general_fields') }}</Label>
                                <div class="flex flex-wrap gap-4">
                                    <div v-for="field in generalFields" :key="field.key" class="flex items-center gap-2">
                                        <Checkbox
                                            :id="`sales-general-${field.key}`"
                                            :checked="form[activeSalesType]?.general_fields?.[field.key]"
                                            @update:checked="(checked) => {
                                                if (!form[activeSalesType].general_fields) form[activeSalesType].general_fields = {}
                                                form[activeSalesType].general_fields[field.key] = checked
                                            }"
                                        />
                                        <Label :for="`sales-general-${field.key}`" class="font-normal cursor-pointer">{{ t(field.label) }}</Label>
                                    </div>
                                </div>
                            </div>

                            <!-- Item Columns -->
                            <div class="space-y-3">
                                <Label class="text-base font-medium">{{ t('settings.sales.item_columns') }}</Label>
                                <div class="flex flex-wrap gap-4">
                                    <div v-for="col in itemColumns" :key="col.key" class="flex items-center gap-2">
                                        <Checkbox
                                            :id="`sales-col-${col.key}`"
                                            :checked="form[activeSalesType]?.item_columns?.[col.key]"
                                            @update:checked="(checked) => {
                                                if (!form[activeSalesType].item_columns) form[activeSalesType].item_columns = {}
                                                form[activeSalesType].item_columns[col.key] = checked
                                            }"
                                        />
                                        <Label :for="`sales-col-${col.key}`" class="font-normal cursor-pointer">{{ t(col.label) }}</Label>
                                    </div>
                                </div>
                            </div>

                            <!-- Additional Settings -->
                            <div class="grid grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label>{{ t('settings.sales.invoice_prefix') }}</Label>
                                    <Input v-model="form[activeSalesType].invoice_prefix" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ t('settings.sales.start_number') }}</Label>
                                    <Input v-model.number="form[activeSalesType].start_number" type="number" min="1" />
                                </div>
                            </div>

                            <template v-if="activeSalesType === 'sales' || activeSalesType === 'sales_order'">
                                <div class="grid grid-cols-2 gap-6">
                                    <div class="space-y-2">
                                        <Label>{{ t('settings.sales.due_days') }}</Label>
                                        <Input v-model.number="form[activeSalesType].due_days" type="number" min="0" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>{{ t('settings.sales.terms') }}</Label>
                                        <Textarea v-model="form[activeSalesType].terms" rows="2" />
                                    </div>
                                </div>
                            </template>

                            <template v-if="activeSalesType === 'sales'">
                                <div class="grid grid-cols-3 gap-6">
                                    <div class="flex items-center gap-3">
                                        <Switch
                                            :checked="form.sales.auto_reminders"
                                            @update:checked="(v) => form.sales.auto_reminders = v"
                                        />
                                        <Label>{{ t('settings.sales.auto_reminders') }}</Label>
                                    </div>
                                    <div class="space-y-2">
                                        <Label>{{ t('settings.sales.reminder_days') }}</Label>
                                        <Input v-model.number="form.sales.reminder_days" type="number" min="1" :disabled="!form.sales.auto_reminders" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>{{ t('settings.sales.late_fee_percentage') }}</Label>
                                        <Input v-model.number="form.sales.late_fee_percentage" type="number" min="0" max="100" step="0.1" />
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-6">
                                    <div class="space-y-2">
                                        <Label>{{ t('settings.sales.tax_percentage') }}</Label>
                                        <Input v-model.number="form.sales.tax_percentage" type="number" min="0" max="100" step="0.1" />
                                    </div>
                                    <div class="flex items-center gap-3 pt-8">
                                        <Switch
                                            :checked="form.sales.auto_calculate_tax"
                                            @update:checked="(v) => form.sales.auto_calculate_tax = v"
                                        />
                                        <Label>{{ t('settings.sales.auto_calculate_tax') }}</Label>
                                    </div>
                                </div>
                                <div class="flex gap-6">
                                    <div class="flex items-center gap-3">
                                        <Switch
                                            :checked="form.sales.show_ledger_transactions"
                                            @update:checked="(v) => form.sales.show_ledger_transactions = v"
                                        />
                                        <Label>{{ t('settings.sales.show_ledger_transactions') }}</Label>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <Switch
                                            :checked="form.sales.show_item_transactions"
                                            @update:checked="(v) => form.sales.show_item_transactions = v"
                                        />
                                        <Label>{{ t('settings.sales.show_item_transactions') }}</Label>
                                    </div>
                                </div>
                            </template>
                        </CardContent>
                    </Card>

                    <!-- Purchase Settings -->
                    <Card v-show="activeTab === 'purchases'" class="animate-in fade-in duration-200">
                        <CardHeader class="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>{{ t('settings.tabs.purchases') }}</CardTitle>
                                <CardDescription>{{ t('settings.purchases.description') }}</CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" @click="resetCategory(activePurchaseType)">
                                <RotateCcw class="w-4 h-4 mr-2" />
                                {{ t('settings.reset') }}
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
                                    {{ t(`settings.purchases.types.${type}`) }}
                                </Button>
                            </div>

                            <!-- General Fields -->
                            <div class="space-y-3">
                                <Label class="text-base font-medium">{{ t('settings.purchases.general_fields') }}</Label>
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
                                <Label class="text-base font-medium">{{ t('settings.purchases.item_columns') }}</Label>
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

                            <!-- Additional Settings -->
                            <div class="grid grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label>{{ t('settings.purchases.invoice_prefix') }}</Label>
                                    <Input v-model="form[activePurchaseType].invoice_prefix" />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ t('settings.purchases.start_number') }}</Label>
                                    <Input v-model.number="form[activePurchaseType].start_number" type="number" min="1" />
                                </div>
                            </div>

                            <template v-if="activePurchaseType === 'purchases'">
                                <div class="grid grid-cols-2 gap-6">
                                    <div class="space-y-2">
                                        <Label>{{ t('settings.purchases.due_days') }}</Label>
                                        <Input v-model.number="form.purchases.due_days" type="number" min="0" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>{{ t('settings.purchases.terms') }}</Label>
                                        <Textarea v-model="form.purchases.terms" rows="2" />
                                    </div>
                                </div>
                                <div class="grid grid-cols-3 gap-6">
                                    <div class="flex items-center gap-3">
                                        <Switch
                                            :checked="form.purchases.auto_reminders"
                                            @update:checked="(v) => form.purchases.auto_reminders = v"
                                        />
                                        <Label>{{ t('settings.purchases.auto_reminders') }}</Label>
                                    </div>
                                    <div class="space-y-2">
                                        <Label>{{ t('settings.purchases.reminder_days') }}</Label>
                                        <Input v-model.number="form.purchases.reminder_days" type="number" min="1" :disabled="!form.purchases.auto_reminders" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>{{ t('settings.purchases.late_fee_percentage') }}</Label>
                                        <Input v-model.number="form.purchases.late_fee_percentage" type="number" min="0" max="100" step="0.1" />
                                    </div>
                                </div>
                                <div class="flex gap-6">
                                    <div class="flex items-center gap-3">
                                        <Switch
                                            :checked="form.purchases.show_ledger_transactions"
                                            @update:checked="(v) => form.purchases.show_ledger_transactions = v"
                                        />
                                        <Label>{{ t('settings.purchases.show_ledger_transactions') }}</Label>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <Switch
                                            :checked="form.purchases.show_item_transactions"
                                            @update:checked="(v) => form.purchases.show_item_transactions = v"
                                        />
                                        <Label>{{ t('settings.purchases.show_item_transactions') }}</Label>
                                    </div>
                                </div>
                            </template>
                        </CardContent>
                    </Card>

                    <!-- Receipt & Payment Settings -->
                    <Card v-show="activeTab === 'receipt_payment'" class="animate-in fade-in duration-200">
                        <CardHeader class="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>{{ t('settings.tabs.receipt_payment') }}</CardTitle>
                                <CardDescription>{{ t('settings.receipt_payment.description') }}</CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" @click="resetCategory('receipt_payment')">
                                <RotateCcw class="w-4 h-4 mr-2" />
                                {{ t('settings.reset') }}
                            </Button>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div class="space-y-3">
                                <Label class="text-base font-medium">{{ t('settings.receipt_payment.visible_fields') }}</Label>
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
                                    <Label>{{ t('settings.receipt_payment.default_cash_account') }}</Label>
                                    <Select v-model="form.receipt_payment.default_cash_account">
                                        <SelectTrigger>
                                            <SelectValue :placeholder="t('settings.receipt_payment.select_account')" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="account in cashAccounts" :key="account.id" :value="account.id">
                                                {{ account.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ t('settings.receipt_payment.lock_after_days') }}</Label>
                                    <Input v-model.number="form.receipt_payment.lock_after_days" type="number" min="0" />
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-6">
                                <div class="flex items-center gap-3">
                                    <Switch
                                        :checked="form.receipt_payment.auto_sequence"
                                        @update:checked="(v) => form.receipt_payment.auto_sequence = v"
                                    />
                                    <Label>{{ t('settings.receipt_payment.auto_sequence') }}</Label>
                                </div>
                                <div class="flex items-center gap-3">
                                    <Switch
                                        :checked="form.receipt_payment.require_approval"
                                        @update:checked="(v) => form.receipt_payment.require_approval = v"
                                    />
                                    <Label>{{ t('settings.receipt_payment.require_approval') }}</Label>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Tax & Currency Settings -->
                    <Card v-show="activeTab === 'tax_currency'" class="animate-in fade-in duration-200">
                        <CardHeader class="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>{{ t('settings.tabs.tax_currency') }}</CardTitle>
                                <CardDescription>{{ t('settings.tax_currency.description') }}</CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" @click="resetCategory('tax_currency')">
                                <RotateCcw class="w-4 h-4 mr-2" />
                                {{ t('settings.reset') }}
                            </Button>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div class="flex flex-wrap gap-6">
                                <div class="flex items-center gap-3">
                                    <Switch
                                        :checked="form.tax_currency.tax_plus"
                                        @update:checked="(v) => form.tax_currency.tax_plus = v"
                                    />
                                    <Label>{{ t('settings.tax_currency.tax_plus') }}</Label>
                                </div>
                                <div class="flex items-center gap-3">
                                    <Switch
                                        :checked="form.tax_currency.tax_minus"
                                        @update:checked="(v) => form.tax_currency.tax_minus = v"
                                    />
                                    <Label>{{ t('settings.tax_currency.tax_minus') }}</Label>
                                </div>
                                <div class="flex items-center gap-3">
                                    <Switch
                                        :checked="form.tax_currency.multi_currency_opening"
                                        @update:checked="(v) => form.tax_currency.multi_currency_opening = v"
                                    />
                                    <Label>{{ t('settings.tax_currency.multi_currency_opening') }}</Label>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <Label>{{ t('settings.tax_currency.spec_text') }}</Label>
                                <Textarea v-model="form.tax_currency.spec_text" rows="3" />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Notification Settings -->
                    <Card v-show="activeTab === 'notifications'" class="animate-in fade-in duration-200">
                        <CardHeader class="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>{{ t('settings.tabs.notifications') }}</CardTitle>
                                <CardDescription>{{ t('settings.notifications.description') }}</CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" @click="resetCategory('notifications')">
                                <RotateCcw class="w-4 h-4 mr-2" />
                                {{ t('settings.reset') }}
                            </Button>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-4">
                                <div class="flex items-center justify-between py-2 border-b">
                                    <div>
                                        <Label class="text-base">{{ t('settings.notifications.email_notifications') }}</Label>
                                        <p class="text-sm text-muted-foreground">{{ t('settings.notifications.email_notifications_desc') }}</p>
                                    </div>
                                    <Switch
                                        :checked="form.notifications.email_notifications"
                                        @update:checked="(v) => form.notifications.email_notifications = v"
                                    />
                                </div>
                                <div class="flex items-center justify-between py-2 border-b">
                                    <div>
                                        <Label class="text-base">{{ t('settings.notifications.low_balance_alert') }}</Label>
                                        <p class="text-sm text-muted-foreground">{{ t('settings.notifications.low_balance_alert_desc') }}</p>
                                    </div>
                                    <Switch
                                        :checked="form.notifications.low_balance_alert"
                                        @update:checked="(v) => form.notifications.low_balance_alert = v"
                                    />
                                </div>
                                <div class="flex items-center justify-between py-2 border-b">
                                    <div>
                                        <Label class="text-base">{{ t('settings.notifications.overdue_invoice_alert') }}</Label>
                                        <p class="text-sm text-muted-foreground">{{ t('settings.notifications.overdue_invoice_alert_desc') }}</p>
                                    </div>
                                    <Switch
                                        :checked="form.notifications.overdue_invoice_alert"
                                        @update:checked="(v) => form.notifications.overdue_invoice_alert = v"
                                    />
                                </div>
                                <div class="flex items-center justify-between py-2 border-b">
                                    <div>
                                        <Label class="text-base">{{ t('settings.notifications.new_transaction_alert') }}</Label>
                                        <p class="text-sm text-muted-foreground">{{ t('settings.notifications.new_transaction_alert_desc') }}</p>
                                    </div>
                                    <Switch
                                        :checked="form.notifications.new_transaction_alert"
                                        @update:checked="(v) => form.notifications.new_transaction_alert = v"
                                    />
                                </div>
                                <div class="flex items-center justify-between py-2 border-b">
                                    <div>
                                        <Label class="text-base">{{ t('settings.notifications.daily_summary_report') }}</Label>
                                        <p class="text-sm text-muted-foreground">{{ t('settings.notifications.daily_summary_report_desc') }}</p>
                                    </div>
                                    <Switch
                                        :checked="form.notifications.daily_summary_report"
                                        @update:checked="(v) => form.notifications.daily_summary_report = v"
                                    />
                                </div>
                                <div class="flex items-center justify-between py-2">
                                    <div>
                                        <Label class="text-base">{{ t('settings.notifications.weekly_financial_summary') }}</Label>
                                        <p class="text-sm text-muted-foreground">{{ t('settings.notifications.weekly_financial_summary_desc') }}</p>
                                    </div>
                                    <Switch
                                        :checked="form.notifications.weekly_financial_summary"
                                        @update:checked="(v) => form.notifications.weekly_financial_summary = v"
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Security Settings -->
                    <Card v-show="activeTab === 'security'" class="animate-in fade-in duration-200">
                        <CardHeader class="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>{{ t('settings.tabs.security') }}</CardTitle>
                                <CardDescription>{{ t('settings.security.description') }}</CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" @click="resetCategory('security')">
                                <RotateCcw class="w-4 h-4 mr-2" />
                                {{ t('settings.reset') }}
                            </Button>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div class="grid grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label>{{ t('settings.security.session_timeout') }}</Label>
                                    <Input v-model.number="form.security.session_timeout" type="number" min="5" max="1440" />
                                    <p class="text-sm text-muted-foreground">{{ t('settings.security.session_timeout_hint') }}</p>
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ t('settings.security.login_attempts_limit') }}</Label>
                                    <Input v-model.number="form.security.login_attempts_limit" type="number" min="3" max="10" />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label>{{ t('settings.security.password_min_length') }}</Label>
                                    <Input v-model.number="form.security.password_min_length" type="number" min="6" max="32" />
                                </div>
                                <div class="flex items-center gap-3 pt-8">
                                    <Switch
                                        :checked="form.security.password_special_chars"
                                        @update:checked="(v) => form.security.password_special_chars = v"
                                    />
                                    <Label>{{ t('settings.security.password_special_chars') }}</Label>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <Switch
                                    :checked="form.security.two_factor_auth"
                                    @update:checked="(v) => form.security.two_factor_auth = v"
                                />
                                <Label>{{ t('settings.security.two_factor_auth') }}</Label>
                            </div>
                            <div class="border-t pt-6 space-y-4">
                                <Label class="text-base font-medium">{{ t('settings.security.report_lock') }}</Label>
                                <div class="flex items-center gap-3">
                                    <Switch
                                        :checked="form.security.lock_reports"
                                        @update:checked="(v) => form.security.lock_reports = v"
                                    />
                                    <Label>{{ t('settings.security.lock_reports') }}</Label>
                                </div>
                                <div v-if="form.security.lock_reports" class="space-y-2 max-w-sm">
                                    <Label>{{ t('settings.security.lock_password') }}</Label>
                                    <Input v-model="form.security.lock_password" type="password" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Backup Settings -->
                    <Card v-show="activeTab === 'backup'" class="animate-in fade-in duration-200">
                        <CardHeader class="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>{{ t('settings.tabs.backup') }}</CardTitle>
                                <CardDescription>{{ t('settings.backup.description') }}</CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" @click="resetCategory('backup')">
                                <RotateCcw class="w-4 h-4 mr-2" />
                                {{ t('settings.reset') }}
                            </Button>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div class="grid grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label>{{ t('settings.backup.auto_backup') }}</Label>
                                    <Select v-model="form.backup.auto_backup">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">{{ t('settings.backup.none') }}</SelectItem>
                                            <SelectItem value="daily">{{ t('settings.backup.daily') }}</SelectItem>
                                            <SelectItem value="weekly">{{ t('settings.backup.weekly') }}</SelectItem>
                                            <SelectItem value="monthly">{{ t('settings.backup.monthly') }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ t('settings.backup.backup_retention_days') }}</Label>
                                    <Input v-model.number="form.backup.backup_retention_days" type="number" min="1" max="365" />
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div class="flex items-center gap-3">
                                    <Switch
                                        :checked="form.backup.cloud_backup"
                                        @update:checked="(v) => form.backup.cloud_backup = v"
                                    />
                                    <Label>{{ t('settings.backup.cloud_backup') }}</Label>
                                </div>
                                <div v-if="form.backup.cloud_backup" class="space-y-2 max-w-sm">
                                    <Label>{{ t('settings.backup.cloud_provider') }}</Label>
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
                                <Label class="text-base font-medium">{{ t('settings.backup.export_formats') }}</Label>
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

                    <!-- Localization Settings -->
                    <Card v-show="activeTab === 'localization'" class="animate-in fade-in duration-200">
                        <CardHeader class="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>{{ t('settings.tabs.localization') }}</CardTitle>
                                <CardDescription>{{ t('settings.localization.description') }}</CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" @click="resetCategory('localization')">
                                <RotateCcw class="w-4 h-4 mr-2" />
                                {{ t('settings.reset') }}
                            </Button>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div class="grid grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label>{{ t('settings.localization.language') }}</Label>
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
                                    <Label>{{ t('settings.localization.timezone') }}</Label>
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
                                    <Label>{{ t('settings.localization.date_format') }}</Label>
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
                                    <Label>{{ t('settings.localization.time_format') }}</Label>
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
                                    <Label>{{ t('settings.localization.number_format') }}</Label>
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
                                    <Label>{{ t('settings.localization.first_day_of_week') }}</Label>
                                    <Select v-model="form.localization.first_day_of_week">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="sunday">{{ t('settings.localization.sunday') }}</SelectItem>
                                            <SelectItem value="monday">{{ t('settings.localization.monday') }}</SelectItem>
                                            <SelectItem value="saturday">{{ t('settings.localization.saturday') }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Display Settings -->
                    <Card v-show="activeTab === 'display'" class="animate-in fade-in duration-200">
                        <CardHeader class="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>{{ t('settings.tabs.display') }}</CardTitle>
                                <CardDescription>{{ t('settings.display.description') }}</CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" @click="resetCategory('display')">
                                <RotateCcw class="w-4 h-4 mr-2" />
                                {{ t('settings.reset') }}
                            </Button>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div class="grid grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label>{{ t('settings.display.theme') }}</Label>
                                    <Select v-model="form.display.theme">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="light">{{ t('settings.display.light') }}</SelectItem>
                                            <SelectItem value="dark">{{ t('settings.display.dark') }}</SelectItem>
                                            <SelectItem value="system">{{ t('settings.display.system') }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ t('settings.display.records_per_page') }}</Label>
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
                                        <Label class="text-base">{{ t('settings.display.dashboard_charts') }}</Label>
                                        <p class="text-sm text-muted-foreground">{{ t('settings.display.dashboard_charts_desc') }}</p>
                                    </div>
                                    <Switch
                                        :checked="form.display.dashboard_charts"
                                        @update:checked="(v) => form.display.dashboard_charts = v"
                                    />
                                </div>
                                <div class="flex items-center justify-between py-2 border-b">
                                    <div>
                                        <Label class="text-base">{{ t('settings.display.show_currency_symbol') }}</Label>
                                        <p class="text-sm text-muted-foreground">{{ t('settings.display.show_currency_symbol_desc') }}</p>
                                    </div>
                                    <Switch
                                        :checked="form.display.show_currency_symbol"
                                        @update:checked="(v) => form.display.show_currency_symbol = v"
                                    />
                                </div>
                                <div class="flex items-center justify-between py-2 border-b">
                                    <div>
                                        <Label class="text-base">{{ t('settings.display.compact_view') }}</Label>
                                        <p class="text-sm text-muted-foreground">{{ t('settings.display.compact_view_desc') }}</p>
                                    </div>
                                    <Switch
                                        :checked="form.display.compact_view"
                                        @update:checked="(v) => form.display.compact_view = v"
                                    />
                                </div>
                                <div class="flex items-center justify-between py-2">
                                    <div>
                                        <Label class="text-base">{{ t('settings.display.sidebar_collapsed') }}</Label>
                                        <p class="text-sm text-muted-foreground">{{ t('settings.display.sidebar_collapsed_desc') }}</p>
                                    </div>
                                    <Switch
                                        :checked="form.display.sidebar_collapsed"
                                        @update:checked="(v) => form.display.sidebar_collapsed = v"
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

