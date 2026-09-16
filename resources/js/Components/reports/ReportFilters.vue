<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Button } from '@/Components/ui/button'
import NextSelect from '@/Components/next/NextSelect.vue'
import NextDate from '@/Components/next/NextDatePicker.vue'
import { useAuth } from '@/composables/useAuth'
const props = defineProps({
  filters: { type: Object, required: true },
  options: { type: Object, required: true },
  activeDefinition: { type: Object, required: true },
  reportList: { type: Array, required: true },
  showReportSelect: { type: Boolean, default: true },
})

const emit = defineEmits(['update:filters', 'submit', 'reset'])
const { t } = useI18n()
const { can, isSuperAdmin } = useAuth()

const showLedger = computed(() => props.activeDefinition.filters.includes('ledger_id'))
const showCustomer = computed(() => props.activeDefinition.filters.includes('customer_id'))
const showSupplier = computed(() => props.activeDefinition.filters.includes('supplier_id'))
const showItem = computed(() => props.activeDefinition.filters.includes('item_id'))
const showVariant = computed(() => props.activeDefinition.filters.includes('variant_id'))
const showAccount = computed(() => props.activeDefinition.filters.includes('account_id'))
const showWarehouse = computed(() => props.activeDefinition.filters.includes('warehouse_id'))
const showCurrency = computed(() => props.activeDefinition.filters.includes('currency_id'))
const showType = computed(() => props.activeDefinition.filters.includes('type'))
const showReason = computed(() => props.activeDefinition.filters.includes('reason'))
const showBalanceType = computed(() => props.activeDefinition.filters.includes('balance_type'))
const showExpenseCategory = computed(() => props.activeDefinition.filters.includes('category_id'))
const showExpenseAccount = computed(() => props.activeDefinition.filters.includes('expense_account_id'))
const showEmployee = computed(() => props.activeDefinition.filters.includes('employee_id'))
const showDepartment = computed(() => props.activeDefinition.filters.includes('department_id'))
const showDesignation = computed(() => props.activeDefinition.filters.includes('designation_id'))
const showPayroll = computed(() => props.activeDefinition.filters.includes('payroll_id'))
const showLeaveType = computed(() => props.activeDefinition.filters.includes('leave_type_id'))
const showEmploymentType = computed(() => props.activeDefinition.filters.includes('employment_type'))
const showEmploymentStatus = computed(() => props.activeDefinition.filters.includes('employment_status'))

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

// Only the chosen item's variants. Every variant in the branch is shipped with
// the page and narrowed here, so picking an item costs no round trip. With no
// item chosen there is nothing to narrow by, so the picker stays empty.
const variantOptions = computed(() => {
  const itemId = props.filters.item_id
  if (!itemId) return withPlaceholder([], t('report.filters.variant'))

  return withPlaceholder(
    (props.options.variants || []).filter((variant) => variant.item_id === itemId),
    t('report.filters.variant'),
  )
})
const accountOptions = computed(() => withPlaceholder(props.options.cash_accounts, t('report.filters.account')))
const warehouseOptions = computed(() => withPlaceholder(props.options.warehouses, t('report.filters.warehouse')))
const currencyOptions = computed(() => withPlaceholder(props.options.currencies, t('report.filters.currency')))
const typeOptions = computed(() => withPlaceholder(props.options.sale_types, t('report.filters.type')))
const reasonOptions = computed(() => withPlaceholder(props.options.stock_adjustment_reasons, t('report.filters.reason')))
const balanceTypeOptions = computed(() => props.options.balance_types || [
  { id: 'all', name: t('report.balance_types.all') },
  { id: 'debtor', name: t('report.balance_types.debtor') },
  { id: 'creditor', name: t('report.balance_types.creditor') },
])
const expenseCategoryOptions = computed(() => withPlaceholder(props.options.expense_categories, t('report.filters.expense_category')))
const expenseAccountOptions = computed(() => withPlaceholder(props.options.expense_accounts, t('report.filters.expense_account')))
const employeeOptions = computed(() => withPlaceholder(props.options.employees, t('report.filters.employee')))
const departmentOptions = computed(() => withPlaceholder(props.options.departments, t('report.filters.department')))
const designationOptions = computed(() => withPlaceholder(props.options.designations, t('report.filters.designation')))
const payrollOptions = computed(() => withPlaceholder(props.options.payrolls, t('report.filters.payroll')))
const leaveTypeOptions = computed(() => withPlaceholder(props.options.leave_types, t('report.filters.leave_type')))
const employmentTypeOptions = computed(() => withPlaceholder(props.options.employment_types, t('report.filters.employment_type')))
const employmentStatusOptions = computed(() => withPlaceholder(props.options.employment_statuses, t('report.filters.employment_status')))

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

