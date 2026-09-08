<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePage } from '@inertiajs/vue3'
import { Switch } from '@/Components/ui/switch'
import { Label } from '@/Components/ui/label'
import { Input } from '@/Components/ui/input'
import { Textarea } from '@/Components/ui/textarea'
import { Button } from '@/Components/ui/button'
import { X, SlidersHorizontal } from 'lucide-vue-next'
import axios from 'axios'

const { t } = useI18n()
const page = usePage()

const props = defineProps({
    open: { type: Boolean, default: false },
    /**
     * Top-level preference group this panel edits.
     * One of: 'sale' | 'purchase' | 'item_management' | 'receipt_payment'
     */
    prefGroup: { type: String, default: '' },
    /** Parent-owned reactive object holding the current values of this group. */
    prefs: { type: Object, default: () => ({}) },
    /** Optional localized title for the panel header. */
    title: { type: String, default: '' },
    /**
     * Module slug for the "confirm before save" toggle (e.g. 'sale', 'purchase',
     * 'receipt', 'item'). When set, a toggle is shown that writes to
     * user_preferences.confirmations[module]. Leave empty to hide it.
     */
    module: { type: String, default: '' },
})

const emit = defineEmits(['update:open'])

const isRTL = computed(() => {
    const locale = page.props?.locale ?? page.props?.auth?.user?.locale ?? 'en'
    return ['fa', 'ps', 'ar'].includes(String(locale).toLowerCase())
})

const close = () => emit('update:open', false)

/* ----------------------------------------------------------------------------
 * Field definitions (mirrors resources/js/Pages/Preferences/Index.vue)
 * -------------------------------------------------------------------------- */
const generalFields = [
    { key: 'number', labelKey: 'preferences.fields.number' },
    { key: 'date', labelKey: 'preferences.fields.date' },
    { key: 'currency', labelKey: 'preferences.fields.currency' },
    { key: 'type', labelKey: 'preferences.fields.type' },
    { key: 'warehouse', labelKey: 'preferences.fields.warehouse' },
]

// Sale/Purchase invoices render a header-level description; orders currently do not.
const documentGeneralFields = [
    ...generalFields,
    { key: 'description', labelKey: 'preferences.fields.description' },
]

const saleItemColumns = [
    { key: 'batch', labelKey: 'preferences.fields.batch' },
    { key: 'expiry', labelKey: 'preferences.fields.expiry' },
    { key: 'colors', labelKey: 'preferences.fields.colors' },
    { key: 'size', labelKey: 'preferences.fields.size' },
    { key: 'on_hand', labelKey: 'preferences.fields.on_hand' },
    { key: 'reserved_out', labelKey: 'general.reserved_out' },
    { key: 'measure', labelKey: 'preferences.fields.measure' },
    { key: 'discount', labelKey: 'preferences.fields.discount' },
    { key: 'free', labelKey: 'preferences.fields.free' },
    { key: 'tax', labelKey: 'preferences.fields.tax' },
]

const purchaseItemColumns = [
    { key: 'batch', labelKey: 'preferences.fields.batch' },
    { key: 'expiry', labelKey: 'preferences.fields.expiry' },
    { key: 'colors', labelKey: 'preferences.fields.colors' },
    { key: 'size', labelKey: 'preferences.fields.size' },
    { key: 'on_hand', labelKey: 'preferences.fields.on_hand' },
    { key: 'reserved_in', labelKey: 'general.reserved_in' },
    { key: 'measure', labelKey: 'preferences.fields.measure' },
    { key: 'discount', labelKey: 'preferences.fields.discount' },
    { key: 'free', labelKey: 'preferences.fields.free' },
    { key: 'tax', labelKey: 'preferences.fields.tax' },
]

const saleOrderItemColumns = [
    { key: 'batch', labelKey: 'preferences.fields.batch' },
    { key: 'expiry', labelKey: 'preferences.fields.expiry' },
    { key: 'measure', labelKey: 'preferences.fields.measure' },
    { key: 'discount', labelKey: 'preferences.fields.discount' },
    { key: 'free', labelKey: 'preferences.fields.free' },
    { key: 'size', labelKey: 'preferences.fields.size' },
    { key: 'category', labelKey: 'preferences.fields.category' },
]

