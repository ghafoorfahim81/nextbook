/**
 * The app's modules (business areas) and the menu each one owns.
 *
 * The sidebar shows the menu of the module the current page belongs to; the
 * modules page (/modules) shows one tile per module. Both read this list, so a
 * page added here shows in both.
 *
 * A module holds groups, a group holds links (the old sidebar shape). Each
 * group's `menuKey` is the sidebar group it used to be, so a user's "hide
 * these menus" preference (appearance.sidebar_menus) keeps working.
 *
 * `paths` are extra URL prefixes that belong to the module without a link of
 * their own (a show page reached from somewhere else, a settings screen).
 */
import {
    ArrowLeftRight, Banknote, BookUser, BriefcaseBusiness, Building, Building2, CalendarClock, CalendarDays, ChartColumn,
    ChartPie, ClipboardList, Coins, Database, FileChartLine, FileText, FolderKanban, Handshake, History,
    MonitorSmartphone, Network, Receipt, Settings2, ShoppingBasket, ShoppingCart,
    SlidersHorizontal, Trash2, Truck, UserPlus, UserRound, Users, Wallet,
} from 'lucide-vue-next'

export function buildAppModules(t) {
    return [
        {
            id: 'accounting',
            icon: ChartColumn,
            title: t('sidebar.modules.accounting.title'),
            description: t('sidebar.modules.accounting.description'),
            groups: [
                {
                    key: 'account', menuKey: 'account', title: t('sidebar.main.account'), icon: ChartColumn,
                    items: [
                        { title: t('sidebar.account.chart_of_account'), url: '/chart-of-accounts', permission: 'accounts.view_any' },
                        { title: t('sidebar.main.transfer'), url: '/account-transfers', icon: ArrowLeftRight, permission: 'account_transfers.view_any' },
                        { title: t('sidebar.journal_entry.journal_class'), url: '/journal-classes', icon: FileText, permission: 'journal_classes.view_any' },
                        { title: t('sidebar.journal_entry.journal_entry'), url: '/journal-entries', icon: FileText, permission: 'journal_entries.view_any' },
                    ],
                },
                {
                    key: 'cash_transactions', menuKey: 'cash_transactions', title: t('sidebar.main.cash_transactions'), icon: Receipt,
                    items: [
                        { title: t('sidebar.main.receipt'), url: '/receipts', permission: 'receipts.view_any' },
                        { title: t('sidebar.main.payment'), url: '/payments', permission: 'payments.view_any' },
                        { title: t('sidebar.main.contra_settlement'), url: '/contra-settlements', permission: 'contra_settlements.view_any' },
                    ],
                },
                {
                    // Phase 7.
                    key: 'dimensions', menuKey: 'account', title: t('sidebar.groups.dimensions'), icon: SlidersHorizontal,
                    items: [
                        { title: t('dimension.cost_centers'), url: '/cost-centers', permission: 'cost_centers.view_any' },
                        { title: t('dimension.allocation_rules'), url: '/allocation-rules', permission: 'allocation_rules.view_any' },
                    ],
                },
                {
                    key: 'currencies', menuKey: 'administration', title: t('sidebar.groups.currencies'), icon: Coins,
                    items: [
                        { title: t('sidebar.administration.currency'), url: '/currencies', permission: 'currencies.view_any' },
                        { title: t('sidebar.administration.currency_rate_update'), url: '/currency-rate-updates', permission: 'currencies.view_any' },
                    ],
                },
            ],
            paths: ['/account-types', '/settlements'],
        },
        {
            id: 'inventory',
            icon: Database,
            title: t('sidebar.modules.inventory.title'),
            description: t('sidebar.modules.inventory.description'),
            groups: [
                {
                    key: 'inventory', menuKey: 'inventory', title: t('sidebar.main.inventory'), icon: Database,
                    items: [
                        { title: t('sidebar.inventory.item'), url: '/items', permission: 'items.view_any' },
                        { title: t('sidebar.inventory.item_transfer'), url: '/item-transfers', permission: 'item_transfers.view_any' },
                        { title: t('sidebar.inventory.stock_adjustment'), url: '/stock-adjustments', permission: 'stock_adjustment.view_any' },
                        { title: t('sidebar.inventory.pricing'), url: '/item-pricing', permission: 'items.view_any' },
                        { title: t('discount_rule.discount_rules'), url: '/discount-rules', permission: 'discount_rules.view_any' },
                        { title: t('sidebar.inventory.landed_costs'), url: '/landed-costs', permission: 'landed_costs.view_any' },
                        { title: t('sidebar.inventory.fast_entry'), url: '/item-fast-entry', permission: ['items.view_any', 'items.create'] },
                        { title: t('sidebar.inventory.fast_opening'), url: '/item-fast-opening', permission: ['items.view_any', 'items.create'] },
                        { title: t('sidebar.inventory.barcode_print'), url: '/item-barcode-print', permission: 'items.view_any' },
                    ],
                },
                {
                    key: 'inventory_setup', menuKey: 'administration', title: t('sidebar.groups.inventory_setup'), icon: SlidersHorizontal,
                    items: [
                        { title: t('sidebar.administration.category'), url: '/categories', permission: 'categories.view_any' },
                        { title: t('sidebar.administration.brand'), url: '/brands', permission: 'brands.view_any' },
                        { title: t('sidebar.administration.unit_measure'), url: '/unit-measures', permission: 'unit_measures.view_any' },
                        { title: t('sidebar.administration.size'), url: '/sizes', permission: 'sizes.view_any' },
                        { title: t('sidebar.administration.warehouse'), url: '/warehouses', permission: 'warehouses.view_any' },
                        { title: t('sidebar.administration.landed_cost_category'), url: '/landed-cost-categories', permission: 'landed_cost_categories.view_any' },
                    ],
                },
            ],
            paths: ['/item', '/fast-opening'],
        },
        {
            id: 'sales',
            icon: ShoppingCart,
            title: t('sidebar.modules.sales.title'),
            description: t('sidebar.modules.sales.description'),
            groups: [
                {
                    key: 'sale', menuKey: 'sale', title: t('sidebar.main.sale'), icon: ShoppingCart,
                    items: [
                        { title: t('sidebar.sale.sale'), url: '/sales', permission: 'sales.view_any' },
                        { title: t('sidebar.sale.sale_order'), url: '/sale-orders', permission: 'sale_orders.view_any' },
                        { title: t('sidebar.sale.sale_quotation'), url: '/sale-quotations', permission: 'sale_quotations.view_any' },
                        { title: t('sidebar.sale.sale_return'), url: '/sale-returns', permission: 'sale_returns.view_any' },
                    ],
                },
                {
                    key: 'customers', menuKey: 'ledger', title: t('sidebar.groups.customers'), icon: Users,
                    items: [
                        { title: t('sidebar.ledger.customer'), url: '/customers', permission: 'customers.view_any' },
                        { title: t('sidebar.administration.customer_group'), url: '/customer-groups', permission: 'customer_groups.view_any' },
                        { title: t('sidebar.administration.payment_term'), url: '/payment-terms', permission: 'payment_terms.view_any' },
                    ],
                },
            ],
            paths: ['/invoice-formats'],
        },
        {
            id: 'purchase',
            icon: ShoppingBasket,
            title: t('sidebar.modules.purchase.title'),
            description: t('sidebar.modules.purchase.description'),
            groups: [
                {
                    key: 'purchase', menuKey: 'purchase', title: t('sidebar.main.purchase'), icon: ShoppingBasket,
                    items: [
                        { title: t('sidebar.purchase.purchase'), url: '/purchases', permission: 'purchases.view_any' },
                        { title: t('sidebar.purchase.purchase_order'), url: '/purchase-orders', permission: 'purchase_orders.view_any' },
                        { title: t('sidebar.purchase.purchase_quotation'), url: '/purchase-quotations', permission: 'purchase_quotations.view_any' },
                        { title: t('sidebar.purchase.purchase_return'), url: '/purchase-returns', permission: 'purchase_returns.view_any' },
                    ],
                },
                {
                    key: 'suppliers', menuKey: 'ledger', title: t('sidebar.groups.suppliers'), icon: Truck,
                    items: [
                        { title: t('sidebar.ledger.supplier'), url: '/suppliers', permission: 'suppliers.view_any' },
                    ],
                },
            ],
            paths: [],
        },
        {
            id: 'hr',
            icon: BriefcaseBusiness,
            title: t('sidebar.modules.hr.title'),
            description: t('sidebar.modules.hr.description'),
            groups: [
                {
                    key: 'hr', menuKey: 'hr', title: t('sidebar.main.hr'), icon: BriefcaseBusiness,
                    items: [
                        { title: t('sidebar.hr.employee'), url: '/employees', permission: 'employees.view_any' },
                        { title: t('sidebar.hr.contract'), url: '/employee-contracts', permission: 'employee_contracts.view_any' },
                        { title: t('sidebar.hr.document'), url: '/employee-documents', permission: 'employee_documents.view_any' },
                        { title: t('sidebar.hr.department'), url: '/departments', permission: 'departments.view_any' },
                        { title: t('sidebar.hr.designation'), url: '/designations', permission: 'designations.view_any' },
                    ],
                },
                {
                    key: 'attendance', menuKey: 'attendance', title: t('sidebar.main.attendance'), icon: CalendarClock,
                    items: [
                        { title: t('sidebar.attendance.roster'), url: '/attendances/roster', permission: 'attendances.create' },
                        { title: t('sidebar.attendance.register'), url: '/attendances', permission: 'attendances.view_any' },
                        { title: t('sidebar.attendance.unmapped'), url: '/attendances/unmapped-punches', permission: 'attendances.view_any' },
                        { title: t('sidebar.attendance.device'), url: '/attendance-devices', permission: 'attendance_devices.view_any' },
                        { title: t('sidebar.attendance.shift'), url: '/shifts', permission: 'shifts.view_any' },
                        { title: t('sidebar.attendance.holiday'), url: '/holidays', permission: 'holidays.view_any' },
                        { title: t('sidebar.attendance.self_service'), url: '/my/attendance' },
                    ],
                },
                {
                    key: 'leave', menuKey: 'leave', title: t('sidebar.main.leave'), icon: CalendarDays,
                    items: [
                        { title: t('sidebar.leave.request'), url: '/leave-requests', permission: 'leave_applications.view_any' },
                        { title: t('sidebar.leave.allocation'), url: '/leave-allocations', permission: 'leaves.view_any' },
                        { title: t('sidebar.leave.type'), url: '/leave-types', permission: 'leave_types.view_any' },
                    ],
                },
                {
                    key: 'payroll', menuKey: 'payroll', title: t('sidebar.main.payroll'), icon: Wallet,
                    items: [
                        { title: t('sidebar.payroll.payroll'), url: '/payrolls', permission: 'payrolls.view_any' },
                        { title: t('sidebar.payroll.salary_payment'), url: '/salary-payments', permission: 'salary_payments.view_any' },
                        { title: t('sidebar.payroll.loan'), url: '/employee-loans', permission: 'loans.view_any' },
                        { title: t('sidebar.payroll.structure'), url: '/salary-structures', permission: 'salary_structures.view_any' },
                        { title: t('sidebar.payroll.component'), url: '/salary-components', permission: 'salary_components.view_any' },
                        { title: t('sidebar.payroll.tax_table'), url: '/tax-bracket-sets', permission: 'tax_bracket_sets.view_any' },
                    ],
                },
                {
                    key: 'recruitment', menuKey: 'recruitment', title: t('sidebar.main.recruitment'), icon: UserPlus,
                    items: [
                        { title: t('sidebar.recruitment.opening'), url: '/job-openings', permission: 'job_openings.view_any' },
                        { title: t('sidebar.recruitment.application'), url: '/job-applications', permission: 'job_applications.view_any' },
                        { title: t('sidebar.recruitment.interview'), url: '/interviews', permission: 'interviews.view_any' },
                    ],
                },
            ],
            paths: ['/attendance-device-mappings', '/leave-allocations'],
        },
        {
            id: 'expenses',
            icon: Banknote,
            title: t('sidebar.modules.expenses.title'),
            description: t('sidebar.modules.expenses.description'),
            groups: [
                {
                    key: 'expense', menuKey: 'expense', title: t('sidebar.main.expense'), icon: Banknote,
                    items: [
                        { title: t('sidebar.expense.expense'), url: '/expenses', permission: 'expenses.view_any' },
                        { title: t('sidebar.expense.expense_category'), url: '/expense-categories', permission: 'expense_categories.view_any' },
                    ],
                },
            ],
            paths: [],
        },
        {
            id: 'owners',
            icon: BookUser,
            title: t('sidebar.modules.owners.title'),
            description: t('sidebar.modules.owners.description'),
            groups: [
                {
                    key: 'owners', menuKey: 'owners', title: t('sidebar.owners.owners'), icon: BookUser,
                    items: [
                        { title: t('sidebar.owners.owners'), url: '/owners', permission: 'owners.view_any' },
                        { title: t('sidebar.owners.drawing'), url: '/drawings', permission: 'drawings.view_any' },
                    ],
                },
            ],
            paths: [],
        },
        {
            id: 'reports',
            icon: FileChartLine,
            title: t('sidebar.modules.reports.title'),
            description: t('sidebar.modules.reports.description'),
            groups: [
                { key: 'reports', menuKey: 'reports', title: t('sidebar.main.reports'), icon: FileChartLine, url: '/reports', permission: 'reports.view_any' },
            ],
            paths: [],
        },
        {
            id: 'settings',
            icon: Settings2,
            title: t('sidebar.modules.settings.title'),
            description: t('sidebar.modules.settings.description'),
            groups: [
                {
                    key: 'user_management', menuKey: 'user_management', title: t('sidebar.main.user_management'), icon: UserRound,
                    items: [
                        { title: t('sidebar.user_management.user'), url: '/users', permission: 'users.view_any' },
                        { title: t('sidebar.user_management.role'), url: '/roles', permission: 'roles.view_any' },
                    ],
                },
                {
                    key: 'workspace', menuKey: 'system', title: t('sidebar.groups.workspace'), icon: Building2,
                    items: [
                        { title: t('sidebar.groups.companies'), url: '/workspace/companies', permission: 'companies.view' },
                        { title: t('sidebar.groups.branches'), url: '/workspace/branches', permission: 'branches.view_any' },
                        { title: t('sidebar.main.preferences'), url: '/preferences' },
                    ],
                },
                {
                    key: 'system', menuKey: 'system', title: t('sidebar.main.system'), icon: Settings2,
                    items: [
                        { title: t('sidebar.system.trash'), url: '/deleted-records', icon: Trash2, permission: 'deleted_records.view_any' },
                        { title: t('sidebar.system.activity_logs'), url: '/activity-logs', icon: History, permission: 'activity_logs.view_any' },
                    ],
                },
            ],
            paths: ['/companies', '/branches', '/company', '/workspace', '/users', '/roles'],
        },
    ]
}

