import { defineAsyncComponent } from 'vue'

/**
 * The live demos a guide can place with a `{ type: 'demo', demo: '<name>' }`
 * block. Each is a small self-contained component on sample data; loaded only
 * when a chapter that uses it is opened.
 */
const DEMOS = {
    'sales-invoice': defineAsyncComponent(() => import('./SalesInvoiceDemo.vue')),
    'purchase-invoice': defineAsyncComponent(() => import('./PurchaseInvoiceDemo.vue')),
    'receipt': defineAsyncComponent(() => import('./ReceiptDemo.vue')),
    'landed-cost': defineAsyncComponent(() => import('./LandedCostDemo.vue')),
    'pricing': defineAsyncComponent(() => import('./PricingDemo.vue')),
    'item-variants': defineAsyncComponent(() => import('./ItemVariantsDemo.vue')),
    'company-wizard': defineAsyncComponent(() => import('./CompanyWizardDemo.vue')),
    'invoice-designer': defineAsyncComponent(() => import('./InvoiceDesignerDemo.vue')),
    'journal-entry': defineAsyncComponent(() => import('./JournalEntryDemo.vue')),
    'expense': defineAsyncComponent(() => import('./ExpenseDemo.vue')),
    'account-transfer': defineAsyncComponent(() => import('./AccountTransferDemo.vue')),
    'discount-rules': defineAsyncComponent(() => import('./DiscountRulesDemo.vue')),
}

export function demoComponent(name) {
    return DEMOS[name] ?? null
}
