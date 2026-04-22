<script setup>
import { computed, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import {
  BookOpenText,
  FileSpreadsheet,
  Landmark,
  ArrowLeftRight,
  Receipt,
  Wallet,
  BadgeDollarSign,
  ShoppingCart,
  Package,
  Boxes,
  Archive,
  TrendingUp,
  TrendingDown,
  Users,
  Truck,
  ClipboardList,
  Download,
  CalendarClock,
  PackageX,
  FileText,
} from 'lucide-vue-next'
import AppLayout from '@/Layouts/Layout.vue'
import { Button } from '@/Components/ui/button'
import { Spinner } from '@/Components/ui/spinner'
import { Skeleton } from '@/Components/ui/skeleton'
import ReportCatalog from '@/Components/reports/ReportCatalog.vue'
import ReportFilters from '@/Components/reports/ReportFilters.vue'
import ReportSummaryCards from '@/Components/reports/ReportSummaryCards.vue'
import ReportDataTable from '@/Components/reports/ReportDataTable.vue'
import ReportStatement from '@/Components/reports/ReportStatement.vue'
import ReportGroupedTable from '@/Components/reports/ReportGroupedTable.vue'
import ReportUserActivity from '@/Components/reports/ReportUserActivity.vue'

const props = defineProps({
  filters: { type: Object, required: true },
  reportOptions: { type: Array, default: () => [] },
  filterOptions: { type: Object, required: true },
  result: { type: Object, required: true },
  reportSelected: { type: Boolean, default: false },
})

const { t } = useI18n()
const localFilters = ref(normalizeFilters(props.filters))
const isLoadingReport = ref(false)

watch(
  () => props.filters,
  (value) => {
    localFilters.value = normalizeFilters(value)
  },
  { deep: true },
)

function normalizeFilters(filters) {
  return {
    report: filters.report || 'trial_balance',
    branch_id: filters.branch_id || '',
    date_from: filters.date_from || '',
    date_to: filters.date_to || '',
    ledger_id: filters.ledger_id || '',
    customer_id: filters.customer_id || '',
    supplier_id: filters.supplier_id || '',
    item_id: filters.item_id || '',
    account_id: filters.account_id || '',
    per_page: Number(filters.per_page || 25),
    page: Number(filters.page || 1),
  }
}

const reportDefinitions = computed(() => ({
  trial_balance: {
    label: t('report.reports.trial_balance.label'),
    description: t('report.reports.trial_balance.description'),
    filters: [],
    group: 'financial',
    icon: FileSpreadsheet,
    summary: [
      { key: 'total_debit', label: t('report.summary.total_debit'), type: 'money' },
      { key: 'total_credit', label: t('report.summary.total_credit'), type: 'money' },
      { key: 'balance_label', label: t('report.summary.balance'), type: 'text' },
    ],
    columns: [
      { key: 'ledger_name', label: t('report.columns.ledger_name') },
      { key: 'total_debit', label: t('report.columns.total_debit'), type: 'money', align: 'right' },
      { key: 'total_credit', label: t('report.columns.total_credit'), type: 'money', align: 'right' },
      { key: 'balance', label: t('report.columns.balance'), type: 'money', align: 'right' },
    ],
  },
  balance_sheet: {
    label: t('report.reports.balance_sheet.label'),
    description: t('report.reports.balance_sheet.description'),
    filters: [],
    group: 'financial',
    icon: Landmark,
    summary: [
      { key: 'total_assets', label: t('report.summary.total_assets'), type: 'money' },
      { key: 'total_liabilities', label: t('report.summary.total_liabilities'), type: 'money' },
      { key: 'total_equity', label: t('report.summary.total_equity'), type: 'money' },
      { key: 'equation_total', label: t('report.summary.equation_total'), type: 'money' },
    ],
  },
  income_statement: {
    label: t('report.reports.income_statement.label'),
    description: t('report.reports.income_statement.description'),
    filters: [],
    group: 'financial',
    icon: TrendingUp,
    summary: [
      { key: 'total_revenue', label: t('report.summary.total_revenue'), type: 'money' },
      { key: 'total_cost_of_goods_sold', label: t('report.summary.total_cost_of_goods_sold'), type: 'money' },
      { key: 'gross_profit', label: t('report.summary.gross_profit'), type: 'money' },
      { key: 'total_expenses', label: t('report.summary.total_expenses'), type: 'money' },
      { key: 'net_profit', label: t('report.summary.net_profit'), type: 'money' },
    ],
  },
  general_ledger: {
    label: t('report.reports.general_ledger.label'),
    description: t('report.reports.general_ledger.description'),
    filters: ['ledger_id'],
    group: 'financial',
    icon: BookOpenText,
    summary: [
      { key: 'total_debit', label: t('report.summary.total_debit'), type: 'money' },
      { key: 'total_credit', label: t('report.summary.total_credit'), type: 'money' },
      { key: 'balance_label', label: t('report.summary.balance'), type: 'text' },
    ],
    columns: [
      { key: 'date', label: t('report.columns.date') },
      { key: 'transaction_number', label: t('report.columns.transaction_number') },
      { key: 'reference_type', label: t('report.columns.reference_type') },
      { key: 'description', label: t('report.columns.description') },
      { key: 'debit', label: t('report.columns.debit'), type: 'money', align: 'right' },
      { key: 'credit', label: t('report.columns.credit'), type: 'money', align: 'right' },
      { key: 'running_balance_label', label: t('report.columns.running_balance'), align: 'right' },
    ],
  },
  cash_book: {
    label: t('report.reports.cash_book.label'),
    description: t('report.reports.cash_book.description'),
    filters: ['account_id'],
    group: 'financial',
    icon: Wallet,
    summary: [
      { key: 'total_debit', label: t('report.summary.total_debit'), type: 'money' },
      { key: 'total_credit', label: t('report.summary.total_credit'), type: 'money' },
      { key: 'balance_label', label: t('report.summary.balance'), type: 'text' },
    ],
    columns: [
      { key: 'date', label: t('report.columns.date') },
      { key: 'reference', label: t('report.columns.reference') },
      { key: 'description', label: t('report.columns.description') },
      { key: 'debit', label: t('report.columns.debit'), type: 'money', align: 'right' },
      { key: 'credit', label: t('report.columns.credit'), type: 'money', align: 'right' },
      { key: 'running_balance_label', label: t('report.columns.running_balance'), align: 'right' },
    ],
  },
  receipt_report: {
    label: t('report.reports.receipt_report.label'),
    description: t('report.reports.receipt_report.description'),
    filters: ['account_id'],
    group: 'cash_flow',
    icon: Receipt,
    summary: [
      { key: 'total_amount', label: t('report.summary.total_amount'), type: 'money' },
    ],
    columns: [
      { key: 'date', label: t('report.columns.date') },
      { key: 'transaction_number', label: t('report.columns.transaction_number') },
      { key: 'ledger_name', label: t('report.columns.ledger_name') },
      { key: 'description', label: t('report.columns.description') },
      { key: 'amount_received', label: t('report.columns.amount_received'), type: 'money', align: 'right' },
    ],
  },
  payment_report: {
    label: t('report.reports.payment_report.label'),
    description: t('report.reports.payment_report.description'),
    filters: ['account_id'],
    group: 'cash_flow',
    icon: BadgeDollarSign,
    summary: [
      { key: 'total_amount', label: t('report.summary.total_amount'), type: 'money' },
    ],
    columns: [
      { key: 'date', label: t('report.columns.date') },
      { key: 'transaction_number', label: t('report.columns.transaction_number') },
      { key: 'ledger_name', label: t('report.columns.ledger_name') },
      { key: 'description', label: t('report.columns.description') },
      { key: 'amount_paid', label: t('report.columns.amount_paid'), type: 'money', align: 'right' },
    ],
  },
  customer_statement: {
    label: t('report.reports.customer_statement.label'),
    description: t('report.reports.customer_statement.description'),
    filters: ['customer_id'],
    group: 'party',
    icon: Users,
    summary: [
      { key: 'total_debit', label: t('report.summary.total_debit'), type: 'money' },
      { key: 'total_credit', label: t('report.summary.total_credit'), type: 'money' },
      { key: 'balance_label', label: t('report.summary.balance'), type: 'text' },
    ],
    columns: [
      { key: 'date', label: t('report.columns.date') },
      { key: 'reference', label: t('report.columns.reference') },
      { key: 'description', label: t('report.columns.description') },
      { key: 'debit', label: t('report.columns.debit'), type: 'money', align: 'right' },
      { key: 'credit', label: t('report.columns.credit'), type: 'money', align: 'right' },
      { key: 'running_balance', label: t('report.columns.running_balance'), type: 'money', align: 'right' },
      { key: 'balance', label: t('report.columns.balance'), align: 'right' },
    ],
  },
  supplier_statement: {
    label: t('report.reports.supplier_statement.label'),
    description: t('report.reports.supplier_statement.description'),
    filters: ['supplier_id'],
    group: 'party',
    icon: Truck,
    summary: [
      { key: 'total_debit', label: t('report.summary.total_debit'), type: 'money' },
      { key: 'total_credit', label: t('report.summary.total_credit'), type: 'money' },
      { key: 'balance_label', label: t('report.summary.balance'), type: 'text' },
    ],
    columns: [
      { key: 'date', label: t('report.columns.date') },
      { key: 'reference', label: t('report.columns.reference') },
      { key: 'description', label: t('report.columns.description') },
      { key: 'debit', label: t('report.columns.debit'), type: 'money', align: 'right' },
      { key: 'credit', label: t('report.columns.credit'), type: 'money', align: 'right' },
      { key: 'running_balance', label: t('report.columns.running_balance'), type: 'money', align: 'right' },
      { key: 'balance', label: t('report.columns.balance'), align: 'right' },
    ],
  },
  sales_report: {
    label: t('report.reports.sales_report.label'),
    description: t('report.reports.sales_report.description'),
    filters: ['item_id'],
    group: 'operations',
    icon: ShoppingCart,
    summary: [
      { key: 'total_quantity', label: t('report.summary.total_quantity'), type: 'quantity' },
      { key: 'total_amount', label: t('report.summary.total_amount'), type: 'money' },
    ],
    columns: [
      { key: 'date', label: t('report.columns.date') },
      { key: 'sale_number', label: t('report.columns.sale_number') },
      { key: 'customer', label: t('report.columns.customer') },
      { key: 'item', label: t('report.columns.item') },
      { key: 'quantity', label: t('report.columns.quantity'), type: 'quantity', align: 'right' },
      { key: 'unit_price', label: t('report.columns.unit_price'), type: 'money', align: 'right' },
      { key: 'total_amount', label: t('report.columns.total_amount'), type: 'money', align: 'right' },
    ],
  },
  purchase_report: {
    label: t('report.reports.purchase_report.label'),
    description: t('report.reports.purchase_report.description'),
    filters: ['item_id'],
    group: 'operations',
    icon: ClipboardList,
    summary: [
      { key: 'total_quantity', label: t('report.summary.total_quantity'), type: 'quantity' },
      { key: 'total_amount', label: t('report.summary.total_amount'), type: 'money' },
    ],
    columns: [
      { key: 'date', label: t('report.columns.date') },
      { key: 'purchase_number', label: t('report.columns.purchase_number') },
      { key: 'supplier', label: t('report.columns.supplier') },
      { key: 'item', label: t('report.columns.item') },
      { key: 'quantity', label: t('report.columns.quantity'), type: 'quantity', align: 'right' },
      { key: 'unit_price', label: t('report.columns.unit_price'), type: 'money', align: 'right' },
      { key: 'total_amount', label: t('report.columns.total_amount'), type: 'money', align: 'right' },
    ],
  },
  inventory_stock: {
    label: t('report.reports.inventory_stock.label'),
    description: t('report.reports.inventory_stock.description'),
    filters: ['item_id'],
    snapshot: true,
    group: 'inventory',
    icon: Package,
    summary: [
      { key: 'total_quantity', label: t('report.summary.total_quantity'), type: 'quantity' },
      { key: 'total_value', label: t('report.summary.total_value'), type: 'money' },
    ],
    columns: [
      { key: 'item', label: t('report.columns.item') },
      { key: 'warehouse', label: t('report.columns.warehouse') },
      { key: 'quantity', label: t('report.columns.quantity'), type: 'quantity', align: 'right' },
      { key: 'average_cost', label: t('report.columns.average_cost'), type: 'money', align: 'right' },
      { key: 'total_value', label: t('report.columns.total_value'), type: 'money', align: 'right' },
    ],
  },
  stock_movement: {
    label: t('report.reports.stock_movement.label'),
    description: t('report.reports.stock_movement.description'),
    filters: ['item_id'],
    group: 'inventory',
    icon: ArrowLeftRight,
    summary: [
      { key: 'total_quantity', label: t('report.summary.total_quantity'), type: 'quantity' },
    ],
    columns: [
      { key: 'date', label: t('report.columns.date') },
      { key: 'item', label: t('report.columns.item') },
      { key: 'warehouse', label: t('report.columns.warehouse') },
      { key: 'movement_type', label: t('report.columns.movement_type') },
      { key: 'quantity', label: t('report.columns.quantity'), type: 'quantity', align: 'right' },
      { key: 'unit_price', label: t('report.columns.unit_price'), type: 'money', align: 'right' },
      { key: 'source_type', label: t('report.columns.source_type') },
      { key: 'reference_type', label: t('report.columns.reference_type') },
      { key: 'reference_id', label: t('report.columns.reference_id') },
    ],
  },
  low_stock: {
    label: t('report.reports.low_stock.label'),
    description: t('report.reports.low_stock.description'),
    filters: ['item_id'],
    snapshot: true,
    group: 'inventory',
    icon: Boxes,
    summary: [
      { key: 'total_items', label: t('report.summary.total_items'), type: 'integer' },
    ],
    columns: [
      { key: 'item', label: t('report.columns.item') },
      { key: 'warehouse', label: t('report.columns.warehouse') },
      { key: 'quantity', label: t('report.columns.quantity'), type: 'quantity', align: 'right' },
      { key: 'reorder_level', label: t('report.columns.reorder_level'), type: 'quantity', align: 'right' },
    ],
  },
  inventory_valuation: {
    label: t('report.reports.inventory_valuation.label'),
    description: t('report.reports.inventory_valuation.description'),
    filters: ['item_id'],
    snapshot: true,
    group: 'inventory',
    icon: Archive,
    summary: [
      { key: 'total_quantity', label: t('report.summary.total_quantity'), type: 'quantity' },
      { key: 'total_value', label: t('report.summary.total_value'), type: 'money' },
    ],
    columns: [
      { key: 'item', label: t('report.columns.item') },
      { key: 'quantity', label: t('report.columns.quantity'), type: 'quantity', align: 'right' },
      { key: 'average_cost', label: t('report.columns.average_cost'), type: 'money', align: 'right' },
      { key: 'total_value', label: t('report.columns.total_value'), type: 'money', align: 'right' },
    ],
  },
  batch_wise_report: {
    label: t('report.reports.batch_wise_report.label'),
    description: t('report.reports.batch_wise_report.description'),
    filters: [],
    snapshot: true,
    group: 'inventory',
    icon: Boxes,
    summary: [
      { key: 'total_in', label: t('report.summary.total_in'), type: 'quantity' },
      { key: 'total_out', label: t('report.summary.total_out'), type: 'quantity' },
      { key: 'total_on_hand', label: t('report.summary.total_on_hand'), type: 'quantity' },
    ],
    columns: [
      { key: 'item_code', label: t('report.columns.item_code') },
      { key: 'item_name', label: t('report.columns.item_name') },
      { key: 'batch_number', label: t('report.columns.batch_number') },
      { key: 'expiry_date', label: t('report.columns.expiry_date') },
      { key: 'in_quantity', label: t('report.columns.in_quantity'), type: 'quantity', align: 'right' },
      { key: 'out_quantity', label: t('report.columns.out_quantity'), type: 'quantity', align: 'right' },
      { key: 'on_hand', label: t('report.columns.on_hand'), type: 'quantity', align: 'right' },
    ],
  },
  expiry_wise_report: {
    label: t('report.reports.expiry_wise_report.label'),
    description: t('report.reports.expiry_wise_report.description'),
    filters: [],
    snapshot: true,
    group: 'inventory',
    icon: CalendarClock,
    summary: [
      { key: 'total_in', label: t('report.summary.total_in'), type: 'quantity' },
      { key: 'total_out', label: t('report.summary.total_out'), type: 'quantity' },
      { key: 'total_on_hand', label: t('report.summary.total_on_hand'), type: 'quantity' },
    ],
    columns: [
      { key: 'item_code', label: t('report.columns.item_code') },
      { key: 'item_name', label: t('report.columns.item_name') },
      { key: 'expiry_date', label: t('report.columns.expiry_date') },
      { key: 'in_quantity', label: t('report.columns.in_quantity'), type: 'quantity', align: 'right' },
      { key: 'out_quantity', label: t('report.columns.out_quantity'), type: 'quantity', align: 'right' },
      { key: 'on_hand', label: t('report.columns.on_hand'), type: 'quantity', align: 'right' },
    ],
  },
  zero_on_hand_report: {
    label: t('report.reports.zero_on_hand_report.label'),
    description: t('report.reports.zero_on_hand_report.description'),
    filters: [],
    snapshot: true,
    group: 'inventory',
    icon: PackageX,
    summary: [
      { key: 'total_items', label: t('report.summary.total_items'), type: 'integer' },
      { key: 'total_in', label: t('report.summary.total_in'), type: 'quantity' },
      { key: 'total_out', label: t('report.summary.total_out'), type: 'quantity' },
    ],
    columns: [
      { key: 'item_code', label: t('report.columns.item_code') },
      { key: 'item_name', label: t('report.columns.item_name') },
      { key: 'total_in', label: t('report.columns.total_in'), type: 'quantity', align: 'right' },
      { key: 'total_out', label: t('report.columns.total_out'), type: 'quantity', align: 'right' },
      { key: 'on_hand', label: t('report.columns.on_hand'), type: 'quantity', align: 'right' },
    ],
  },
  fast_moving_report: {
    label: t('report.reports.fast_moving_report.label'),
    description: t('report.reports.fast_moving_report.description'),
    filters: [],
    group: 'inventory',
    icon: TrendingUp,
    summary: [
      { key: 'total_sold', label: t('report.summary.total_sold'), type: 'quantity' },
      { key: 'sale_count', label: t('report.summary.sale_count'), type: 'integer' },
      { key: 'average_per_day', label: t('report.summary.average_per_day'), type: 'quantity' },
    ],
    columns: [
      { key: 'item_code', label: t('report.columns.item_code') },
      { key: 'item_name', label: t('report.columns.item_name') },
      { key: 'total_sold', label: t('report.columns.total_sold'), type: 'quantity', align: 'right' },
      { key: 'sale_count', label: t('report.columns.sale_count'), type: 'integer', align: 'right' },
      { key: 'average_per_day', label: t('report.columns.average_per_day'), type: 'quantity', align: 'right' },
    ],
  },
  slow_moving_report: {
    label: t('report.reports.slow_moving_report.label'),
    description: t('report.reports.slow_moving_report.description'),
    filters: [],
    group: 'inventory',
    icon: TrendingDown,
    summary: [
      { key: 'total_sold', label: t('report.summary.total_sold'), type: 'quantity' },
      { key: 'sale_count', label: t('report.summary.sale_count'), type: 'integer' },
      { key: 'days_on_hand', label: t('report.summary.days_on_hand'), type: 'integer' },
    ],
    columns: [
      { key: 'item_code', label: t('report.columns.item_code') },
      { key: 'item_name', label: t('report.columns.item_name') },
      { key: 'total_sold', label: t('report.columns.total_sold'), type: 'quantity', align: 'right' },
      { key: 'sale_count', label: t('report.columns.sale_count'), type: 'integer', align: 'right' },
      { key: 'days_on_hand', label: t('report.columns.days_on_hand'), type: 'integer', align: 'right' },
      { key: 'turnover_rate', label: t('report.columns.turnover_rate'), type: 'quantity', align: 'right' },
    ],
  },
  today_sale_purchase_closing_stock_report: {
    label: t('report.reports.today_sale_purchase_closing_stock_report.label'),
    description: t('report.reports.today_sale_purchase_closing_stock_report.description'),
    filters: [],
    snapshot: true,
    group: 'inventory',
    icon: ClipboardList,
    summary: [
      { key: 'opening_balance', label: t('report.summary.opening_balance'), type: 'quantity' },
      { key: 'purchase_today', label: t('report.summary.purchase_today'), type: 'quantity' },
      { key: 'sale_today', label: t('report.summary.sale_today'), type: 'quantity' },
      { key: 'closing_balance', label: t('report.summary.closing_balance'), type: 'quantity' },
    ],
    columns: [
      { key: 'item_code', label: t('report.columns.item_code') },
      { key: 'item_name', label: t('report.columns.item_name') },
      { key: 'opening_balance', label: t('report.columns.opening_balance'), type: 'quantity', align: 'right' },
      { key: 'purchase_today', label: t('report.columns.purchase_today'), type: 'quantity', align: 'right' },
      { key: 'sale_today', label: t('report.columns.sale_today'), type: 'quantity', align: 'right' },
      { key: 'closing_balance', label: t('report.columns.closing_balance'), type: 'quantity', align: 'right' },
    ],
  },
  near_expiry_report: {
    label: t('report.reports.near_expiry_report.label'),
    description: t('report.reports.near_expiry_report.description'),
    filters: [],
    snapshot: true,
    group: 'inventory',
    icon: CalendarClock,
    summary: [
      { key: 'total_items', label: t('report.summary.total_items'), type: 'integer' },
      { key: 'total_on_hand', label: t('report.summary.total_on_hand'), type: 'quantity' },
    ],
    columns: [
      { key: 'item_code', label: t('report.columns.item_code') },
      { key: 'item_name', label: t('report.columns.item_name') },
      { key: 'batch_number', label: t('report.columns.batch_number') },
      { key: 'expiry_date', label: t('report.columns.expiry_date') },
      { key: 'on_hand', label: t('report.columns.on_hand'), type: 'quantity', align: 'right' },
      { key: 'days_until_expiry', label: t('report.columns.days_until_expiry'), type: 'integer', align: 'right' },
    ],
  },
  maximum_stock_report: {
    label: t('report.reports.maximum_stock_report.label'),
    description: t('report.reports.maximum_stock_report.description'),
    filters: [],
    snapshot: true,
    group: 'inventory',
    icon: Archive,
    summary: [
      { key: 'total_items', label: t('report.summary.total_items'), type: 'integer' },
      { key: 'total_excess_quantity', label: t('report.summary.total_excess_quantity'), type: 'quantity' },
    ],
    columns: [
      { key: 'item_code', label: t('report.columns.item_code') },
      { key: 'item_name', label: t('report.columns.item_name') },
      { key: 'max_stock_level', label: t('report.columns.max_stock_level'), type: 'quantity', align: 'right' },
      { key: 'on_hand', label: t('report.columns.on_hand'), type: 'quantity', align: 'right' },
      { key: 'excess_quantity', label: t('report.columns.excess_quantity'), type: 'quantity', align: 'right' },
    ],
  },
  group_summary_report: {
    label: t('report.reports.group_summary_report.label'),
    description: t('report.reports.group_summary_report.description'),
    filters: [],
    group: 'financial',
    icon: Landmark,
    summary: [
      { key: 'opening_balance', label: t('report.summary.opening_balance'), type: 'money' },
      { key: 'debit', label: t('report.summary.total_debit'), type: 'money' },
      { key: 'credit', label: t('report.summary.total_credit'), type: 'money' },
      { key: 'closing_balance', label: t('report.summary.closing_balance'), type: 'money' },
    ],
    layout: 'group_summary',
    columns: [
      { key: 'account_name', label: t('report.columns.account_name') },
      { key: 'opening_balance', label: t('report.columns.opening_balance'), type: 'balance', align: 'right' },
      { key: 'debit', label: t('report.columns.debit'), type: 'money', align: 'right' },
      { key: 'credit', label: t('report.columns.credit'), type: 'money', align: 'right' },
      { key: 'closing_balance', label: t('report.columns.closing_balance'), type: 'balance', align: 'right' },
    ],
  },
  day_book_report: {
    label: t('report.reports.day_book_report.label'),
    description: t('report.reports.day_book_report.description'),
    filters: [],
    group: 'financial',
    icon: FileText,
    summary: [
      { key: 'total_debit', label: t('report.summary.total_debit'), type: 'money' },
      { key: 'total_credit', label: t('report.summary.total_credit'), type: 'money' },
      { key: 'balance', label: t('report.summary.balance'), type: 'money' },
    ],
    columns: [
      { key: 'time', label: t('report.columns.time') },
      { key: 'account_name', label: t('report.columns.account_name') },
      { key: 'transaction_type', label: t('report.columns.transaction_type') },
      { key: 'reference', label: t('report.columns.reference') },
      { key: 'debit', label: t('report.columns.debit'), type: 'money', align: 'right' },
      { key: 'credit', label: t('report.columns.credit'), type: 'money', align: 'right' },
      { key: 'narration', label: t('report.columns.narration') },
    ],
  },
  journal_book_report: {
    label: t('report.reports.journal_book_report.label'),
    description: t('report.reports.journal_book_report.description'),
    filters: [],
    group: 'financial',
    icon: BookOpenText,
    summary: [
      { key: 'total_debit', label: t('report.summary.total_debit'), type: 'money' },
      { key: 'total_credit', label: t('report.summary.total_credit'), type: 'money' },
      { key: 'balance', label: t('report.summary.balance'), type: 'money' },
    ],
    columns: [
      { key: 'account_type', label: t('report.columns.account_type') },
      { key: 'total_debit', label: t('report.columns.total_debit'), type: 'money', align: 'right' },
      { key: 'total_credit', label: t('report.columns.total_credit'), type: 'money', align: 'right' },
      { key: 'balance', label: t('report.columns.balance'), type: 'balance', align: 'right' },
    ],
  },
  user_activity: {
    label: t('report.reports.user_activity.label'),
    description: t('report.reports.user_activity.description'),
    filters: [],
    group: 'management',
    icon: Users,
    summary: [],
  },
}))

const catalogBlueprint = computed(() => ([
  { key: 'financial', label: t('report.groups.financial'), description: t('report.groups.financial_description') },
  { key: 'cash_flow', label: t('report.groups.cash_flow'), description: t('report.groups.cash_flow_description') },
  { key: 'party', label: t('report.groups.party'), description: t('report.groups.party_description') },
  { key: 'operations', label: t('report.groups.operations'), description: t('report.groups.operations_description') },
  { key: 'inventory', label: t('report.groups.inventory'), description: t('report.groups.inventory_description') },
  { key: 'management', label: t('report.groups.management'), description: t('report.groups.management_description') },
]))

const activeDefinition = computed(() => reportDefinitions.value[localFilters.value.report] || reportDefinitions.value.trial_balance)
const reportList = computed(() => props.reportOptions.map((option) => ({ key: option.key, ...reportDefinitions.value[option.key] })))
const catalogSections = computed(() => catalogBlueprint.value.map((section) => ({
  ...section,
  reports: reportList.value.filter((report) => report.group === section.key),
})).filter((section) => section.reports.length))
const isDetailView = computed(() => props.reportSelected)
const heroBadgeLabel = computed(() => (isDetailView.value ? activeDefinition.value.label : t('report.catalog_label')))
const isGroupedLayout = computed(() => props.result.meta?.layout === 'group_summary')

const summaryCards = computed(() => (activeDefinition.value.summary || [])
  .filter((card) => props.result.summary?.[card.key] !== undefined)
  .map((card) => ({
    ...card,
    value: props.result.summary[card.key],
  })))

const emptyMessage = computed(() => {
  if (props.result.meta?.requires_filter && props.result.meta?.message_key) {
    return t(`report.messages.${props.result.meta.message_key}`)
  }

  return t('report.table.no_data')
})

const isStatementLayout = computed(() => props.result.meta?.layout === 'statement')
const isUserActivityLayout = computed(() => props.result.meta?.layout === 'user_activity')

function compactFilters(filters) {
  return Object.fromEntries(
    Object.entries(filters).filter(([, value]) => value !== '' && value !== null && value !== undefined),
  )
}

function visitReports(filters) {
  isLoadingReport.value = true

  router.get('/reports', compactFilters(filters), {
    preserveState: true,
    preserveScroll: true,
    replace: true,
    onFinish: () => {
      isLoadingReport.value = false
    },
    onError: () => {
      isLoadingReport.value = false
    },
    onCancel: () => {
      isLoadingReport.value = false
    },
  })
}

function submit(page = 1) {
  visitReports({ ...localFilters.value, page })
}

function resetFilters() {
  localFilters.value = {
    report: localFilters.value.report,
    branch_id: props.filters.branch_id || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
    ledger_id: '',
    customer_id: '',
    supplier_id: '',
    item_id: '',
    account_id: '',
    per_page: Number(props.filters.per_page || 25),
    page: 1,
  }

  submit(1)
}

function updateFilters(nextFilters) {
  localFilters.value = nextFilters
}

function selectReport(reportKey) {
  localFilters.value = {
    ...localFilters.value,
    report: reportKey,
    date_from: '',
    date_to: '',
    ledger_id: '',
    customer_id: '',
    supplier_id: '',
    item_id: '',
    account_id: '',
    page: 1,
  }

  visitReports({ ...localFilters.value, report: reportKey, page: 1 })
}

function goBackToCatalog() {
  visitReports({
    branch_id: localFilters.value.branch_id,
    date_from: localFilters.value.date_from,
    date_to: localFilters.value.date_to,
    per_page: localFilters.value.per_page,
  })
}

function exportReport() {
  const params = new URLSearchParams(compactFilters(localFilters.value))
  window.location.href = `/reports/export?${params.toString()}`
}
</script>

<template>
  <AppLayout>
    <Head :title="t('report.title')" />

    <div class="space-y-6 text-foreground">
      <section class="overflow-hidden rounded-[30px] border border-emerald-800/30 bg-gradient-to-br from-violet-500 via-violet-900 to-violet-950 p-6 text-white shadow-[0_20px_50px_rgba(6,78,59,0.35)]">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div class="space-y-2">
            <h1 class="text-3xl font-semibold tracking-tight">{{ t('report.title') }}</h1>
            <p class="max-w-3xl text-sm leading-7 text-emerald-100/85">{{ t('report.subtitle') }}</p>
          </div>
          <div class="rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-sm text-emerald-50/85 backdrop-blur">
            {{ heroBadgeLabel }}
          </div>
        </div>
      </section>

      <ReportCatalog
        v-if="!isDetailView && !isLoadingReport"
        :sections="catalogSections"
        :active-report="localFilters.report"
        @select="selectReport"
      />

      <section
        v-else-if="!isDetailView && isLoadingReport"
        class="space-y-5 rounded-[28px] border border-border bg-gradient-to-b from-card via-card to-background p-5 shadow-sm"
        aria-busy="true"
      >
        <div class="flex items-center gap-3 text-sm text-muted-foreground">
          <Spinner class="h-5 w-5" />
          {{ t('general.loading') }}...
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <div v-for="index in 6" :key="index" class="rounded-2xl border border-border bg-card p-5 shadow-sm">
            <Skeleton class="h-12 w-12 rounded-2xl" />
            <Skeleton class="mt-4 h-5 w-3/4" />
            <Skeleton class="mt-3 h-4 w-full" />
            <Skeleton class="mt-2 h-4 w-5/6" />
          </div>
        </div>
      </section>

      <section v-if="isDetailView" class="space-y-5 rounded-[28px] border border-border bg-gradient-to-b from-card via-card to-background p-5 shadow-sm" :aria-busy="isLoadingReport">
        <template v-if="isLoadingReport">
          <div class="flex items-center gap-3 text-sm text-muted-foreground">
            <Spinner class="h-5 w-5" />
            {{ t('general.loading') }}...
          </div>

          <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:grid-rows-[auto_auto] lg:items-start">
            <div class="space-y-2">
              <Skeleton class="h-3 w-32" />
              <Skeleton class="h-8 w-72" />
              <Skeleton class="h-4 w-full max-w-3xl" />
            </div>
            <Skeleton class="h-10 w-32 lg:justify-self-end" />
            <Skeleton class="h-8 w-80 max-w-full" />
            <Skeleton class="h-10 w-36 lg:self-end lg:justify-self-end" />
          </div>

          <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div v-for="index in 4" :key="`summary-${index}`" class="rounded-2xl border border-border bg-card p-4 shadow-sm">
              <Skeleton class="h-4 w-24" />
              <Skeleton class="mt-3 h-7 w-3/4" />
            </div>
          </div>

          <div class="space-y-3">
            <div>
              <Skeleton class="h-6 w-40" />
              <Skeleton class="mt-2 h-4 w-72" />
            </div>

            <div class="rounded-2xl border border-border bg-card p-4 shadow-sm">
              <div class="space-y-3">
                <Skeleton class="h-10 w-full" />
                <Skeleton class="h-10 w-full" />
                <Skeleton class="h-10 w-full" />
                <Skeleton class="h-10 w-full" />
                <Skeleton class="h-10 w-full" />
              </div>
            </div>
          </div>
        </template>

        <template v-else>
          <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:grid-rows-[auto_auto] lg:items-start">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-primary lg:self-center">{{ t('report.active_report') }}</p>
            <Button variant="outline" class="w-fit shrink-0 lg:justify-self-end" @click="goBackToCatalog">
              {{ t('report.back_to_reports') }}
            </Button>
            <div class="space-y-3">
              <h2 class="text-2xl font-semibold tracking-tight text-foreground">{{ activeDefinition.label }}</h2>
              <p class="max-w-3xl text-sm leading-7 text-muted-foreground">{{ activeDefinition.description }}</p>
            </div>
            <Button class="gap-2 lg:self-end lg:justify-self-end" @click="exportReport">
              <Download class="h-4 w-4" />
              {{ t('report.export_excel') }}
            </Button>
          </div>

          <ReportFilters
            :filters="localFilters"
            :options="filterOptions"
            :active-definition="activeDefinition"
            :report-list="reportList"
            :show-report-select="false"
            @update:filters="updateFilters"
            @submit="submit(1)"
            @reset="resetFilters"
          />

          <ReportSummaryCards v-if="!isUserActivityLayout" :cards="summaryCards" />

          <section class="space-y-3">
            <div>
              <h3 class="text-xl font-semibold tracking-tight text-foreground">{{ t('report.table.results') }}</h3>
              <p class="text-sm text-muted-foreground">{{ activeDefinition.description }}</p>
            </div>

            <ReportUserActivity
              v-if="isUserActivityLayout"
              :summary="result.summary || {}"
              :rows="result.rows || []"
              :pagination="result.pagination"
              :meta="result.meta || {}"
              :empty-message="emptyMessage"
              @page-change="submit"
            />

            <ReportStatement
              v-else-if="isStatementLayout"
              :sections="result.meta?.sections || []"
              :empty-message="emptyMessage"
            />

            <ReportGroupedTable
              v-else-if="isGroupedLayout"
              :sections="result.meta?.sections || []"
              :columns="activeDefinition.columns"
              :empty-message="emptyMessage"
            />

            <ReportDataTable
              v-else
              :columns="activeDefinition.columns"
              :rows="result.rows || []"
              :pagination="result.pagination"
              :empty-message="emptyMessage"
              @page-change="submit"
            />
          </section>
        </template>
      </section>
    </div>
  </AppLayout>
</template>
