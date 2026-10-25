<script setup>
/**
 * The settings a company shares (phase 6): document numbering, posting rules,
 * tax, formats, security and backup. The keys are the company_keys of
 * config/preferences.php, and the object is edited in place.
 *
 * Used by the company page (view, and edit with company_settings.update) and
 * by the company set-up wizard, where it starts from the defaults.
 */
import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Switch } from '@/Components/ui/switch'
import { Checkbox } from '@/Components/ui/checkbox'
import { Textarea } from '@/Components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select'

const props = defineProps({
    settings: { type: Object, required: true },
    disabled: { type: Boolean, default: false },
    cashAccounts: { type: Array, default: () => [] },
    // Laravel's errors, dot keys relative to the settings object.
    errors: { type: Object, default: () => ({}) },
})

const { t } = useI18n()

const sections = [
    { id: 'modules', label: 'company.settings.tabs.modules' },
    { id: 'documents', label: 'company.settings.tabs.documents' },
    { id: 'posting', label: 'company.settings.tabs.posting' },
    { id: 'stock_adjustment', label: 'preferences.tabs.stock_adjustment' },
    { id: 'receipt_payment', label: 'preferences.tabs.receipt_payment' },
    { id: 'tax_currency', label: 'preferences.tabs.tax_currency' },
    { id: 'formats', label: 'company.settings.tabs.formats' },
    { id: 'security', label: 'preferences.tabs.security' },
    { id: 'backup', label: 'preferences.tabs.backup' },
]
const active = ref('modules')

// Only modules that exist; planned ones (config/modules.php) stay out until
// they are built.
const page = usePage()
const availableModules = computed(() => page.props.modules?.available ?? [])

const documentTypes = [
    { key: 'sale', label: 'preferences.sale.types.sale' },
    { key: 'sale_order', label: 'preferences.sale.types.sale_order' },
    { key: 'sale_return', label: 'preferences.sale.types.sale_return' },
    { key: 'sale_quotation', label: 'preferences.sale.types.sale_quotation' },
    { key: 'purchase', label: 'preferences.purchase.types.purchase' },
    { key: 'purchase_order', label: 'preferences.purchase.types.purchase_order' },
    { key: 'purchase_return', label: 'preferences.purchase.types.purchase_return' },
    { key: 'purchase_quotation', label: 'preferences.purchase.types.purchase_quotation' },
]

const postModules = [
    'sale', 'sale_order', 'sale_return', 'sale_quotation', 'purchase', 'purchase_order', 'purchase_return',
    'purchase_quotation', 'expense', 'journal_entry', 'receipt', 'payment', 'account_transfer',
    'item_transfer', 'drawing', 'stock_adjustment',
].map((module) => ({ key: `${module}_post_immediately`, label: `preferences.transaction.modules.${module}` }))

// Reason → offset account (9040 shrinkage vs 9050 adjustments).
const reasons = [
    ['damage', 'out'], ['expiry', 'out'], ['theft', 'out'], ['loss', 'out'], ['count_down', 'out'],
    ['internal_use', 'out'], ['sample', 'out'], ['wastage', 'out'], ['quality_rejection', 'out'],
    ['count_up', 'in'], ['found', 'in'], ['opening_stock', 'in'], ['production_output', 'in'], ['surplus', 'in'],
].map(([key, direction]) => ({ key, direction }))
const offsetAccounts = [
    { id: 'inventory-shrinkage-and-wastage', label: 'preferences.stock_adjustment.accounts.shrinkage' },
    { id: 'inventory-adjustments', label: 'preferences.stock_adjustment.accounts.adjustments' },
]

const timezones = {
    UTC: 'UTC',
    'Asia/Kabul': 'Asia/Kabul (UTC+4:30)',
    'Asia/Tehran': 'Asia/Tehran (UTC+3:30)',
    'Asia/Dubai': 'Asia/Dubai (UTC+4)',
    'Asia/Karachi': 'Asia/Karachi (UTC+5)',
    'Asia/Kolkata': 'Asia/Kolkata (UTC+5:30)',
    'Europe/London': 'Europe/London (UTC+0)',
    'Europe/Paris': 'Europe/Paris (UTC+1)',
    'America/New_York': 'America/New_York (UTC-5)',
    'America/Los_Angeles': 'America/Los_Angeles (UTC-8)',
}