// Purchase orders render the same item columns as sale orders.
const purchaseOrderItemColumns = saleOrderItemColumns

// Return documents pick items from an existing bill, so the editable header is
// small and the line grid only shows identity columns (batch / colour / size).
const returnGeneralFields = [
    { key: 'number', labelKey: 'preferences.fields.number' },
    { key: 'date', labelKey: 'preferences.fields.date' },
    { key: 'description', labelKey: 'preferences.fields.description' },
]

const returnItemColumns = [
    { key: 'batch', labelKey: 'preferences.fields.batch' },
    { key: 'colors', labelKey: 'preferences.fields.colors' },
    { key: 'size', labelKey: 'preferences.fields.size' },
]

// Every toggleable item field — mirrors config/business_profiles.php `base.fields`,
// so an owner can switch on anything their trade's profile hid. `accounts` and
// `is_active` are always shown and not listed here. Labels come from `item.*`.
const itemManagementFields = [
    { key: 'code', labelKey: 'item.code' },
    { key: 'generic_name', labelKey: 'item.generic_name' },
    { key: 'packing', labelKey: 'item.packing' },
    { key: 'description', labelKey: 'item.description' },
    { key: 'photo', labelKey: 'item.photo' },
    { key: 'item_type', labelKey: 'item.item_type' },
    { key: 'category', labelKey: 'admin.category.category' },
    { key: 'brand', labelKey: 'admin.brand.brand' },
    { key: 'unit_measure', labelKey: 'admin.unit_measure.unit_measure' },
    { key: 'model', labelKey: 'item.model' },
    { key: 'rate_a', labelKey: 'item.rate_a' },
    { key: 'rate_b', labelKey: 'item.rate_b' },
    { key: 'rate_c', labelKey: 'item.rate_c' },
    { key: 'pricing_method', labelKey: 'item.pricing_method' },
    { key: 'costing_method', labelKey: 'item.costing_method' },
    { key: 'reorder_quantity', labelKey: 'item.reorder_quantity' },
    { key: 'lead_time_days', labelKey: 'item.lead_time_days' },
    { key: 'default_warehouse', labelKey: 'item.default_warehouse' },
    { key: 'rack_no', labelKey: 'item.rack_no' },
    { key: 'fast_search', labelKey: 'item.fast_search' },
    { key: 'weight', labelKey: 'item.weight' },
    { key: 'dimensions', labelKey: 'item.dimensions' },
    { key: 'manufacturer', labelKey: 'item.manufacturer' },
    { key: 'country_of_origin', labelKey: 'item.country_of_origin' },
    { key: 'hs_code', labelKey: 'item.hs_code' },
    { key: 'warranty_months', labelKey: 'item.warranty_months' },
    { key: 'shelf_life_days', labelKey: 'item.shelf_life_days' },
    { key: 'min_shelf_life_percent', labelKey: 'item.min_shelf_life_percent' },
    { key: 'storage_zone', labelKey: 'item.storage_zone' },
    { key: 'requires_prescription', labelKey: 'item.requires_prescription' },
    { key: 'is_controlled', labelKey: 'item.is_controlled' },
    { key: 'is_batch_tracked', labelKey: 'item.is_batch_tracked' },
    { key: 'is_expiry_tracked', labelKey: 'item.is_expiry_tracked' },
    { key: 'is_serial_tracked', labelKey: 'item.is_serial_tracked' },
    { key: 'is_stockable', labelKey: 'item.is_stockable' },
    { key: 'is_sellable', labelKey: 'item.is_sellable' },
    { key: 'is_purchasable', labelKey: 'item.is_purchasable' },
    { key: 'is_weighted', labelKey: 'item.is_weighted' },
    { key: 'allow_negative_stock', labelKey: 'item.allow_negative_stock' },
    { key: 'show_in_pos', labelKey: 'item.show_in_pos' },
]