/**
 * Modules that are planned but not built (config/modules.php, available =>
 * false). The modules page shows them as "coming soon": no link, no menu.
 */
export function plannedModules(t) {
    return [
        { id: 'scm', icon: Network },
        { id: 'pos', icon: MonitorSmartphone },
        { id: 'crm', icon: Handshake },
        { id: 'procurement', icon: ClipboardList },
        { id: 'projects', icon: FolderKanban },
        { id: 'fixed_assets', icon: Building },
        { id: 'bi', icon: ChartPie },
    ].map((module) => ({
        ...module,
        title: t(`company.modules.${module.id}`),
        description: t(`sidebar.planned.${module.id}`),
    }))
}

// Each module its own colour (Tailwind gradient stops), so its icon reads at a
// glance on the modules page and beside the module's name in the top bar.
const TONES = {
    scm: 'from-lime-400 to-green-600',
    pos: 'from-orange-400 to-rose-500',
    crm: 'from-blue-400 to-violet-600',
    procurement: 'from-teal-400 to-sky-600',
    projects: 'from-yellow-400 to-amber-600',
    fixed_assets: 'from-stone-400 to-stone-600',
    bi: 'from-indigo-400 to-fuchsia-600',
    accounting: 'from-violet-500 to-indigo-600',
    inventory: 'from-amber-400 to-orange-500',
    sales: 'from-emerald-400 to-teal-600',
    purchase: 'from-sky-400 to-blue-600',
    hr: 'from-pink-400 to-rose-600',
    expenses: 'from-red-400 to-red-600',
    owners: 'from-cyan-400 to-cyan-700',
    reports: 'from-fuchsia-500 to-purple-700',
    settings: 'from-slate-400 to-slate-600',
}

