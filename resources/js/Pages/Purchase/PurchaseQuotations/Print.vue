<script setup>
import { computed, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { useColors } from '@/composables/useColors'
import '../../../../css/print/invoice.css'

const props = defineProps({
    quotation: { type: Object, required: true },
    company: { type: Object, default: () => ({}) },
})

const page = usePage()
const { t, locale } = useI18n()
const { resolveColor } = useColors()

const data = computed(() => props.quotation?.data ?? props.quotation ?? {})
const isRTL = computed(() => ['fa', 'ps', 'pa', 'ar', 'ur'].includes(locale.value))

const preferences = computed(() => page.props?.user_preferences ?? {})
const decimalPlaces = computed(() => preferences.value?.appearance?.decimal_places ?? 2)

const companyName = computed(() => {
    if (locale.value === 'ps') return props.company?.name_pa || props.company?.name_fa || props.company?.name_en || 'Nextbook'
    if (locale.value === 'fa') return props.company?.name_fa || props.company?.name_pa || props.company?.name_en || 'Nextbook'
    return props.company?.name_en || props.company?.name_fa || props.company?.name_pa || 'Nextbook'
})
const companyLogo = computed(() => {
    if (props.company?.logo_url) return props.company.logo_url
    if (props.company?.logo) return `/storage/${props.company.logo}`
    return null
})
const companyAddress = computed(() =>
    [props.company?.address, props.company?.city, props.company?.country].filter(Boolean).join(', ')
)

const currencySymbol = computed(() => data.value.currency?.symbol || '')
const fmt = (value) => Number(value || 0).toFixed(decimalPlaces.value)

const totalQuantity = computed(() =>
    Number((data.value.items || []).reduce((sum, item) => sum + Number(item.quantity || 0), 0))
)
const grandTotal = computed(() =>
    Number((data.value.items || []).reduce((sum, item) => sum + Number(item.line_total || 0), 0))
)

onMounted(() => {
    window.print()
})
</script>

<template>
    <div class="print-page" :dir="isRTL ? 'rtl' : 'ltr'">
        <div class="quotation-sheet">
            <!-- Header -->
            <div class="q-header">
                <div class="q-company">
                    <img v-if="companyLogo" :src="companyLogo" class="q-logo" alt="logo" />
                    <div>
                        <div class="q-company-name">{{ companyName }}</div>
                        <div v-if="companyAddress" class="q-muted">{{ companyAddress }}</div>
                        <div v-if="company?.phone" class="q-muted">{{ company.phone }}</div>
                    </div>
                </div>
                <div class="q-title-block">
                    <div class="q-title">{{ t('purchase_quotation.purchase_quotation') }}</div>
                    <div class="q-muted"># {{ data.number }}</div>
                </div>
            </div>

            <!-- Meta -->
            <div class="q-meta">
                <div>
                    <div class="q-muted">{{ t('ledger.supplier.supplier') }}</div>
                    <div class="q-strong">{{ data.supplier_name || '-' }}</div>
                </div>
                <div>
                    <div class="q-muted">{{ t('general.date') }}</div>
                    <div class="q-strong">{{ data.date }}</div>
                </div>
                <div>
                    <div class="q-muted">{{ t('purchase_quotation.valid_until') }}</div>
                    <div class="q-strong">{{ data.valid_until || '-' }}</div>
                </div>
            </div>

            <!-- Items -->
            <table class="q-table">
                <thead>
                    <tr>
                        <th class="q-num">#</th>
                        <th class="q-left">{{ t('item.item') }}</th>
                        <th class="q-left">{{ t('item.color') }}</th>
                        <th class="q-left">{{ t('item.size') }}</th>
                        <th class="q-right">{{ t('general.qty') }}</th>
                        <th class="q-left">{{ t('general.unit') }}</th>
                        <th class="q-right">{{ t('general.price') }}</th>
                        <th class="q-right">{{ t('general.total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in data.items" :key="item.id">
                        <td class="q-num">{{ index + 1 }}</td>
                        <td class="q-left">
                            <div class="q-strong">{{ item.item_name }}</div>
                            <div class="q-muted q-small">{{ item.item_code }}</div>
                        </td>
                        <td class="q-left">{{ resolveColor(item.color)?.name || '-' }}</td>
                        <td class="q-left">{{ item.size_name || '-' }}</td>
                        <td class="q-right">{{ item.quantity }}</td>
                        <td class="q-left">{{ item.unit_measure_name || '-' }}</td>
                        <td class="q-right">{{ fmt(item.unit_price) }}</td>
                        <td class="q-right">{{ fmt(item.line_total) }}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="q-right q-strong">{{ t('general.total') }}</td>
                        <td class="q-right q-strong">{{ totalQuantity }}</td>
                        <td></td>
                        <td></td>
                        <td class="q-right q-strong">{{ currencySymbol }} {{ fmt(grandTotal) }}</td>
                    </tr>
                </tfoot>
            </table>

            <div v-if="data.note" class="q-note">
                <div class="q-muted">{{ t('general.note') }}</div>
                <div>{{ data.note }}</div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.quotation-sheet {
    max-width: 800px;
    margin: 0 auto;
    padding: 24px;
    color: #111;
    font-size: 13px;
}
.q-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 2px solid #6d28d9;
    padding-bottom: 12px;
    margin-bottom: 16px;
}
.q-company { display: flex; gap: 12px; align-items: center; }
.q-logo { height: 56px; width: auto; object-fit: contain; }
.q-company-name { font-size: 18px; font-weight: 700; color: #6d28d9; }
.q-title-block { text-align: end; }
.q-title { font-size: 20px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
.q-muted { color: #666; }
.q-small { font-size: 11px; }
.q-strong { font-weight: 600; }
.q-meta {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 16px;
}
.q-table { width: 100%; border-collapse: collapse; }
.q-table th, .q-table td { border: 1px solid #ddd; padding: 6px 8px; }
.q-table thead th { background: #f3f0fb; text-align: start; font-size: 11px; text-transform: uppercase; color: #4c1d95; }
.q-left { text-align: start; }
.q-right { text-align: end; }
.q-num { text-align: center; width: 32px; }
.q-note { margin-top: 16px; }
@media print {
    .quotation-sheet { padding: 0; }
}
</style>