/**
 * Changing the item drops any variant chosen under the previous one — that
 * variant belongs to a different item, so leaving it would filter the report
 * down to nothing with no visible reason why.
 */
function setItemFilter(itemId) {
  updateFilters({
    ...props.filters,
    item_id: itemId,
    variant_id: '',
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
    variant_id: '',
    account_id: '',
    currency_id: '',
    balance_type: 'all',
    category_id: '',
    expense_account_id: '',
    warehouse_id: '',
    reason: '',
    employee_id: '',
    department_id: '',
    designation_id: '',
    payroll_id: '',
    leave_type_id: '',
    employment_type: '',
    employment_status: '',
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
        <div v-if="isSuperAdmin">
          <NextSelect
            :floating-text="t('report.filters.branch')"
            :model-value="filters.branch_id"
            :options="branchOptions"
            :clearable="false"
            label-key="name"
            value-key="id"
            @update:modelValue="setFilter('branch_id', $event)"
          />
        </div>
        <div>
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
        </div>
        <div>
          <NextDate v-model="filters.date_from" :label="t('general.date')" :placeholder="t('general.enter', { text: t('report.filters.date_from') })" />
        </div>
        <div>
          <NextDate v-model="filters.date_to" :label="t('general.date')" :placeholder="t('general.enter', { text: t('report.filters.date_to') })" />
        </div>
        <div v-if="showLedger">
          <NextSelect
            :floating-text="t('report.filters.ledger')"
            :model-value="filters.ledger_id"
            :options="ledgerOptions"
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
            @update:modelValue="setFilter('supplier_id', $event)"
          />
        </div>
        <div v-if="showItem">
          <NextSelect
            :floating-text="t('report.filters.item')"
            :model-value="filters.item_id"
            :options="itemOptions"
            @update:modelValue="setItemFilter($event)"
          />
        </div>
        <div v-if="showVariant">
          <NextSelect
            :floating-text="t('report.filters.variant')"
            :model-value="filters.variant_id"
            :options="variantOptions"
            :disabled="!filters.item_id"
            @update:modelValue="setFilter('variant_id', $event)"
          />
        </div>
        <div v-if="showAccount">
          <NextSelect
            :floating-text="t('report.filters.account')"
            :model-value="filters.account_id"
            :options="accountOptions"
            label-key="name"
            value-key="id"
            @update:modelValue="setFilter('account_id', $event)"
          />
        </div>
        <div v-if="showType">
          <NextSelect
            :floating-text="t('report.filters.type')"
            :model-value="filters.type"
            :options="typeOptions"
            label-key="name"
            value-key="id"
            @update:modelValue="setFilter('type', $event)"
          />
        </div>
        <div v-if="showReason">
          <NextSelect
            :floating-text="t('report.filters.reason')"
            :model-value="filters.reason"
            :options="reasonOptions"
            label-key="name"
            value-key="id"
            @update:modelValue="setFilter('reason', $event)"
          />
        </div>
        <div v-if="showBalanceType">
          <NextSelect
            :floating-text="t('report.filters.balance_type')"
            :model-value="filters.balance_type || 'all'"
            :options="balanceTypeOptions"
            label-key="name"
            value-key="id"
            :clearable="false"
            @update:modelValue="setFilter('balance_type', $event || 'all')"
          />
        </div>
        <div v-if="showCurrency">
          <NextSelect
            :floating-text="t('report.filters.currency')"
            :model-value="filters.currency_id"
            :options="currencyOptions"
            label-key="name"
            value-key="id"
            @update:modelValue="setFilter('currency_id', $event)"
          />
        </div>
        <div v-if="showWarehouse">
          <NextSelect
            :floating-text="t('report.filters.warehouse')"
            :model-value="filters.warehouse_id"
            :options="warehouseOptions"
            label-key="name"
            value-key="id"
            @update:modelValue="setFilter('warehouse_id', $event)"
          />
        </div>
        <div v-if="showExpenseCategory">
          <NextSelect
            :floating-text="t('report.filters.expense_category')"
            :model-value="filters.category_id"
            :options="expenseCategoryOptions"
            label-key="name"
            value-key="id"
            @update:modelValue="setFilter('category_id', $event)"
          />
        </div>
        <div v-if="showExpenseAccount">
          <NextSelect
            :floating-text="t('report.filters.expense_account')"
            :model-value="filters.expense_account_id"
            :options="expenseAccountOptions"
            label-key="name"
            value-key="id"
            @update:modelValue="setFilter('expense_account_id', $event)"
          />
        </div>
        <div v-if="showEmployee">
          <NextSelect
            :floating-text="t('report.filters.employee')"
            :model-value="filters.employee_id"
            :options="employeeOptions"
            label-key="name"
            value-key="id"
            @update:modelValue="setFilter('employee_id', $event)"
          />
        </div>
        <div v-if="showDepartment">
          <NextSelect
            :floating-text="t('report.filters.department')"
            :model-value="filters.department_id"
            :options="departmentOptions"
            label-key="name"
            value-key="id"
            @update:modelValue="setFilter('department_id', $event)"
          />
        </div>
        <div v-if="showDesignation">
          <NextSelect
            :floating-text="t('report.filters.designation')"
            :model-value="filters.designation_id"
            :options="designationOptions"
            label-key="name"
            value-key="id"
            @update:modelValue="setFilter('designation_id', $event)"
          />
        </div>
        <div v-if="showPayroll">
          <NextSelect
            :floating-text="t('report.filters.payroll')"
            :model-value="filters.payroll_id"
            :options="payrollOptions"
            label-key="name"
            value-key="id"
            @update:modelValue="setFilter('payroll_id', $event)"
          />
        </div>
        <div v-if="showLeaveType">
          <NextSelect
            :floating-text="t('report.filters.leave_type')"
            :model-value="filters.leave_type_id"
            :options="leaveTypeOptions"
            label-key="name"
            value-key="id"
            @update:modelValue="setFilter('leave_type_id', $event)"
          />
        </div>
        <div v-if="showEmploymentType">
          <NextSelect
            :floating-text="t('report.filters.employment_type')"
            :model-value="filters.employment_type"
            :options="employmentTypeOptions"
            label-key="name"
            value-key="id"
            @update:modelValue="setFilter('employment_type', $event)"
          />
        </div>
        <div v-if="showEmploymentStatus">
          <NextSelect
            :floating-text="t('report.filters.employment_status')"
            :model-value="filters.employment_status"
            :options="employmentStatusOptions"
            label-key="name"
            value-key="id"
            @update:modelValue="setFilter('employment_status', $event)"
          />
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <Button variant="outline" class="h-9 border-primary text-primary hover:bg-primary hover:text-white" @click="$emit('submit')">{{ t('report.filters.apply') }}</Button>
        <Button variant="outline" class="h-9 border-primary text-primary hover:bg-primary hover:text-white" @click="$emit('reset')">{{ t('report.filters.reset') }}</Button>
      </div>
    </div>
  </div>
</template>
