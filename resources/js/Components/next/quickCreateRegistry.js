// Central registry for quick-create modal fields per resource type.
// Resource types should match what `NextSelect` receives via `resource-type`.

export const QUICK_CREATE_EVENT = 'nextbook:quick-created'

const option = (key, label) => ({ key, label })

/**
 * @typedef {Object} Field
 * @property {string} key
 * @property {string} label
 * @property {'text'|'number'|'textarea'|'select'|'checkbox'} type
 * @property {boolean=} required
 * @property {Array<{id:any,name:string}>|(()=>Array<{id:any,name:string}>)=} options
 * @property {string=} placeholder
 */

/**
 * @type {Record<string, { title: string, endpointType: string, fields: Field[], defaults?: (ctx: any) => any }>}
 */
export const quickCreateRegistry = {
  currencies: {
    title: 'Currency',
    endpointType: 'currencies',
    fields: [
      { key: 'name', label: 'Name', type: 'text', required: true },
      { key: 'code', label: 'Code', type: 'text', required: true },
      { key: 'symbol', label: 'Symbol', type: 'text', required: true },
      { key: 'format', label: 'Format', type: 'text', required: true },
      { key: 'exchange_rate', label: 'Exchange Rate', type: 'number', required: true },
      { key: 'flag', label: 'Flag', type: 'text' },
      { key: 'is_active', label: 'Active', type: 'checkbox' },
      { key: 'is_base_currency', label: 'Base Currency', type: 'checkbox' },
    ],
  },

  accounts: {
    title: 'Account',
    endpointType: 'accounts',
    fields: [
      { key: 'name', label: 'Name', type: 'text', required: true },
      { key: 'number', label: 'Number', type: 'text', required: true },
      { key: 'account_type_id', label: 'Account Type', type: 'select', required: true },
      { key: 'currency_id', label: 'Currency', type: 'select', required: true },
      { key: 'rate', label: 'Rate', type: 'number', required: true },
      { key: 'amount', label: 'Opening Amount', type: 'number', required: true },
      { key: 'transaction_type', label: 'Transaction Type', type: 'select', required: true },
      { key: 'remark', label: 'Remark', type: 'textarea' },
      { key: 'is_active', label: 'Active', type: 'checkbox' },
    ],
  },

  categories: {
    title: 'Category',
    endpointType: 'categories',
    fields: [
      { key: 'name', label: 'Name', type: 'text', required: true },
      { key: 'parent_id', label: 'Parent Category', type: 'select' },
      { key: 'remark', label: 'Remark', type: 'textarea' },
    ],
  },

  unit_measures: {
    title: 'Unit Measure',
    endpointType: 'unit_measures',
    fields: [
      { key: 'metric.name', label: 'Quantity Name', type: 'text', required: true },
      { key: 'metric.unit', label: 'Quantity Unit', type: 'text', required: true },
      { key: 'metric.symbol', label: 'Quantity Symbol', type: 'text', required: true },
      { key: 'measure.name', label: 'Measure Name', type: 'text', required: true },
      { key: 'measure.unit', label: 'Measure Unit', type: 'number', required: true },
      { key: 'measure.symbol', label: 'Measure Symbol', type: 'text', required: true },
    ],
  },

  sizes: {
    title: 'Size',
    endpointType: 'sizes',
    fields: [
      { key: 'name', label: 'Name', type: 'text', required: true },
      { key: 'code', label: 'Code', type: 'text', required: true },
    ],
  },

  brands: {
    title: 'Brand',
    endpointType: 'brands',
    fields: [{ key: 'name', label: 'Name', type: 'text', required: true }],
  },

  stores: {
    title: 'Store',
    endpointType: 'stores',
    fields: [
      { key: 'name', label: 'Name', type: 'text', required: true },
      { key: 'address', label: 'Address', type: 'textarea' },
      { key: 'is_main', label: 'Main Store', type: 'checkbox' },
    ],
  },

  items: {
    title: 'Item',
    endpointType: 'items',
    fields: [
      { key: 'name', label: 'Name', type: 'text', required: true },
      { key: 'code', label: 'Code', type: 'text', required: true },
      { key: 'unit_measure_id', label: 'Unit Measure', type: 'select', required: true },
      { key: 'sale_price', label: 'Sale Price', type: 'number', required: true },
      { key: 'brand_id', label: 'Brand', type: 'select' },
      { key: 'category_id', label: 'Category', type: 'select' },
      { key: 'size_id', label: 'Size', type: 'select' },
    ],
  },

  'items-for-sale': {
    title: 'Item',
    endpointType: 'items',
    fields: [
      { key: 'name', label: 'Name', type: 'text', required: true },
      { key: 'code', label: 'Code', type: 'text', required: true },
      { key: 'unit_measure_id', label: 'Unit Measure', type: 'select', required: true },
      { key: 'sale_price', label: 'Sale Price', type: 'number', required: true },
      { key: 'brand_id', label: 'Brand', type: 'select' },
      { key: 'category_id', label: 'Category', type: 'select' },
      { key: 'size_id', label: 'Size', type: 'select' },
    ],
  },

  // Customer/Supplier are Ledgers with type.
  ledgers: {
    title: 'Customer / Supplier',
    endpointType: 'ledgers',
    defaults: (ctx) => ({
      type: ctx?.additionalParams?.type || 'customer',
    }),
    fields: [
      { key: 'type', label: 'Ledger Type', type: 'select', required: true },
      { key: 'name', label: 'Name', type: 'text', required: true },
      { key: 'phone_no', label: 'Phone', type: 'text' },
      { key: 'email', label: 'Email', type: 'text' },
      { key: 'address', label: 'Address', type: 'textarea' },
      { key: 'currency_id', label: 'Currency', type: 'select' },
    ],
  },

  suppliers: {
    title: 'Supplier',
    endpointType: 'ledgers',
    defaults: () => ({ type: 'supplier' }),
    fields: [
      { key: 'type', label: 'Ledger Type', type: 'select', required: true },
      { key: 'name', label: 'Name', type: 'text', required: true },
      { key: 'phone_no', label: 'Phone', type: 'text' },
      { key: 'email', label: 'Email', type: 'text' },
      { key: 'address', label: 'Address', type: 'textarea' },
      { key: 'currency_id', label: 'Currency', type: 'select' },
    ],
  },

  customers: {
    title: 'Customer',
    endpointType: 'ledgers',
    defaults: () => ({ type: 'customer' }),
    fields: [
      { key: 'type', label: 'Ledger Type', type: 'select', required: true },
      { key: 'name', label: 'Name', type: 'text', required: true },
      { key: 'phone_no', label: 'Phone', type: 'text' },
      { key: 'email', label: 'Email', type: 'text' },
      { key: 'address', label: 'Address', type: 'textarea' },
      { key: 'currency_id', label: 'Currency', type: 'select' },
    ],
  },

  expense_categories: {
    title: 'Expense Category',
    endpointType: 'expense_categories',
    fields: [
      { key: 'name', label: 'Name', type: 'text', required: true },
      { key: 'remarks', label: 'Remarks', type: 'textarea' },
      { key: 'is_active', label: 'Active', type: 'checkbox' },
    ],
  },
}

export const isQuickCreatable = (resourceType) => Boolean(quickCreateRegistry?.[resourceType])

