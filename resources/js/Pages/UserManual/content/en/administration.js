export default {
    id: 'administration',
    number: '14',
    title: 'Administration & company setup guide',
    subtitle: 'Setup order · company · branch & warehouse · currency & unit · master data · invoice designer · users & roles · several companies · subscription',
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
                        ['Company logo', 'PNG or JPG, up to 2 MB. After you choose the file a window opens where you drag and zoom the picture so the whole logo sits in the frame; what you see is what is saved. The logo prints on invoices.'],
                        ['Business type', 'Shapes which fields, reports and defaults you see (pharmacy, supermarket, manufacturing and so on).'],
                        ['Company name in three languages', 'English is required. Add the Dari and Pashto names to print them on documents.'],
                        ['Costing method', 'How the cost of goods sold is worked out (FIFO, LIFO or weighted average). An item can have its own method.'],
                        ['Business licence', 'Licence number, issue date and expiry date. Shared by all branches and printed with the company name.'],
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
                {
                    type: 'demo',
                    demo: 'invoice-designer',
                    caption: 'Try it: pick each theme, switch parts of the invoice on and off and edit the footer note. The real designer also lets you build a custom template.',
                    labels: {
                        banner: 'Live demo — sample data, nothing is saved',
                        number_locale: 'en',
                        form_title: 'Invoice designer',
                        theme: 'Theme',
                        options: {
                            logo: 'Show the logo',
                            phone: 'Show the company phone',
                            discount: 'Show the discount column',
                            terms: 'Show the terms',
                            signature: 'Show signature lines',
                        },
                        footer: 'Footer note',
                        footer_sample: 'Thank you for your business.',
                        company: 'Noor Trading Co.',
                        invoice: 'Sales invoice',
                        sample_date: '2026-11-13',
                        customer: 'Customer',
                        customer_name: 'Ahmad Store',
                        item: 'Item',
                        qty: 'Qty',
                        price: 'Price',
                        discount: 'Discount',
                        total: 'Total',
                        terms: 'Goods once sold are not returnable without the invoice.',
                        seller: 'Seller',
                        buyer: 'Buyer',
                        lines: [
                            {
                                name: 'Black tea 200 g',
                                qty: 5,
                                price: 280,
                                discount: 0,
                            },
                            {
                                name: 'Basmati rice 5 kg',
                                qty: 2,
                                price: 1450,
                                discount: 100,
                            },
                        ],
                    },
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
            id: 'companies-branches',
            number: '9',
            title: 'Several companies and branches',
            blocks: [
                {
                    type: 'p',
                    text: 'One person can work in more than one company, and in several branches of each. Each company keeps its own books, items, customers and users’ roles; nothing is shared between companies. The company and the branch you are working in are always the ones shown in the header.',
                },
                {
                    type: 'h4',
                    text: 'a) Choosing where to work',
                },
                {
                    type: 'list',
                    items: [
                        'Your companies (profile menu): lists every company you may work in, with the one you are in marked Current. Press Switch on another company to work in it.',
                        'Your branches (profile menu): lists the branches of the current company you may work in. Switch the same way.',
                        'Only the companies and branches you were given are listed. Your home company and home branch are always available.',
                        'A page left open on another company in an old browser tab is refused when you try to save, so a document can never be saved into the wrong company. Reload the tab.',
                    ],
                },
                {
                    type: 'h4',
                    text: 'b) Who can create companies and give access',
                },
                {
                    type: 'list',
                    items: [
                        'Only a super-admin can create a company. A branch is added by someone who is allowed to create branches. The person who signs up becomes the super-admin of their own company.',
                        'On the Your companies page, New company opens the setup wizard (next chapter). On the Your branches page, New branch adds another place to trade that shares the company’s books.',
                        'To decide where a user may work, open Users and press Access on that user. For each company you can tick “Can work in this company” and pick the branches they may switch to.',
                        'A super-admin works in every branch of a company they have, so no branches are picked for them.',
                    ],
                },
                {
                    type: 'h4',
                    text: 'c) Roles per company',
                },
                {
                    type: 'list',
                    items: [
                        'The roles themselves (super-admin, admin, accountant, clerk and so on) are one shared set. What differs per company is who holds which role.',
                        'Pick the user’s role next to each company on the Access page. Someone can be a super-admin in one company and a clerk in another.',
                        'Only a super-admin of that company can change roles there.',
                    ],
                },
                {
                    type: 'note',
                    label: 'Super-admin is per company',
                    text: 'A super-admin has full power only in the companies where it was given to them, not everywhere in the system.',
                },
            ],
        },
        {
            id: 'company-wizard',
            number: '10',
            title: 'Creating and editing a company',
            blocks: [
                {
                    type: 'p',
                    text: 'A company is created by a short wizard, at sign-up or from Your companies → New company. Every step is checked by the system before you move on, and nothing is created until the last step passes.',
                },
                {
                    type: 'table',
                    headers: ['Step', 'What you enter'],
                    rows: [
                        ['Identity', 'The company name (English is required; add Dari and Pashto to print them on documents), the abbreviation, the logo, and the business type.'],
                        ['Books & language', 'The base currency, the language, the calendar (Gregorian or Jalali), the working style, and the costing method (how the cost of goods sold is worked out).'],
                        ['Contact & licence', 'How to reach the company, and its business licence: number, issue date and expiry date. The licence is shared by all branches and is printed with the company name.'],
                        ['Review', 'Check everything and press Create company. Each line has an Edit link back to its step.'],
                    ],
                },
                {
                    type: 'list',
                    items: [
                        'The logo is PNG or JPG up to 2 MB. After you choose it, a window opens where you drag and zoom the picture so the whole logo sits in the frame.',
                        'The business type shapes which fields, reports and defaults you see.',
                        'The base currency is the one your books are kept in. It cannot be changed once you trade.',
                    ],
                },
                {
                    type: 'h4',
                    text: 'What the system builds',
                },
                {
                    type: 'p',
                    text: 'After the last step an animated screen shows the company being set up: the company and its main branch, the chart of accounts, default currencies, units and measures, categories and brands, the walk-in cash customer, expense categories and journal classes, human resources defaults (departments, shifts, leave and salary), the financial year, the main warehouse, and your access. If anything fails, nothing is saved and you can try again.',
                },
                {
                    type: 'h4',
                    text: 'Editing an existing company',
                },
                {
                    type: 'list',
                    items: [
                        'Open the company page and press Edit company. You must be working in that company; otherwise the page asks you to switch to it first.',
                        'The same steps open all at once, and two more join them: Company settings (numbering, posting, formats) and Invoice design (theme and formats).',
                        'Nothing is saved until you press Save changes. The one exception is the invoice theme, which saves as soon as you pick it.',
                        'You can view the company settings step without special rights, but changing it needs the company settings permission.',
                    ],
                },
                {
                    type: 'demo',
                    demo: 'company-wizard',
                    caption: 'Try it: leave the English name empty and press Next to see the check on each step, then go through to Create company and watch the set-up list.',
                    labels: {
                        banner: 'Live demo — sample data, nothing is saved',
                        number_locale: 'en',
                        form_title: 'New company',
                        steps: ['Identity', 'Books & language', 'Contact & licence', 'Review'],
                        name_en: 'Name (English)',
                        abbreviation: 'Abbreviation',
                        name_fa: 'Name (Dari)',
                        name_ps: 'Name (Pashto)',
                        business_type: 'Business type',
                        business_types: ['Supermarket', 'Pharmacy', 'Manufacturing', 'Accounting firm'],
                        business_type_hint: 'Shapes which fields, reports and defaults you see.',
                        base_currency: 'Base currency',
                        currencies: ['AFN — Afghani', 'USD — US dollar'],
                        currency_hint: 'Your books are kept in this currency. It cannot be changed once you trade.',
                        language: 'Language',
                        languages: ['English', 'Dari', 'Pashto'],
                        calendar: 'Calendar',
                        calendars: ['Gregorian', 'Jalali'],
                        costing: 'Costing method',
                        costings: ['FIFO', 'LIFO', 'Weighted average'],
                        costing_hint: 'How the cost of goods sold is worked out.',
                        phone: 'Phone',
                        email: 'Email',
                        license_number: 'Licence number',
                        license_hint: 'Shared by all branches; printed with your company name.',
                        license_issued: 'Issued on',
                        license_expires: 'Expires on',
                        name: 'Name',
                        not_set: 'Not set',
                        edit: 'Edit',
                        back: 'Back',
                        next: 'Next',
                        create: 'Create company',
                        name_required: 'The English name is required.',
                        license_dates: 'The licence cannot expire before it was issued.',
                        building: 'Setting up your company…',
                        ready: 'Your company is ready',
                        again: 'Start again',
                        setup_steps: ['Creating the company', 'Opening the main branch', 'Building the chart of accounts', 'Adding the default currencies', 'Setting up units and measures', 'Preparing categories and brands', 'Adding the walk-in cash customer', 'Setting up expense categories and journal classes', 'Setting up HR: departments, shifts, leave and salary', 'Opening the financial year', 'Creating the main warehouse', 'Giving you access'],
                    },
                },
            ],
        },
        {
            id: 'company-settings-modules',
            number: '11',
            title: 'Company settings and switching modules off',
            blocks: [
                {
                    type: 'p',
                    text: 'Company settings are the rules everyone in the company works by. They are grouped in four tabs.',
                },
                {
                    type: 'table',
                    headers: ['Tab', 'What it holds'],
                    rows: [
                        ['Documents', 'Document numbering and document rules, such as the return window in days.'],
                        ['Posting', 'How and when documents are posted.'],
                        ['Formats', 'How numbers, dates and balances are written.'],
                        ['Modules', 'Which modules the company uses.'],
                    ],
                },
                {
                    type: 'list',
                    items: [
                        'Modules: switch off the modules the company does not use. A module that is off disappears from the menu and its pages are closed. Its records are kept and come back when you switch it on again.',
                        'Only someone allowed to change company settings can change them; everyone else sees them read-only.',
                        'A module can also be missing because it is not part of the company’s package (see the next chapter).',
                    ],
                },
            ],
        },
        {
            id: 'subscription',
            number: '12',
            title: 'Your subscription and licence',
            blocks: [
                {
                    type: 'p',
                    text: 'Each company runs on a package that NextBook issues: which modules it has, how many users and branches it may have, and until when it is paid. The company’s licence is refreshed automatically from NextBook; you do not enter anything.',
                },
                {
                    type: 'h4',
                    text: 'a) The My subscription page',
                },
                {
                    type: 'list',
                    items: [
                        'Open My subscription from the profile menu. It shows your package, how long it runs, how much of it you use (users and branches), and the modules in your package.',
                        'It also shows the invoices and payments recorded for your subscription.',
                        'If the page says no package limits apply, the company has no limits.',
                        'To change or renew the package, contact NextBook. The platform pages for packages and subscriptions are for the NextBook platform admin only.',
                    ],
                },
                {
                    type: 'h4',
                    text: 'b) Warnings and read-only mode',
                },
                {
                    type: 'table',
                    headers: ['State', 'What you see', 'What you can do'],
                    rows: [
                        ['Active', 'Nothing.', 'Everything your role allows.'],
                        ['Expiring soon', 'A banner with the end date and the days left, about two weeks before it ends.', 'Everything — renew to avoid interruption.'],
                        ['Expired', 'A banner saying the subscription ended.', 'Read, print and export everything. Nothing can be added, changed, posted or deleted.'],
                        ['Blocked', 'A banner with the reason.', 'The same as expired.'],
                    ],
                },
                {
                    type: 'list',
                    items: [
                        'The read-only mode is deliberately soft: nothing is locked or removed, so you can always read and print your own books.',
                        'A few actions are still allowed: signing out, changing language, switching company or branch, notifications, and your own profile and personal settings.',
                        'If NextBook cannot be reached for a while, the last licence keeps working and its end date is stretched by a few grace days. A block is never softened.',
                    ],
                },
                {
                    type: 'h4',
                    text: 'c) Package limits',
                },
                {
                    type: 'list',
                    items: [
                        'Users and branches: when all the places in your package are used, adding another is refused with a message that names the package and the limit.',
                        'Modules: only the modules in your package are available, even if they are switched on in Company settings.',
                    ],
                },
            ],
        },
        {
            id: 'troubleshooting',
            number: '13',
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
                        ['A save is refused: “subscription ended” or “company is blocked”', 'The company is read-only. You can still read, print and export; contact NextBook to renew or unblock it.'],
                        ['“Your package allows … users/branches, and they are all in use”', 'The package limit is reached. Ask NextBook to upgrade your package.'],
                        ['I cannot see a company or branch I need', 'A super-admin must give you access on the user’s Access page. Only the companies and branches you were given are listed.'],
                        ['A whole module is missing from the menu', 'It is switched off under Company settings → Modules, or it is not part of your package.'],
                    ],
                },
            ],
        },
    ],
}
