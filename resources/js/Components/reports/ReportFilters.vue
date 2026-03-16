<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Button } from '@/Components/ui/button'

const props = defineProps({
  filters: { type: Object, required: true },
  options: { type: Object, required: true },
  activeDefinition: { type: Object, required: true },
  reportList: { type: Array, required: true },
  showReportSelect: { type: Boolean, default: true },
})

const emit = defineEmits(['update:filters', 'submit', 'reset'])
const { t } = useI18n()

const baseSelectClass = 'h-10 w-full rounded-lg border border-border bg-background px-3 text-sm text-foreground shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20'
const baseInputClass = `${baseSelectClass} [color-scheme:light] dark:[color-scheme:dark]`

const showLedger = computed(() => props.activeDefinition.filters.includes('ledger_id'))
const showCustomer = computed(() => props.activeDefinition.filters.includes('customer_id'))
const showSupplier = computed(() => props.activeDefinition.filters.includes('supplier_id'))
const showItem = computed(() => props.activeDefinition.filters.includes('item_id'))
const showAccount = computed(() => props.activeDefinition.filters.includes('account_id'))

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
      <div class="grid gap-4 lg:grid-cols-4">
        <label v-if="showReportSelect" class="space-y-2 lg:col-span-2">
          <span class="text-sm font-medium text-foreground">{{ t('report.filters.report') }}</span>
          <select :value="filters.report" :class="baseSelectClass" @change="setReport($event.target.value)">
            <option v-for="report in reportList" :key="report.key" :value="report.key">
              {{ report.label }}
            </option>
          </select>
        </label>

        <label class="space-y-2">
          <span class="text-sm font-medium text-foreground">{{ t('report.filters.branch') }}</span>
          <select :value="filters.branch_id" :class="baseSelectClass" @change="setFilter('branch_id', $event.target.value)">
            <option v-for="branch in options.branches || []" :key="branch.id" :value="branch.id">
              {{ branch.name }}
            </option>
          </select>
        </label>

        <label class="space-y-2">
          <span class="text-sm font-medium text-foreground">{{ t('report.filters.per_page') }}</span>
          <select :value="filters.per_page" :class="baseSelectClass" @change="setFilter('per_page', Number($event.target.value))">
            <option :value="15">15</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
          </select>
        </label>
      </div>

      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <label class="space-y-2">
          <span class="text-sm font-medium text-foreground">{{ t('report.filters.date_from') }}</span>
          <input :value="filters.date_from" type="date" :class="baseInputClass" @input="setFilter('date_from', $event.target.value)" />
        </label>

        <label class="space-y-2">
          <span class="text-sm font-medium text-foreground">{{ t('report.filters.date_to') }}</span>
          <input :value="filters.date_to" type="date" :class="baseInputClass" @input="setFilter('date_to', $event.target.value)" />
        </label>

        <label v-if="showLedger" class="space-y-2">
          <span class="text-sm font-medium text-foreground">{{ t('report.filters.ledger') }}</span>
          <select :value="filters.ledger_id" :class="baseSelectClass" @change="setFilter('ledger_id', $event.target.value)">
            <option value="">{{ t('report.filters.ledger') }}</option>
            <option v-for="ledger in options.ledgers || []" :key="ledger.id" :value="ledger.id">
              {{ ledger.name }}
            </option>
          </select>
        </label>

        <label v-if="showCustomer" class="space-y-2">
          <span class="text-sm font-medium text-foreground">{{ t('report.filters.customer') }}</span>
          <select :value="filters.customer_id" :class="baseSelectClass" @change="setFilter('customer_id', $event.target.value)">
            <option value="">{{ t('report.filters.customer') }}</option>
            <option v-for="customer in options.customers || []" :key="customer.id" :value="customer.id">
              {{ customer.name }}
            </option>
          </select>
        </label>

        <label v-if="showSupplier" class="space-y-2">
          <span class="text-sm font-medium text-foreground">{{ t('report.filters.supplier') }}</span>
          <select :value="filters.supplier_id" :class="baseSelectClass" @change="setFilter('supplier_id', $event.target.value)">
            <option value="">{{ t('report.filters.supplier') }}</option>
            <option v-for="supplier in options.suppliers || []" :key="supplier.id" :value="supplier.id">
              {{ supplier.name }}
            </option>
          </select>
        </label>

        <label v-if="showItem" class="space-y-2">
          <span class="text-sm font-medium text-foreground">{{ t('report.filters.item') }}</span>
          <select :value="filters.item_id" :class="baseSelectClass" @change="setFilter('item_id', $event.target.value)">
            <option value="">{{ t('report.filters.item') }}</option>
            <option v-for="item in options.items || []" :key="item.id" :value="item.id">
              {{ item.name }}
            </option>
          </select>
        </label>

        <label v-if="showAccount" class="space-y-2">
          <span class="text-sm font-medium text-foreground">{{ t('report.filters.account') }}</span>
          <select :value="filters.account_id" :class="baseSelectClass" @change="setFilter('account_id', $event.target.value)">
            <option value="">{{ t('report.filters.account') }}</option>
            <option v-for="account in options.cash_accounts || []" :key="account.id" :value="account.id">
              {{ account.name }}
            </option>
          </select>
        </label>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <Button @click="$emit('submit')">{{ t('report.filters.apply') }}</Button>
        <Button variant="outline" @click="$emit('reset')">{{ t('report.filters.reset') }}</Button>
      </div>
    </div>
  </div>
</template>