const receiptPaymentFields = [
    { key: 'number', labelKey: 'preferences.fields.number' },
    { key: 'currency', labelKey: 'preferences.fields.currency' },
    { key: 'cheque_number', labelKey: 'preferences.receipt_fields.cheque_number' },
    { key: 'debit_account', labelKey: 'preferences.receipt_fields.debit_account' },
    { key: 'ledger_old_balance', labelKey: 'preferences.receipt_fields.ledger_old_balance' },
]

/* ----------------------------------------------------------------------------
 * Per-group schema. Invoice theme is intentionally excluded.
 * -------------------------------------------------------------------------- */
const SCHEMAS = {
    sale: [
        { type: 'toggles', group: 'general_fields', titleKey: 'preferences.sale.general_fields', items: documentGeneralFields },
        { type: 'toggles', group: 'item_columns', titleKey: 'preferences.sale.item_columns', items: saleItemColumns },
        {
            type: 'fields', titleKey: 'general.settings', items: [
                { key: 'invoice_prefix', type: 'text', labelKey: 'preferences.sale.invoice_prefix' },
                { key: 'start_number', type: 'number', labelKey: 'preferences.sale.start_number', min: 1 },
                { key: 'due_days', type: 'number', labelKey: 'preferences.sale.due_days', min: 0 },
                { key: 'terms', type: 'textarea', labelKey: 'preferences.sale.terms' },
                { key: 'auto_reminders', type: 'switch', labelKey: 'preferences.sale.auto_reminders' },
                { key: 'reminder_days', type: 'number', labelKey: 'preferences.sale.reminder_days', min: 1, dependsOn: 'auto_reminders' },
                { key: 'late_fee_percentage', type: 'number', labelKey: 'preferences.sale.late_fee_percentage', min: 0, max: 100, step: '0.1' },
                { key: 'tax_percentage', type: 'number', labelKey: 'preferences.sale.tax_percentage', min: 0, max: 100, step: '0.1' },
                { key: 'auto_calculate_tax', type: 'switch', labelKey: 'preferences.sale.auto_calculate_tax' },
                { key: 'show_ledger_transactions', type: 'switch', labelKey: 'preferences.sale.show_ledger_transactions' },
                { key: 'show_item_transactions', type: 'switch', labelKey: 'preferences.sale.show_item_transactions' },
                { key: 'show_attachments', type: 'switch', labelKey: 'preferences.sale.show_attachments' },
                { key: 'enforce_sale_stock_reservation', type: 'switch', labelKey: 'preferences.sale.enforce_sale_stock_reservation' },
            ]
        },
    ],
    sale_order: [
        { type: 'toggles', group: 'general_fields', titleKey: 'preferences.sale.general_fields', items: generalFields },
        { type: 'toggles', group: 'item_columns', titleKey: 'preferences.sale.item_columns', items: saleOrderItemColumns },
        {
            type: 'fields', titleKey: 'general.settings', items: [
                { key: 'invoice_prefix', type: 'text', labelKey: 'preferences.sale.invoice_prefix' },
                { key: 'start_number', type: 'number', labelKey: 'preferences.sale.start_number', min: 1 },
                { key: 'due_days', type: 'number', labelKey: 'preferences.sale.due_days', min: 0 },
                { key: 'terms', type: 'textarea', labelKey: 'preferences.sale.terms' },
            ]
        },
    ],
    sale_return: [
        { type: 'toggles', group: 'general_fields', titleKey: 'preferences.sale.general_fields', items: returnGeneralFields },
        { type: 'toggles', group: 'item_columns', titleKey: 'preferences.sale.item_columns', items: returnItemColumns },
    ],
    sale_quotation: [
        { type: 'toggles', group: 'general_fields', titleKey: 'preferences.sale.general_fields', items: generalFields },
        { type: 'toggles', group: 'item_columns', titleKey: 'preferences.sale.item_columns', items: saleOrderItemColumns },
        {
            type: 'fields', titleKey: 'general.settings', items: [
                { key: 'invoice_prefix', type: 'text', labelKey: 'preferences.sale.invoice_prefix' },
                { key: 'start_number', type: 'number', labelKey: 'preferences.sale.start_number', min: 1 },
            ]
        },
    ],
    purchase: [
        { type: 'toggles', group: 'general_fields', titleKey: 'preferences.purchase.general_fields', items: documentGeneralFields },
        { type: 'toggles', group: 'item_columns', titleKey: 'preferences.purchase.item_columns', items: purchaseItemColumns },
        {
            type: 'fields', titleKey: 'general.settings', items: [
                { key: 'invoice_prefix', type: 'text', labelKey: 'preferences.purchase.invoice_prefix' },
                { key: 'start_number', type: 'number', labelKey: 'preferences.purchase.start_number', min: 1 },
                { key: 'due_days', type: 'number', labelKey: 'preferences.purchase.due_days', min: 0 },
                { key: 'terms', type: 'textarea', labelKey: 'preferences.purchase.terms' },
                { key: 'auto_reminders', type: 'switch', labelKey: 'preferences.purchase.auto_reminders' },
                { key: 'reminder_days', type: 'number', labelKey: 'preferences.purchase.reminder_days', min: 1, dependsOn: 'auto_reminders' },
                { key: 'late_fee_percentage', type: 'number', labelKey: 'preferences.purchase.late_fee_percentage', min: 0, max: 100, step: '0.1' },
                { key: 'show_ledger_transactions', type: 'switch', labelKey: 'preferences.purchase.show_ledger_transactions' },
                { key: 'show_item_transactions', type: 'switch', labelKey: 'preferences.purchase.show_item_transactions' },
                { key: 'show_attachments', type: 'switch', labelKey: 'preferences.purchase.show_attachments' },
            ]
        },
    ],
    purchase_order: [
        { type: 'toggles', group: 'general_fields', titleKey: 'preferences.purchase.general_fields', items: generalFields },
        { type: 'toggles', group: 'item_columns', titleKey: 'preferences.purchase.item_columns', items: purchaseOrderItemColumns },
        {
            type: 'fields', titleKey: 'general.settings', items: [
                { key: 'invoice_prefix', type: 'text', labelKey: 'preferences.purchase.invoice_prefix' },
                { key: 'start_number', type: 'number', labelKey: 'preferences.purchase.start_number', min: 1 },
                { key: 'due_days', type: 'number', labelKey: 'preferences.purchase.due_days', min: 0 },
                { key: 'terms', type: 'textarea', labelKey: 'preferences.purchase.terms' },
            ]
        },
    ],
    purchase_return: [
        { type: 'toggles', group: 'general_fields', titleKey: 'preferences.purchase.general_fields', items: returnGeneralFields },
        { type: 'toggles', group: 'item_columns', titleKey: 'preferences.purchase.item_columns', items: returnItemColumns },
    ],
    purchase_quotation: [
        { type: 'toggles', group: 'general_fields', titleKey: 'preferences.purchase.general_fields', items: generalFields },
        { type: 'toggles', group: 'item_columns', titleKey: 'preferences.purchase.item_columns', items: purchaseOrderItemColumns },
        {
            type: 'fields', titleKey: 'general.settings', items: [
                { key: 'invoice_prefix', type: 'text', labelKey: 'preferences.purchase.invoice_prefix' },
                { key: 'start_number', type: 'number', labelKey: 'preferences.purchase.start_number', min: 1 },
            ]
        },
    ],
    item_management: [
        { type: 'toggles', group: 'visible_fields', titleKey: 'preferences.item_management.visible_fields', items: itemManagementFields },
        {
            type: 'fields', titleKey: 'general.settings', items: [
                { key: 'spec_text', type: 'text', labelKey: 'preferences.tax_currency.spec_text' },
            ]
        },
    ],
    receipt_payment: [
        { type: 'toggles', group: 'visible_fields', titleKey: 'preferences.receipt_payment.visible_fields', items: receiptPaymentFields },
        {
            type: 'fields', titleKey: 'general.settings', items: [
                { key: 'lock_after_days', type: 'number', labelKey: 'preferences.receipt_payment.lock_after_days', min: 0 },
                { key: 'auto_sequence', type: 'switch', labelKey: 'preferences.receipt_payment.auto_sequence' },
                { key: 'require_approval', type: 'switch', labelKey: 'preferences.receipt_payment.require_approval' },
            ]
        },
    ],
}

