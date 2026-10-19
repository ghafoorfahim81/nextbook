export default {
    id: 'administration',
    number: '14',
    title: 'Administration & company setup guide',
    subtitle: 'Setup order · company · branch & warehouse · currency & unit · master data · invoice designer · users & roles',
    summary:
        'The administration module is where you define the company’s master data before daily work: org structure, currencies, units, categories, the invoice template, and users.',
    chapters: [
        {
            id: 'setup-order',
            number: '1',
            title: 'Setup order',
            blocks: [
                {
                    type: 'p',
                    text: 'The order matters — each step depends on the one before it. Follow this list top to bottom:',
                },
                {
                    type: 'ol',
                    items: [
                        'Administration → Company: confirm the name, calendar type, base currency, and legal details. (Calendar and base currency are hard to change later.)',
                        'Create branches and warehouses.',
                        'Add currencies and, if you work in multiple currencies, keep rate updates current.',
                        'Define units of measure and, if needed, conversions between them.',
                        'Create item categories, brands, and sizes.',
                        'Create customer groups, payment terms, and landed-cost categories if needed.',
                        'Create expense categories (for the expenses module).',
                        'Enter the chart of accounts and opening balances (accounting guide).',
                        'Select the invoice template on the company page.',
                        'Create roles and attach users to them.',
                    ],
                },
                {
                    type: 'figure',
                    src: '/images/user-manual/administration/setup-order.png',
                    caption: 'The company page with details, calendar, and invoice template tabs',
                    hint: 'screenshot of the company page',
                },
            ],
        },
        {
            id: 'company',
            number: '2',
            title: 'Company settings',
            blocks: [
                {
                    type: 'table',
                    headers: ['Setting', 'Description'],
                    rows: [
                        ['Calendar type', 'Gregorian or Jalali. All dates and period names display accordingly.'],
                        ['Base currency', 'The currency all financial reports are presented in. Documents in other currencies are stored with an exchange rate to this currency.'],
                        ['Legal details', 'Official name, address, tax ID, phone — they appear on the printed invoice.'],
                        ['Invoice template', 'The sales print template for the whole company. Selected and designed on this page.'],
                    ],
                },
                {
                    type: 'warn',
                    label: 'Decide calendar and base currency early',
                    text: 'Changing the calendar type or base currency after real documents are posted is complex and risky. Get these right on day one.',
                },
            ],
        },
        {
            id: 'branch-warehouse',
            number: '3',
            title: 'Branch and warehouse',
            blocks: [
                {
                    type: 'p',
                    text: 'The branch is the place of work selected in the header and it scopes lists and documents. The warehouse is where stock is held; purchase, sale, transfer, and opening always need a specific warehouse.',
                },
                {
                    type: 'list',
                    items: [
                        'A branch can have several warehouses.',
                        'A user can have access to several branches and switch between them.',
                        'You cannot sell or buy from another branch’s warehouse on this branch’s invoice.',
                        'Document numbering is usually per branch.',
                    ],
                },
            ],
        },
        {
            id: 'currency-unit',
            number: '4',
            title: 'Currency, rate, and unit of measure',
            blocks: [
                {
                    type: 'list',
                    items: [
                        'Each cash/bank account has one currency, and that currency cannot be changed once it has a transaction.',
                        'Enter today’s rate from "Currency rate update". New documents take that day’s rate, and a later rate change does not affect old documents.',
                        'Units of measure can have conversions defined (1 carton = 12 pieces). This keeps buying by carton and selling by piece consistent and the stock report correct.',
                    ],
                },
            ],
        },
        {
            id: 'master-data',
            number: '5',
            title: 'Other master data',
            blocks: [
                {
                    type: 'table',
                    headers: ['Item', 'Use'],
                    rows: [
                        ['Item category / brand / size', 'Grouping and filtering items in lists and reports.'],
                        ['Customer group', 'Shared pricing and credit terms for a group.'],
                        ['Payment terms', 'The default payment window (cash, 7 days, 30 days) used on the invoice and the receivable aging report.'],
                        ['Landed-cost categories', 'Types of import cost (freight, customs, insurance) for allocation in the purchase module.'],
                        ['Expense categories', 'Types of running cost for the expenses module.'],
                    ],
                },
            ],
        },
        {
            id: 'invoice-designer',
            number: '6',
            title: 'The invoice designer',
            blocks: [
                {
                    type: 'p',
                    text: 'The printed sales invoice is the same for the whole company; it is not a per-user setting. Open the company page, click "Edit", then use the "Invoice designer".',
                },
                {
                    type: 'list',
                    items: [
                        'Ready-made templates 1 to 5 have a preview in the designer; pick one and save.',
                        'You can build a custom template: logo, header and footer text, table columns, terms, and signature.',
                        'A read-only user can see the designer but changes only open with the Edit button on the company page.',
                        'After changing the template, if the print shows the old version, refresh the page.',
                    ],
                },
                {
                    type: 'note',
                    label: 'A shared template',
                    text: 'Every salesperson prints with the same company template. A change on the company page changes everyone’s printout.',
                },
            ],
        },
        {
            id: 'users-roles',
            number: '7',
            title: 'Users and roles',
            blocks: [
                {
                    type: 'p',
                    text: 'A user is a login account. A role is a set of permissions. Define access on the role, not per person — it is simpler and safer to manage.',
                },
                {
                    type: 'ol',
                    items: [
                        'Administration → Roles → New role. Name it and select the required permissions.',
                        'Administration → Users → New user. Enter the name, email, and initial password.',
                        'Attach the user to one or more branches and a role.',
                        'The user changes the password on first login and enables two-factor login if needed.',
                    ],
                },
                {
                    type: 'table',
                    headers: ['Example role', 'Typical permissions'],
                    rows: [
                        ['Sales cashier', 'Sales, receipts, customers, items (view only). Not the chart of accounts, not payroll.'],
                        ['Accountant', 'Accounting, receipts, payments, financial reports, journal entries.'],
                        ['Warehouse keeper', 'Items, item transfers, stock adjustments, purchase receiving.'],
                        ['Manager', 'Order approval, payroll approval, loan approval, all reports.'],
                    ],
                },
                {
                    type: 'warn',
                    label: 'Keep super admin limited',
                    text: 'A super admin sees and can change everything, including deleting documents. Give this role to only one or two trusted people.',
                },
            ],
        },
        {
            id: 'quick-create',
            number: '8',
            title: 'Quick-create while working',
            blocks: [
                {
                    type: 'p',
                    text: 'On many forms (sale, purchase, expense) there is a "+" button next to a dropdown that lets you create a new item, customer, or supplier without leaving the form. The record is created and immediately selected in that dropdown. Add its remaining details later from that record’s main page.',
                },
            ],
        },
        {
            id: 'troubleshooting',
            number: '9',
            title: 'Troubleshooting',
            blocks: [
                {
                    type: 'table',
                    headers: ['Problem', 'Likely cause and fix'],
                    rows: [
                        ['A new user cannot sign in', 'They may not be attached to any company, or the account is inactive.'],
                        ['A user cannot see a menu', 'Their role does not have that permission. Add the permission to the role.'],
                        ['The invoice prints with the old logo', 'The template was changed on the company page but the browser cached it. Refresh the page.'],
                        ['The exchange rate on a new invoice is wrong', 'Today’s rate was not entered. Record today’s rate from "Currency rate update".'],
                    ],
                },
            ],
        },
    ],
}