export function moduleTone(id) {
    return TONES[id] ?? 'from-primary to-violet-500'
}

function pathOf(url) {
    const path = String(url || '').split('?')[0].replace(/\/+$/, '')
    return path === '' ? '/' : path
}

export function urlUnder(url, prefix) {
    const path = pathOf(url)
    const base = pathOf(prefix)
    return path === base || path.startsWith(`${base}/`)
}

/**
 * The modules this user may see, each cut down to the links they may open,
 * with `firstUrl` (where its tile leads). A module left with no link is gone.
 *
 * @param can          (permission) => bool
 * @param isOff        (url) => bool, true under a module the company switched off
 * @param allowedMenus Set of menu keys the user keeps, or null for all
 */
export function visibleModules(modules, { can, isOff, allowedMenus = null }) {
    const allowed = (link) => (!link.permission || can(link.permission)) && !isOff(link.url)

    return modules
        .map((module) => {
            const groups = module.groups
                .filter((group) => !allowedMenus || !group.menuKey || allowedMenus.has(group.menuKey))
                .map((group) => {
                    if (!group.items) return allowed(group) ? group : null
                    const items = group.items.filter(allowed)
                    return items.length ? { ...group, items } : null
                })
                .filter(Boolean)

            if (!groups.length) return null

            const first = groups[0]
            return { ...module, groups, firstUrl: first.items ? first.items[0].url : first.url }
        })
        .filter(Boolean)
}

/** The module a URL belongs to, by its links and its extra paths. */
export function moduleForUrl(modules, url) {
    let best = null
    let bestLength = -1

    // The longest matching prefix wins: /attendances/roster before /attendances.
    for (const module of modules) {
        const prefixes = [
            ...module.groups.flatMap((group) => (group.items ? group.items.map((item) => item.url) : [group.url])),
            ...(module.paths ?? []),
        ]
        for (const prefix of prefixes) {
            if (prefix && prefix !== '#' && urlUnder(url, prefix) && pathOf(prefix).length > bestLength) {
                best = module
                bestLength = pathOf(prefix).length
            }
        }
    }

    return best
}