const sections = computed(() => SCHEMAS[props.prefGroup] ?? [])

const headerTitle = computed(() => props.title || t('general.settings'))

/* ----------------------------------------------------------------------------
 * Reading / writing values against the parent's reactive prefs object.
 * -------------------------------------------------------------------------- */
// Item-field visibility is driven by the company's business profile first, then
// the owner's per-field overrides. So a toggle with no explicit override should
// reflect what the trade already switched on — otherwise the panel shows OFF for
// a field the form is actually rendering.
const profileFields = computed(
    () => (props.prefGroup === 'item_management' && page.props?.business_profile?.fields) || {}
)

const groupValue = (group, key) => {
    const override = props.prefs?.[group]?.[key]
    if (override !== undefined && override !== null) return override
    if (group === 'visible_fields' && key in profileFields.value) {
        return Boolean(profileFields.value[key])
    }
    return false
}
const fieldValue = (key) => props.prefs?.[key]

const persist = () => {
    axios.put(route('preferences.update'), {
        [props.prefGroup]: JSON.parse(JSON.stringify(props.prefs)),
    }).catch((e) => console.error('Failed to save preference', e))
}

const setGroup = (group, key, value) => {
    if (!props.prefs[group] || typeof props.prefs[group] !== 'object') {
        props.prefs[group] = {}
    }
    props.prefs[group][key] = value
    persist()
}

