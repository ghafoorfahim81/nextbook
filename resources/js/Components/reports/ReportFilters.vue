<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Button } from '@/Components/ui/button'
import ReportFilterDate from '@/Components/reports/ReportFilterDate.vue'
import ReportFilterSelect from '@/Components/reports/ReportFilterSelect.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import NextDate from '@/Components/next/NextDatePicker.vue'

const props = defineProps({
  filters: { type: Object, required: true },
  options: { type: Object, required: true },
  activeDefinition: { type: Object, required: true },
  reportList: { type: Array, required: true },
  showReportSelect: { type: Boolean, default: true },
})

const emit = defineEmits(['update:filters', 'submit', 'reset'])
const { t } = useI18n()

const showLedger = computed(() => props.activeDefinition.filters.includes('ledger_id'))
const showCustomer = computed(() => props.activeDefinition.filters.includes('customer_id'))
const showSupplier = computed(() => props.activeDefinition.filters.includes('supplier_id'))
const showItem = computed(() => props.activeDefinition.filters.includes('item_id'))
const showAccount = computed(() => props.activeDefinition.filters.includes('account_id'))

const perPageOptions = [
  { value: 15, label: '15' },
  { value: 25, label: '25' },
  { value: 50, label: '50' },
  { value: 100, label: '100' },
]

const reportOptions = computed(() => props.reportList.map(report => ({
  key: report.key,
  label: report.label,
})))

const branchOptions = computed(() => props.options.branches || [])

function withPlaceholder(items, label, labelKey = 'name', valueKey = 'id') {
  return [
    { [valueKey]: '', [labelKey]: label },
    ...(items || []),
  ]
}

const ledgerOptions = computed(() => withPlaceholder(props.options.ledgers, t('report.filters.ledger')))
const customerOptions = computed(() => withPlaceholder(props.options.customers, t('report.filters.customer')))
const supplierOptions = computed(() => withPlaceholder(props.options.suppliers, t('report.filters.supplier')))
const itemOptions = computed(() => withPlaceholder(props.options.items, t('report.filters.item')))
const accountOptions = computed(() => withPlaceholder(props.options.cash_accounts, t('report.filters.account')))

function updateFilters(next) {
  emit('update:filters', next)
}

function setFilter(key, value) {
  updateFilters({
    ...props.filters,
    [key]: value,
    page: 1,
  })
}

function setReport(report) {
  updateFilters({
    ...props.filters,
    report,
    ledger_id: '',
    customer_id: '',
    supplier_id: '',
    item_id: '',
    account_id: '',
    page: 1,
  })
}
</script>

<template>
  <div class="rounded-2xl border border-border bg-card shadow-sm">
    <div class="border-b border-border px-5 py-4">
      <h2 class="text-base font-semibold text-card-foreground">{{ t('report.filters.title') }}</h2>
      <p v-if="activeDefinition.snapshot" class="mt-1 text-sm text-muted-foreground">
        {{ t('report.snapshot_note') }}
      </p>
    </div>

    <div class="space-y-4 p-5">
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4"> 
        <div v-if="showReportSelect">  
          <NextSelect
            :floating-text="t('report.filters.report')"
            :model-value="filters.report"
            :options="reportOptions"
            label-key="label"
            value-key="key"
            :clearable="false"
            @update:modelValue="setReport"
          /> 
        </div>
          <NextSelect
          :floating-text="t('report.filters.branch')"
          :model-value="filters.branch_id"
          :options="branchOptions"
          :clearable="false"
          label-key="name"
          value-key="id"
          @update:modelValue="setFilter('branch_id', $event)"
        />   
          <NextSelect
            :floating-text="t('report.filters.per_page')"
            :model-value="filters.per_page"
            :options="perPageOptions"
            label-key="label"
            value-key="value"
            :clearable="false"
            :empty-value="15"
            @update:modelValue="setFilter('per_page', Number($event))"
          /> 
          <NextDate v-model="filters.date_from"  :label="t('general.date')" :placeholder="t('general.enter', { text: t('report.filters.date_from') })" />
       
          <NextDate v-model="filters.date_to"   :label="t('general.date')" :placeholder="t('general.enter', { text: t('report.filters.date_to') })" />
       

        <div v-if="showLedger">
          <NextSelect
            :floating-text="t('report.filters.ledger')"
            :model-value="filters.ledger_id"
            :options="ledgerOptions"
            :clearable="false"
            label-key="name"
            value-key="id"
            @update:modelValue="setFilter('ledger_id', $event)"
          />
          </div>
          <div v-if="showCustomer">
          <NextSelect
            :floating-text="t('report.filters.customer')"
            :model-value="filters.customer_id"
            :options="customerOptions"
            label-key="name"
            value-key="id"
            :clearable="false"
            @update:modelValue="setFilter('customer_id', $event)"
          />
          </div>
          <div v-if="showSupplier">
          <NextSelect
            :floating-text="t('report.filters.supplier')"
            :model-value="filters.supplier_id"
            :options="supplierOptions"
            label-key="name"
            value-key="id"
            :clearable="false"
            @update:modelValue="setFilter('supplier_id', $event)"
          />
          </div>
          <div v-if="showItem">
          <NextSelect
            :floating-text="t('report.filters.item')"
            :model-value="filters.item_id"
            :options="itemOptions"
            :clearable="false"
            @update:modelValue="setFilter('item_id', $event)"
          /> 
          </div>
        <div v-if="showAccount">
          <NextSelect
            :floating-text="t('report.filters.account')"
            :model-value="filters.account_id"
            :options="accountOptions"
            label-key="name"
            value-key="id"
            :clearable="false"
            @update:modelValue="setFilter('account_id', $event)"
          /> 
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <Button @click="$emit('submit')">{{ t('report.filters.apply') }}</Button>
        <Button variant="outline" @click="$emit('reset')">{{ t('report.filters.reset') }}</Button>
      </div>
    </div>
  </div>
</template>
