/**
 * Which part of the app a record belongs to, keyed by the route-name prefix of
 * its routes (`items.destroy` -> `inventory`).
 *
 * The grouping mirrors the sidebar on purpose: a record's module is the section
 * its list screen lives under, which is the word an operator will go looking
 * for when they want the record back.
 */
export const RESOURCE_MODULES = {
    // Account
    'chart-of-accounts': 'account',
    'account-types': 'account',
    'account-transfers': 'account',
    'journal-classes': 'account',
    'journal-entries': 'account',

    // Inventory
    items: 'inventory',
    'discount-rules': 'inventory',
    'landed-costs': 'inventory',
    'item-transfers': 'inventory',
    'stock-adjustments': 'inventory',

    // Ledger
    customers: 'ledger',
    suppliers: 'ledger',

    // Human resources
    employees: 'hr',
    'employee-contracts': 'hr',
    'employee-documents': 'hr',
    departments: 'hr',
    designations: 'hr',

    // Attendance
    attendances: 'attendance',
    'attendance-devices': 'attendance',
    shifts: 'attendance',
    holidays: 'attendance',

    // Leave
    'leave-requests': 'leave',
    'leave-allocations': 'leave',
    'leave-types': 'leave',

    // Payroll
    payrolls: 'payroll',
    'salary-payments': 'payroll',
    'employee-loans': 'payroll',
    'salary-structures': 'payroll',
    'salary-components': 'payroll',
    'tax-bracket-sets': 'payroll',

    // Recruitment
    'job-openings': 'recruitment',
    'job-applications': 'recruitment',
    interviews: 'recruitment',

    // Owners
    owners: 'owners',
    drawings: 'owners',

    // Expense
    'expense-categories': 'expense',
    expenses: 'expense',

    // Purchase
    purchases: 'purchase',
    'purchase-orders': 'purchase',
    'purchase-quotations': 'purchase',
    'purchase-returns': 'purchase',

    // Sale
    sales: 'sale',
    'sale-orders': 'sale',
    'sale-quotations': 'sale',
    'sale-returns': 'sale',

    // Cash transactions
    receipts: 'cash_transactions',
    payments: 'cash_transactions',

    // User management
    users: 'user_management',
    roles: 'user_management',

    // Administration
    categories: 'administration',
    'customer-groups': 'administration',
    'payment-terms': 'administration',
    currencies: 'administration',
    'unit-measures': 'administration',
    sizes: 'administration',
    branches: 'administration',
    brands: 'administration',
    warehouses: 'administration',
    companies: 'administration',
    'landed-cost-categories': 'administration',
};

/**
 * The i18n key holding each module's display name. Most sit under
 * `sidebar.main`, but the sidebar titles a couple of groups from their own
 * section, so the key is spelled out rather than derived.
 */
const MODULE_LABEL_KEYS = {
    account: 'sidebar.main.account',
    inventory: 'sidebar.main.inventory',
    ledger: 'sidebar.main.ledger',
    hr: 'sidebar.main.hr',
    attendance: 'sidebar.main.attendance',
    leave: 'sidebar.main.leave',
    payroll: 'sidebar.main.payroll',
    recruitment: 'sidebar.main.recruitment',
    owners: 'sidebar.owners.owners',
    expense: 'sidebar.main.expense',
    purchase: 'sidebar.main.purchase',
    sale: 'sidebar.main.sale',
    cash_transactions: 'sidebar.main.cash_transactions',
    user_management: 'sidebar.main.user_management',
    administration: 'sidebar.main.administration',
};

/**
 * The module a route belongs to, or null when the route is not one of the
 * grouped resources (a custom screen, say). Callers fall back to a generic
 * message rather than guessing.
 */
export function moduleKeyForRoute(routeName) {
    const resource = String(routeName || '').split('.')[0];

    return RESOURCE_MODULES[resource] ?? null;
}

/**
 * The translated module name for a route, or null when there is no mapping.
 *
 * @param {string} routeName  e.g. 'items.destroy'
 * @param {(key: string) => string} t  the caller's `t` from `useI18n`
 */
export function moduleLabelForRoute(routeName, t) {
    const key = moduleKeyForRoute(routeName);

    if (!key) {
        return null;
    }

    const labelKey = MODULE_LABEL_KEYS[key];
    const label = t(labelKey);

    return label === labelKey ? null : label;
}