const setField = (key, value) => {
    props.prefs[key] = value
    persist()
}

const isDisabled = (field) => field.dependsOn ? !props.prefs?.[field.dependsOn] : false

/* ----------------------------------------------------------------------------
 * "Confirm before save" toggle — stored under user_preferences.confirmations[module].
 * Persisted independently of the page's preference group so it works for every module.
 * -------------------------------------------------------------------------- */
const confirmOnSave = computed(() => {
    if (!props.module) return false
    return page.props?.user_preferences?.confirmations?.[props.module] ?? true
})

const setConfirmOnSave = (value) => {
    if (!props.module) return
    if (!page.props.user_preferences) page.props.user_preferences = {}
    page.props.user_preferences.confirmations = {
        ...(page.props.user_preferences.confirmations || {}),
        [props.module]: value,
    }
    axios.put(route('preferences.update'), {
        confirmations: { [props.module]: value },
    }).catch((e) => console.error('Failed to save confirmation preference', e))
}

/* ----------------------------------------------------------------------------
 * "Show field hints" — the inline one-line descriptions under form inputs.
 * A single app-wide toggle (appearance.show_field_hints), surfaced on every
 * module's panel so it can be turned off from wherever the clutter is noticed.
 * -------------------------------------------------------------------------- */
const showFieldHints = computed(
    () => page.props?.user_preferences?.appearance?.show_field_hints ?? true
)

const setShowFieldHints = (value) => {
    if (!page.props.user_preferences) page.props.user_preferences = {}
    page.props.user_preferences.appearance = {
        ...(page.props.user_preferences.appearance || {}),
        show_field_hints: value,
    }
    axios.put(route('preferences.update'), {
        appearance: { show_field_hints: value },
    }).catch((e) => console.error('Failed to save field-hints preference', e))
}
</script>

