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

const baseItemColumns = [
    { key: 'batch', labelKey: 'preferences.fields.batch' },
    { key: 'expiry', labelKey: 'preferences.fields.expiry' },
    { key: 'on_hand', labelKey: 'preferences.fields.on_hand' },
    { key: 'measure', labelKey: 'preferences.fields.measure' },
    { key: 'discount', labelKey: 'preferences.fields.discount' },
    { key: 'free', labelKey: 'preferences.fields.free' },
    { key: 'tax', labelKey: 'preferences.fields.tax' },
]

const saleItemColumns = [
    ...baseItemColumns.slice(0, 3),
    { key: 'reserved_out', labelKey: 'general.reserved_out' },
    ...baseItemColumns.slice(3),
]

const purchaseItemColumns = [
    ...baseItemColumns.slice(0, 3),
    { key: 'reserved_in', labelKey: 'general.reserved_in' },
    ...baseItemColumns.slice(3),
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

const itemManagementFields = [
    { key: 'code', labelKey: 'preferences.item_fields.code' },
    { key: 'generic_name', labelKey: 'preferences.item_fields.generic_name' },
    { key: 'packing', labelKey: 'preferences.item_fields.packing' },
    { key: 'colors', labelKey: 'preferences.item_fields.colors' },
    { key: 'size', labelKey: 'preferences.item_fields.size' },
    { key: 'brand', labelKey: 'preferences.item_fields.brand' },
    { key: 'minimum_stock', labelKey: 'preferences.item_fields.minimum_stock' },
    { key: 'maximum_stock', labelKey: 'preferences.item_fields.maximum_stock' },
    { key: 'file_upload', labelKey: 'preferences.item_fields.file_upload' },
    { key: 'rate_a', labelKey: 'preferences.item_fields.rate_a' },
    { key: 'rate_b', labelKey: 'preferences.item_fields.rate_b' },
    { key: 'rate_c', labelKey: 'preferences.item_fields.rate_c' },
    { key: 'barcode', labelKey: 'preferences.item_fields.barcode' },
    { key: 'rack_no', labelKey: 'preferences.item_fields.rack_no' },
    { key: 'fast_search', labelKey: 'preferences.item_fields.fast_search' },
    { key: 'item_type', labelKey: 'preferences.fields.item_type' },
    { key: 'sku', labelKey: 'preferences.fields.sku' },
    { key: 'is_batch_tracked', labelKey: 'preferences.item_fields.is_batch_tracked' },
    { key: 'is_expiry_tracked', labelKey: 'preferences.item_fields.is_expiry_tracked' },
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
        { type: 'toggles', group: 'general_fields', titleKey: 'preferences.sale.general_fields', items: generalFields },
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
    purchase: [
        { type: 'toggles', group: 'general_fields', titleKey: 'preferences.purchase.general_fields', items: generalFields },
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
const groupValue = (group, key) => props.prefs?.[group]?.[key] ?? false
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
                <!-- Confirm before save (per-module) -->
                <div v-if="module" class="space-y-2">
                    <div class="text-xs font-semibold uppercase tracking-wide text-primary/80">
                        {{ t('general.general') }}
                    </div>
                    <div class="flex items-center justify-between rounded-lg border border-border px-3 py-2">
                        <Label class="text-sm font-normal">{{ t('general.confirm_before_save') }}</Label>
                        <Switch
                            :model-value="confirmOnSave"
                            @update:model-value="setConfirmOnSave"
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