// Groups the defaults may lack in an older company: create them on first use.
const group = (path) => path.split('.').reduce((node, key) => {
    if (!node[key] || typeof node[key] !== 'object') node[key] = {}
    return node[key]
}, props.settings)

const allPosted = computed(() => postModules.every((module) => group('transaction')[module.key] ?? true))
const setAllPosted = (checked) => postModules.forEach((module) => { group('transaction')[module.key] = checked })

// Which sections hold an error, so the pill can say so.
const sectionOf = {
    sale: 'documents', sale_order: 'documents', sale_return: 'documents', sale_quotation: 'documents',
    purchase: 'documents', purchase_order: 'documents', purchase_return: 'documents', purchase_quotation: 'documents',
    transaction: 'posting', stock_adjustment: 'stock_adjustment', receipt_payment: 'receipt_payment',
    tax_currency: 'tax_currency', appearance: 'formats', item_management: 'formats', localization: 'formats',
    security: 'security', backup: 'backup', modules: 'modules',
}
const sectionsWithErrors = computed(() => new Set(Object.keys(props.errors).map((key) => sectionOf[key.split('.')[0]])))
const error = (key) => props.errors[key]
</script>

<template>
    <div class="space-y-5">
        <div class="flex flex-wrap gap-2" role="tablist">
            <button
                v-for="section in sections"
                :key="section.id"
                type="button"
                role="tab"
                :aria-selected="active === section.id"
                class="rounded-full border px-3 py-1 text-sm transition"
                :class="[
                    active === section.id
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-border text-muted-foreground hover:border-primary/40 hover:text-foreground',
                    sectionsWithErrors.has(section.id) && active !== section.id ? 'border-red-500 text-red-600' : '',
                ]"
                @click="active = section.id"
            >
                {{ t(section.label) }}
            </button>
        </div>

        <!-- A disabled fieldset disables every input and button in it, the Select
             triggers and switches included: the view mode of the company page. -->
        <fieldset :disabled="disabled" class="min-w-0 disabled:opacity-80">
            <!-- Modules: switched off is hidden and blocked, its data kept -->
            <div v-show="active === 'modules'" class="space-y-3">
                <p class="text-sm text-muted-foreground">{{ t('company.settings.modules_hint') }}</p>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <div v-for="module in availableModules" :key="module" class="flex items-center justify-between gap-3 rounded-lg border border-border px-4 py-3">
                        <Label :for="`company-module-${module}`" class="cursor-pointer font-medium">{{ t(`company.modules.${module}`) }}</Label>
                        <Switch
                            :id="`company-module-${module}`"
                            :model-value="group('modules')[module] ?? false"
                            @update:model-value="(v) => group('modules')[module] = v"
                        />
                    </div>
                </div>
            </div>

            <!-- Documents: numbering and terms -->
            <div v-show="active === 'documents'" class="space-y-6">
                <div class="overflow-x-auto rounded-lg border border-border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/40 text-muted-foreground">
                            <tr>
                                <th class="px-3 py-2 text-start font-medium">{{ t('company.settings.document') }}</th>
                                <th class="px-3 py-2 text-start font-medium">{{ t('preferences.sale.invoice_prefix') }}</th>
                                <th class="px-3 py-2 text-start font-medium">{{ t('preferences.sale.start_number') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="doc in documentTypes" :key="doc.key" class="border-t border-border">
                                <td class="px-3 py-2 text-foreground">{{ t(doc.label) }}</td>
                                <td class="px-3 py-2">
                                    <Input v-model="group(doc.key).invoice_prefix" class="h-8 max-w-40" />
                                    <p v-if="error(`${doc.key}.invoice_prefix`)" class="mt-1 text-xs text-red-600">{{ error(`${doc.key}.invoice_prefix`) }}</p>
                                </td>
                                <td class="px-3 py-2">
                                    <Input v-model.number="group(doc.key).start_number" type="number" min="1" class="h-8 max-w-32" />
                                    <p v-if="error(`${doc.key}.start_number`)" class="mt-1 text-xs text-red-600">{{ error(`${doc.key}.start_number`) }}</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-for="side in ['sale', 'purchase']" :key="side" class="space-y-4 rounded-lg border border-border p-4">
                    <h4 class="text-sm font-semibold text-foreground">{{ t(`preferences.tabs.${side}`) }}</h4>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label>{{ t(`preferences.${side}.due_days`) }}</Label>
                            <Input v-model.number="group(side).due_days" type="number" min="0" />
                        </div>
                        <div class="space-y-2">
                            <Label>{{ t(`preferences.${side}.late_fee_percentage`) }}</Label>
                            <Input v-model.number="group(side).late_fee_percentage" type="number" min="0" max="100" step="0.1" />
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <Label>{{ t(`preferences.${side}.terms`) }}</Label>
                            <Textarea v-model="group(side).terms" rows="2" />
                        </div>
                        <div class="flex items-center gap-3">
                            <Switch :model-value="group(side).auto_reminders" @update:model-value="(v) => group(side).auto_reminders = v" />
                            <Label>{{ t(`preferences.${side}.auto_reminders`) }}</Label>
                        </div>
                        <div class="space-y-2">
                            <Label>{{ t(`preferences.${side}.reminder_days`) }}</Label>
                            <Input v-model.number="group(side).reminder_days" type="number" min="1" :disabled="!group(side).auto_reminders" />
                        </div>
                    </div>
                    <template v-if="side === 'sale'">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label>{{ t('preferences.sale.tax_percentage') }}</Label>
                                <Input v-model.number="group('sale').tax_percentage" type="number" min="0" max="100" step="0.1" />
                            </div>
                            <div class="flex items-center gap-3 md:pt-7">
                                <Switch :model-value="group('sale').auto_calculate_tax" @update:model-value="(v) => group('sale').auto_calculate_tax = v" />
                                <Label>{{ t('preferences.sale.auto_calculate_tax') }}</Label>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 rounded-lg bg-muted/30 p-3">
                            <Checkbox
                                id="company-enforce-reservation"
                                :checked="group('sale').enforce_sale_stock_reservation ?? false"
                                @update:checked="(v) => group('sale').enforce_sale_stock_reservation = v"
                            />
                            <div class="space-y-1">
                                <Label for="company-enforce-reservation" class="cursor-pointer font-medium">{{ t('preferences.sale.enforce_sale_stock_reservation') }}</Label>
                                <p class="text-sm text-muted-foreground">{{ t('preferences.sale.enforce_sale_stock_reservation_help') }}</p>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label>{{ t('preferences.sale.types.sale_order') }} · {{ t('preferences.sale.due_days') }}</Label>
                        <Input v-model.number="group('sale_order').due_days" type="number" min="0" />
                    </div>
                    <div class="space-y-2">
                        <Label>{{ t('preferences.sale.types.sale_return') }} · {{ t('company.settings.accepted_return_days') }}</Label>
                        <Input v-model.number="group('sale_return').accepted_return_days" type="number" min="0" />
                    </div>
                </div>
            </div>

            <!-- Posting -->
            <div v-show="active === 'posting'" class="space-y-3">
                <p class="text-sm text-muted-foreground">{{ t('preferences.transaction.post_immediately_help') }}</p>
                <div class="flex items-center gap-3 rounded-lg border border-primary/40 bg-primary/5 px-4 py-3">
                    <Checkbox id="company-post-all" :checked="allPosted" @update:checked="setAllPosted" />
                    <Label for="company-post-all" class="cursor-pointer font-medium">
                        {{ allPosted ? t('preferences.transaction.deselect_all') : t('preferences.transaction.select_all') }}
                    </Label>
                </div>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <div v-for="module in postModules" :key="module.key" class="flex items-center justify-between gap-3 rounded-lg border border-border px-4 py-2.5">
                        <Label :for="`company-${module.key}`" class="cursor-pointer font-normal">{{ t(module.label) }}</Label>
                        <Switch
                            :id="`company-${module.key}`"
                            :model-value="group('transaction')[module.key] ?? true"
                            @update:model-value="(v) => group('transaction')[module.key] = v"
                        />
                    </div>
                </div>
            </div>

            <!-- Stock adjustment -->
            <div v-show="active === 'stock_adjustment'" class="space-y-5">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label>{{ t('preferences.stock_adjustment.reference_prefix') }}</Label>
                        <Input v-model="group('stock_adjustment').reference_prefix" />
                    </div>
                    <div class="flex items-center gap-3 sm:pt-7">
                        <Switch
                            :model-value="group('stock_adjustment').allow_in_cost_override ?? true"
                            @update:model-value="(v) => group('stock_adjustment').allow_in_cost_override = v"
                        />
                        <Label>{{ t('preferences.stock_adjustment.allow_in_cost_override') }}</Label>
                    </div>
                </div>
                <div>
                    <Label class="font-medium">{{ t('preferences.stock_adjustment.reason_accounts') }}</Label>
                    <p class="mb-3 text-sm text-muted-foreground">{{ t('preferences.stock_adjustment.reason_accounts_help') }}</p>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <div v-for="reason in reasons" :key="reason.key" class="flex items-center justify-between gap-3 rounded-lg border border-border px-4 py-2.5">
                            <div class="flex items-center gap-2">
                                <Label class="font-normal">{{ t(`adjustment.reasons.${reason.key}`) }}</Label>
                                <span
                                    class="rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase"
                                    :class="reason.direction === 'in'
                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                        : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'"
                                >
                                    {{ t(`adjustment.direction.${reason.direction}`) }}
                                </span>
                            </div>
                            <Select
                                :model-value="group('stock_adjustment.reason_accounts')[reason.key]"
                                @update:model-value="(v) => group('stock_adjustment.reason_accounts')[reason.key] = v"
                            >
                                <SelectTrigger class="w-48"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="option in offsetAccounts" :key="option.id" :value="option.id">{{ t(option.label) }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Receipt & payment -->
            <div v-show="active === 'receipt_payment'" class="space-y-5">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label>{{ t('preferences.receipt_payment.default_cash_account') }}</Label>
                        <Select v-model="group('receipt_payment').default_cash_account">
                            <SelectTrigger><SelectValue :placeholder="t('preferences.receipt_payment.select_account')" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="account in cashAccounts" :key="account.id" :value="account.id">{{ account.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-2">
                        <Label>{{ t('preferences.receipt_payment.lock_after_days') }}</Label>
                        <Input v-model.number="group('receipt_payment').lock_after_days" type="number" min="0" />
                    </div>
                </div>
                <div class="flex flex-wrap gap-6">
                    <div class="flex items-center gap-3">
                        <Switch :model-value="group('receipt_payment').auto_sequence" @update:model-value="(v) => group('receipt_payment').auto_sequence = v" />
                        <Label>{{ t('preferences.receipt_payment.auto_sequence') }}</Label>
                    </div>
                    <div class="flex items-center gap-3">
                        <Switch :model-value="group('receipt_payment').require_approval" @update:model-value="(v) => group('receipt_payment').require_approval = v" />
                        <Label>{{ t('preferences.receipt_payment.require_approval') }}</Label>
                    </div>
                </div>
            </div>

            <!-- Tax & currency -->
            <div v-show="active === 'tax_currency'" class="flex flex-wrap gap-6">
                <div v-for="key in ['tax_plus', 'tax_minus', 'multi_currency_opening']" :key="key" class="flex items-center gap-3">
                    <Switch :model-value="group('tax_currency')[key]" @update:model-value="(v) => group('tax_currency')[key] = v" />
                    <Label>{{ t(`preferences.tax_currency.${key}`) }}</Label>
                </div>
            </div>

            <!-- Formats: how money, dates and lots are written -->
            <div v-show="active === 'formats'" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="space-y-2">
                    <Label>{{ t('preferences.appearance.decimal_places') }}</Label>
                    <Select v-model="group('appearance').decimal_places">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="places in [0, 2, 3]" :key="places" :value="places">{{ places }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="space-y-2">
                    <Label>{{ t('preferences.appearance.balance_nature_format') }}</Label>
                    <Select v-model="group('appearance').balance_nature_format">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="without_nature">{{ t('preferences.appearance.without_nature') }}</SelectItem>
                            <SelectItem value="with_nature">{{ t('preferences.appearance.with_nature') }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="space-y-2">
                    <Label>{{ t('preferences.localization.date_format') }}</Label>
                    <Select v-model="group('localization').date_format">
                        <SelectTrigger><SelectValue /></SelectTrigger>
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
                    <Select v-model="group('localization').time_format">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="12h">12-hour (AM/PM)</SelectItem>
                            <SelectItem value="24h">24-hour</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="space-y-2">
                    <Label>{{ t('preferences.localization.number_format') }}</Label>
                    <Select v-model="group('localization').number_format">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="1,000.00">1,000.00</SelectItem>
                            <SelectItem value="1.000,00">1.000,00</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="space-y-2">
                    <Label>{{ t('preferences.localization.timezone') }}</Label>
                    <Select v-model="group('localization').timezone">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="(label, value) in timezones" :key="value" :value="value">{{ label }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="space-y-2">
                    <Label>{{ t('preferences.localization.first_day_of_week') }}</Label>
                    <Select v-model="group('localization').first_day_of_week">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="saturday">{{ t('preferences.localization.saturday') }}</SelectItem>
                            <SelectItem value="sunday">{{ t('preferences.localization.sunday') }}</SelectItem>
                            <SelectItem value="monday">{{ t('preferences.localization.monday') }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="space-y-2">
                    <Label>{{ t('preferences.tax_currency.spec_text') }}</Label>
                    <Input v-model="group('item_management').spec_text" />
                </div>
            </div>

            <!-- Security -->
            <div v-show="active === 'security'" class="space-y-5">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label>{{ t('preferences.security.session_timeout') }}</Label>
                        <Input v-model.number="group('security').session_timeout" type="number" min="5" max="1440" />
                        <p class="text-sm text-muted-foreground">{{ t('preferences.security.session_timeout_hint') }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label>{{ t('preferences.security.login_attempts_limit') }}</Label>
                        <Input v-model.number="group('security').login_attempts_limit" type="number" min="3" max="10" />
                    </div>
                    <div class="space-y-2">
                        <Label>{{ t('preferences.security.password_min_length') }}</Label>
                        <Input v-model.number="group('security').password_min_length" type="number" min="6" max="32" />
                    </div>
                    <div class="flex items-center gap-3 md:pt-7">
                        <Switch :model-value="group('security').password_special_chars" @update:model-value="(v) => group('security').password_special_chars = v" />
                        <Label>{{ t('preferences.security.password_special_chars') }}</Label>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <Switch :model-value="group('security').two_factor_auth" @update:model-value="(v) => group('security').two_factor_auth = v" />
                    <Label>{{ t('preferences.security.two_factor_auth') }}</Label>
                </div>
                <div class="space-y-4 border-t border-border pt-5">
                    <Label class="text-base font-medium">{{ t('preferences.security.report_lock') }}</Label>
                    <div class="flex items-center gap-3">
                        <Switch :model-value="group('security').lock_reports" @update:model-value="(v) => group('security').lock_reports = v" />
                        <Label>{{ t('preferences.security.lock_reports') }}</Label>
                    </div>
                    <div v-if="group('security').lock_reports" class="max-w-sm space-y-2">
                        <Label>{{ t('preferences.security.lock_password') }}</Label>
                        <Input v-model="group('security').lock_password" type="password" autocomplete="new-password" />
                    </div>
                </div>
            </div>

            <!-- Backup -->
            <div v-show="active === 'backup'" class="space-y-5">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label>{{ t('preferences.backup.auto_backup') }}</Label>
                        <Select v-model="group('backup').auto_backup">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="option in ['none', 'daily', 'weekly', 'monthly']" :key="option" :value="option">{{ t(`preferences.backup.${option}`) }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-2">
                        <Label>{{ t('preferences.backup.backup_retention_days') }}</Label>
                        <Input v-model.number="group('backup').backup_retention_days" type="number" min="1" max="365" />
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <Switch :model-value="group('backup').cloud_backup" @update:model-value="(v) => group('backup').cloud_backup = v" />
                    <Label>{{ t('preferences.backup.cloud_backup') }}</Label>
                </div>
                <div v-if="group('backup').cloud_backup" class="max-w-sm space-y-2">
                    <Label>{{ t('preferences.backup.cloud_provider') }}</Label>
                    <Select v-model="group('backup').cloud_provider">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="aws">Amazon S3</SelectItem>
                            <SelectItem value="google">Google Cloud</SelectItem>
                            <SelectItem value="azure">Azure Blob</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="space-y-3">
                    <Label class="text-base font-medium">{{ t('preferences.backup.export_formats') }}</Label>
                    <div class="flex flex-wrap gap-6">
                        <div v-for="[key, label] in [['export_pdf', 'PDF'], ['export_excel', 'Excel'], ['export_csv', 'CSV']]" :key="key" class="flex items-center gap-2">
                            <Checkbox :id="`company-${key}`" :checked="group('backup')[key]" @update:checked="(v) => group('backup')[key] = v" />
                            <Label :for="`company-${key}`" class="cursor-pointer font-normal">{{ label }}</Label>
                        </div>
                    </div>
                </div>
            </div>
        </fieldset>
    </div>
</template>