<template>
    <Transition name="fade">
        <div
            v-if="open"
            class="fixed inset-0 z-40 bg-black/30 backdrop-blur-[1px]"
            @click="close"
        />
    </Transition>

    <Transition :name="isRTL ? 'slide-left' : 'slide-right'">
        <div
            v-if="open"
            :class="[
                'fixed top-0 z-50 h-full w-[400px] max-w-[92vw] bg-card shadow-2xl flex flex-col',
                isRTL ? 'left-0 border-r border-border' : 'right-0 border-l border-border'
            ]"
        >
            <!-- Header -->
            <div class="flex items-center justify-between px-4 py-3 border-b border-border bg-primary/5">
                <div class="flex items-center gap-2 text-primary">
                    <SlidersHorizontal class="w-4 h-4" />
                    <span class="font-semibold text-sm">{{ headerTitle }}</span>
                </div>
                <Button variant="ghost" size="icon" class="h-7 w-7 rounded-full" @click="close">
                    <X class="w-4 h-4" />
                </Button>
            </div>

            <!-- Body -->
            <div class="flex-1 overflow-y-auto p-4 space-y-5">
                <!-- Confirm before save (per-module) + field hints (app-wide) -->
                <div class="space-y-2">
                    <div class="text-xs font-semibold uppercase tracking-wide text-primary/80">
                        {{ t('general.general') }}
                    </div>
                    <div v-if="module" class="flex items-center justify-between rounded-lg border border-border px-3 py-2">
                        <Label class="text-sm font-normal">{{ t('general.confirm_before_save') }}</Label>
                        <Switch
                            :model-value="confirmOnSave"
                            @update:model-value="setConfirmOnSave"
                        />
                    </div>
                    <div class="flex items-center justify-between rounded-lg border border-border px-3 py-2">
                        <Label class="text-sm font-normal">{{ t('preferences.appearance.show_field_hints') }}</Label>
                        <Switch
                            :model-value="showFieldHints"
                            @update:model-value="setShowFieldHints"
                        />
                    </div>
                </div>

                <div v-for="(section, sIdx) in sections" :key="sIdx" class="space-y-2">
                    <div class="text-xs font-semibold uppercase tracking-wide text-primary/80">
                        {{ t(section.titleKey) }}
                    </div>

                    <!-- Toggle groups (general_fields / item_columns / visible_fields) -->
                    <template v-if="section.type === 'toggles'">
                        <div class="rounded-lg border border-border divide-y divide-border overflow-hidden">
                            <div
                                v-for="item in section.items"
                                :key="item.key"
                                class="flex items-center justify-between px-3 py-2 hover:bg-muted/50 transition-colors cursor-pointer"
                                @click="setGroup(section.group, item.key, !groupValue(section.group, item.key))"
                            >
                                <Label class="cursor-pointer text-sm font-normal select-none">
                                    {{ t(item.labelKey) }}
                                </Label>
                                <Switch
                                    :model-value="groupValue(section.group, item.key)"
                                    @update:model-value="(v) => setGroup(section.group, item.key, v)"
                                    @click.stop
                                />
                            </div>
                        </div>
                    </template>

                    <!-- Scalar settings -->
                    <template v-else>
                        <div class="space-y-3">
                            <template v-for="field in section.items" :key="field.key">
                                <!-- Switch row -->
                                <div
                                    v-if="field.type === 'switch'"
                                    class="flex items-center justify-between rounded-lg border border-border px-3 py-2"
                                >
                                    <Label class="text-sm font-normal">{{ t(field.labelKey) }}</Label>
                                    <Switch
                                        :model-value="!!fieldValue(field.key)"
                                        @update:model-value="(v) => setField(field.key, v)"
                                    />
                                </div>

                                <!-- Textarea -->
                                <div v-else-if="field.type === 'textarea'" class="space-y-1.5">
                                    <Label class="text-sm font-normal">{{ t(field.labelKey) }}</Label>
                                    <Textarea
                                        :model-value="fieldValue(field.key) ?? ''"
                                        rows="2"
                                        @update:model-value="(v) => setField(field.key, v)"
                                    />
                                </div>

                                <!-- Text / number -->
                                <div v-else class="space-y-1.5">
                                    <Label class="text-sm font-normal">{{ t(field.labelKey) }}</Label>
                                    <Input
                                        :type="field.type === 'number' ? 'number' : 'text'"
                                        :min="field.min"
                                        :max="field.max"
                                        :step="field.step"
                                        :disabled="isDisabled(field)"
                                        :model-value="fieldValue(field.key) ?? ''"
                                        @update:model-value="(v) => setField(field.key, field.type === 'number' ? (v === '' ? null : Number(v)) : v)"
                                    />
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.slide-right-enter-active, .slide-right-leave-active { transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1); }
.slide-right-enter-from, .slide-right-leave-to { transform: translateX(100%); }

.slide-left-enter-active, .slide-left-leave-active { transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1); }
.slide-left-enter-from, .slide-left-leave-to { transform: translateX(-100%); }
</style>
