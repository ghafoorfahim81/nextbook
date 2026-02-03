// Central registry for quick-create modal fields per resource type.
// Resource types should match what `NextSelect` receives via `resource-type`.

export const QUICK_CREATE_EVENT = 'nextbook:quick-created'

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
    titleKey: 'admin.currency.currency',
    endpointType: 'currencies',
    fields: [
      { key: 'name', labelKey: 'general.name', type: 'text', required: true },
      { key: 'code', labelKey: 'admin.currency.code', type: 'text', required: true },
      { key: 'symbol', labelKey: 'admin.shared.symbol', type: 'text', required: true },
      { key: 'format', labelKey: 'admin.currency.format', type: 'text', required: true },
      { key: 'exchange_rate', labelKey: 'admin.currency.exchange_rate', type: 'number', required: true },
      { key: 'flag', labelKey: 'admin.currency.flag', type: 'text' },
      { key: 'is_active', labelKey: 'general.active', type: 'checkbox' },
      { key: 'is_base_currency', labelKey: 'admin.currency.base_currency', type: 'checkbox' },
    ],
  },

  accounts: {
    titleKey: 'account.account',
    endpointType: 'accounts',
    fields: [
      { key: 'name', labelKey: 'general.name', type: 'text', required: true },
      { key: 'number', labelKey: 'general.number', type: 'text', required: true },
      { key: 'account_type_id', labelKey: 'account.account_type', type: 'select', required: true },
      { key: 'currency_id', labelKey: 'admin.currency.currency', type: 'select', required: true },
      { key: 'rate', labelKey: 'general.rate', type: 'number', required: true },
      { key: 'amount', labelKey: 'general.amount', type: 'number', required: true },
      { key: 'transaction_type', labelKey: 'general.transaction_type', type: 'select', required: true },
      { key: 'remark', labelKey: 'general.remark', type: 'textarea' },
      { key: 'is_active', labelKey: 'general.active', type: 'checkbox' },
    ],
  },

  categories: {
    titleKey: 'admin.category.category',
    endpointType: 'categories',
    fields: [
      { key: 'name', labelKey: 'general.name', type: 'text', required: true },
      { key: 'parent_id', labelKey: 'admin.shared.parent', type: 'select' },
      { key: 'remark', labelKey: 'general.remarks', type: 'textarea' },
    ],
  },

  unit_measures: {
    titleKey: 'admin.unit_measure.unit_measure',
    endpointType: 'unit_measures',
    fields: [
      { key: 'metric.name', labelKey: 'admin.unit_measure.quantity', type: 'text', required: true },
      { key: 'metric.unit', labelKey: 'admin.unit_measure.base_unit', type: 'text', required: true },
      { key: 'metric.symbol', labelKey: 'admin.shared.symbol', type: 'text', required: true },
      { key: 'measure.name', labelKey: 'general.name', type: 'text', required: true },
      { key: 'measure.unit', labelKey: 'admin.unit_measure.unit', type: 'number', required: true },
      { key: 'measure.symbol', labelKey: 'admin.shared.symbol', type: 'text', required: true },
    ],
  },

  sizes: {
    titleKey: 'admin.size.size',
    endpointType: 'sizes',
    fields: [
      { key: 'name', labelKey: 'general.name', type: 'text', required: true },
      { key: 'code', labelKey: 'admin.currency.code', type: 'text', required: true },
    ],
  },

  brands: {
    titleKey: 'admin.brand.brand',
    endpointType: 'brands',
    fields: [
      { key: 'name', labelKey: 'general.name', type: 'text', required: true },
      { key: 'legal_name', labelKey: 'admin.brand.legal_name', type: 'text' },
      { key: 'registration_number', labelKey: 'admin.brand.registration_number', type: 'text' },
      { key: 'email', labelKey: 'admin.shared.email', type: 'text' },
      { key: 'phone', labelKey: 'admin.shared.phone', type: 'text' },
      { key: 'website', labelKey: 'admin.brand.website', type: 'text' },
      { key: 'industry', labelKey: 'admin.brand.industry', type: 'text' },
      { key: 'type', labelKey: 'admin.brand.type', type: 'text' },
      { key: 'address', labelKey: 'admin.brand.address', type: 'text' },
      { key: 'city', labelKey: 'admin.brand.city', type: 'text' },
      { key: 'country', labelKey: 'admin.shared.country', type: 'text' },
    ],
  },

  stores: {
    titleKey: 'admin.store.store',
    endpointType: 'stores',
    fields: [
      { key: 'name', labelKey: 'general.name', type: 'text', required: true },
      { key: 'address', labelKey: 'admin.shared.address', type: 'textarea' },
    ],
  },

  items: {
    titleKey: 'item.item',
    endpointType: 'items',
    fields: [
      { key: 'name', labelKey: 'general.name', type: 'text', required: true },
      { key: 'code', labelKey: 'admin.currency.code', type: 'text', required: true },
      { key: 'unit_measure_id', labelKey: 'admin.unit_measure.unit_measure', type: 'select', required: true },
      { key: 'sale_price', labelKey: 'item.sale_price', type: 'number', required: true },
      { key: 'brand_id', labelKey: 'admin.brand.brand', type: 'select' },
      { key: 'category_id', labelKey: 'admin.category.category', type: 'select' },
      { key: 'size_id', labelKey: 'item.size', type: 'select' },
    ],
  },

  'items-for-sale': {
    titleKey: 'item.item',
    endpointType: 'items',
    fields: [
      { key: 'name', labelKey: 'general.name', type: 'text', required: true },
      { key: 'code', labelKey: 'admin.currency.code', type: 'text', required: true },
      { key: 'unit_measure_id', labelKey: 'admin.unit_measure.unit_measure', type: 'select', required: true },
      { key: 'sale_price', labelKey: 'item.sale_price', type: 'number', required: true },
      { key: 'brand_id', labelKey: 'admin.brand.brand', type: 'select' },
      { key: 'category_id', labelKey: 'admin.category.category', type: 'select' },
      { key: 'size_id', labelKey: 'item.size', type: 'select' },
    ],
  },

  // Customer/Supplier are Ledgers with type.
  ledgers: {
    titleKey: 'ledger.ledger',
    endpointType: 'ledgers',
    defaults: (ctx) => ({
      type: ctx?.additionalParams?.type || 'customer',
    }),
    fields: [
      { key: 'type', labelKey: 'general.type', type: 'select', required: true },
      { key: 'name', labelKey: 'general.name', type: 'text', required: true },
      { key: 'phone_no', labelKey: 'general.phone', type: 'text' },
      { key: 'email', labelKey: 'general.email', type: 'text' },
      { key: 'address', labelKey: 'general.address', type: 'textarea' },
      { key: 'currency_id', labelKey: 'admin.currency.currency', type: 'select' },
    ],
  },

  suppliers: {
    titleKey: 'ledger.supplier.supplier',
    endpointType: 'ledgers',
    defaults: () => ({ type: 'supplier' }),
    fields: [
      { key: 'type', labelKey: 'general.type', type: 'select', required: true },
      { key: 'name', labelKey: 'general.name', type: 'text', required: true },
      { key: 'phone_no', labelKey: 'general.phone', type: 'text' },
      { key: 'email', labelKey: 'general.email', type: 'text' },
      { key: 'address', labelKey: 'general.address', type: 'textarea' },
      { key: 'currency_id', labelKey: 'admin.currency.currency', type: 'select' },
    ],
  },

  customers: {
    titleKey: 'ledger.customer.customer',
    endpointType: 'ledgers',
    defaults: () => ({ type: 'customer' }),
    fields: [
      { key: 'type', labelKey: 'general.type', type: 'select', required: true },
      { key: 'name', labelKey: 'general.name', type: 'text', required: true },
      { key: 'phone_no', labelKey: 'general.phone', type: 'text' },
      { key: 'email', labelKey: 'general.email', type: 'text' },
      { key: 'address', labelKey: 'general.address', type: 'textarea' },
      { key: 'currency_id', labelKey: 'admin.currency.currency', type: 'select' },
    ],
  },

  expense_categories: {
    titleKey: 'expense.category',
    endpointType: 'expense_categories',
    fields: [
      { key: 'name', labelKey: 'general.name', type: 'text', required: true },
      { key: 'remarks', labelKey: 'general.remarks', type: 'textarea' },
      { key: 'is_active', labelKey: 'general.active', type: 'checkbox' },
    ],
  },
}

export const isQuickCreatable = (resourceType) => Boolean(quickCreateRegistry?.[resourceType])

