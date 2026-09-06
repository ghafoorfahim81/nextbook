export default {
    id: 'overview',
    number: '1',
    title: 'Getting started & overview',
    subtitle: 'Sign in · dashboard · draft, post, reverse · how modules connect',
    summary:
        'Start here if you are new to NextBook. This guide covers the big picture, the principles shared by every module, and how the modules feed each other.',
    chapters: [
        {
            id: 'what-is-nextbook',
            number: '1',
            title: 'What NextBook is',
            blocks: [
                {
                    type: 'p',
                    text: 'NextBook is a financial and administrative system for Afghan businesses. It keeps inventory, purchasing, sales, cash and bank, expenses, double-entry accounting, and human resources in one place. The core idea is that every piece of data is entered once and then reused automatically by the other modules.',
                },
                { type: 'h3', text: 'Company, branch, and warehouse' },
                {
                    type: 'p',
                    text: 'There are three organisational levels. The company is the top level and holds global settings such as calendar type and invoice template. A branch is a specific place of work; each user works inside one branch at a time and sees that branch’s lists and documents. A warehouse (store) is where item stock is kept, and a branch can have several warehouses.',
                },
                {
                    type: 'table',
                    headers: ['Level', 'What it controls', 'Example'],
                    rows: [
                        ['Company', 'Calendar, base currency, invoice print template, legal details', 'Noor Trading Co.'],
                        ['Branch', 'The user’s place of work, the scope of lists and documents', 'Kabul branch, Herat branch'],
                        ['Warehouse', 'Where stock is held for purchase, sale, and transfer', 'Main store, Showroom store'],
                    ],
                },
                {
                    type: 'note',
                    label: 'A company is required',
                    text: 'A user who is not attached to any company cannot open the main screens. Ask an administrator to attach you to the company and give you a role.',
                },
            ],
        },
        {
            id: 'sign-in',
            number: '2',
            title: 'Sign in, language, and branch',
            blocks: [
                {
                    type: 'ol',
                    items: [
                        'Open the system and sign in with your email and password. If two-factor login is enabled, enter that code too.',
                        'Use the language switcher in the header to choose English, Dari, or Pashto. Dates follow the company calendar (Gregorian or Jalali).',
                        'If you belong to more than one branch, pick your working branch from the header. All lists and new documents stay inside that branch.',
                    ],
                },
                {
                    type: 'figure',
                    src: '/images/user-manual/overview/sign-in.png',
                    caption: 'The sign-in screen — work email, password, and the language switcher',
                },
                {
                    type: 'figure',
                    src: '/images/user-manual/overview/application-header.png',
                    caption: 'The header bar after login: branch switcher, global search, language switcher, and profile menu',
                    hint: 'screenshot of the application header (Home page)',
                },
                { type: 'h3', text: 'Home and Dashboard' },
                {
                    type: 'p',
                    text: 'Home is the landing page after login and includes shortcuts and tools such as currency exchange and unit conversion. The Dashboard shows totals for sales, purchases, cash, net profit, and top-selling items. What you see depends on your permissions — if a section is missing, your role does not have that access.',
                },
                { type: 'h3', text: 'Your profile menu' },
                {
                    type: 'table',
                    headers: ['Menu', 'What it does'],
                    rows: [
                        ['Account', 'Name, photo, password, two-factor login, and open sessions.'],
                        ['Notifications', 'In-app alerts for work that needs your attention (contract end, document expiry, pending approvals).'],
                        ['User Manual', 'This guide. It opens inside the application — it is not a separate PDF.'],
                        ['What’s New', 'A list of recent product changes.'],
                        ['Logout', 'Ends your session on this device only.'],
                    ],
                },
                {
                    type: 'tip',
                    label: 'Quick search',
                    text: 'Use the header search box to jump straight to a customer, supplier, item, or document without opening the sidebar.',
                },
            ],
        },
        {
            id: 'draft-post-reverse',
            number: '3',
            title: 'Draft, post, and reverse',
            blocks: [
                {
                    type: 'p',
                    text: 'Almost every financial document in NextBook — sale, purchase, receipt, payment, journal entry, account transfer, expense, owner drawing, item transfer — shares the same lifecycle. Understanding it is the key to the whole system.',
                },
                {
                    type: 'flow',
                    steps: [
                        'Draft — saved with no effect on the books',
                        'Post — writes the accounting entry and moves stock',
                        'Reverse — creates a mirror document to correct it',
                    ],
                },
                {
                    type: 'table',
                    headers: ['State', 'What it means', 'What you can do'],
                    rows: [
                        ['Draft', 'Saved but no effect yet in accounting or stock. Stock on a draft sale is only reserved.', 'Edit, delete, post'],
                        ['Posted', 'The accounting entry is written and, for stockable items, real stock has moved. The document is locked.', 'View, print, reverse'],
                        ['Reversed', 'A mirror document has been created that cancels the effect of the original. Both documents stay in the ledger.', 'View, print'],
                    ],
                },
                {
                    type: 'warn',
                    label: 'Do not edit a posted document',
                    text: 'There is no “fix the previous invoice” button, and there should not be. If a posted document is wrong, reverse it with a reason, then enter and post the correct draft. This keeps a full history for audit.',
                },
                {
                    type: 'tip',
                    label: 'Why so strict?',
                    text: 'Trustworthy accounting means no figure changes silently. Once a document is posted, the only way to correct it is another document everyone can see. That is exactly what a tax or audit review asks of you.',
                },
            ],
        },
        {
            id: 'roles-and-access',
            number: '4',
            title: 'Roles and access',
            blocks: [
                {
                    type: 'p',
                    text: 'Menus and buttons appear only when your role allows that action. If you cannot see an option, it usually means you do not have access, not that the system is broken.',
                },
                {
                    type: 'list',
                    items: [
                        'Preparing a document and approving it are two separate permissions. The person who prepares payroll is not necessarily the person who approves it.',
                        'Access is defined on a role, not per person. An administrator creates a role and attaches users to it.',
                        'Ask for the smallest role that covers your daily work. A cashier needs sales and receipts, not the ability to edit the chart of accounts.',
                    ],
                },
                {
                    type: 'note',
                    label: 'Super admin',
                    text: 'A super admin user sees everything. Give this role to only one or two trusted people.',
                },
            ],
        },
        {
            id: 'how-modules-connect',
            number: '5',
            title: 'How the modules connect',
            blocks: [
                {
                    type: 'p',
                    text: 'NextBook’s modules are not separate islands; anything you do in one module automatically affects the next. The table below shows the most important links.',
                },
                {
                    type: 'table',
                    headers: ['When you do this', 'The system automatically does this'],
                    rows: [
                        ['Create a new item', 'Its asset, income, and cost-of-goods accounts are wired to the chart of accounts'],
                        ['Create a customer or supplier', 'A ledger account is created to track what they owe or are owed'],
                        ['Register an employee', 'A ledger account (code EMP-0001) is created for their payroll and loans'],
                        ['Post a sale', 'Item stock goes down, the customer’s balance goes up, and sales income is recorded'],
                        ['Post a purchase', 'Item stock goes up and a payable to the supplier is recorded'],
                        ['Post a receipt or payment', 'The party balance and the cash/bank account are updated'],
                        ['Approve a leave request', 'Those days appear in attendance and are honoured by payroll'],
                        ['Post payroll', 'Salary expense, tax withheld, and a payable to each employee are written to the ledger'],
                    ],
                },
                {
                    type: 'warn',
                    label: 'Do not mix modules',
                    text: 'If you use the sales and purchase screens, do not also record the same transactions as raw journal entries. Those modules write stock and party balances themselves, and a duplicate entry causes double-counting.',
                },
            ],
        },
        {
            id: 'calendar-currency',
            number: '6',
            title: 'Calendar and currency',
            blocks: [
                {
                    type: 'p',
                    text: 'If the company calendar is Jalali, all dates display in the Persian calendar and periods are named by the Persian month (for example 1405-05 means Asad 1405). Persian months have different lengths, and the system accounts for this in pro-rata calculations such as a mid-month salary.',
                },
                {
                    type: 'p',
                    text: 'The system is multi-currency. Each cash or bank account has one currency. In documents, the amount is stored both in the document currency and in the company base currency with an exchange rate, so financial reports always read in a single currency.',
                },
                {
                    type: 'tip',
                    label: 'Keep exchange rates current',
                    text: 'If you work in multiple currencies, enter today’s rate from the “Currency rate update” screen so new documents post at the correct rate.',
                },
            ],
        },
        {
            id: 'trash-and-activity',
            number: '7',
            title: 'Trash and the activity log',
            blocks: [
                {
                    type: 'p',
                    text: 'Most records are soft-deleted: they disappear from lists but stay in Trash and can be restored. A record with a posted accounting history is usually not permanently deleted, so entries are never orphaned.',
                },
                {
                    type: 'p',
                    text: 'The activity log shows who created, posted, reversed, or deleted which document and when. When something does not look right, read this log first.',
                },
                {
                    type: 'figure',
                    src: '/images/user-manual/overview/trash-and-activity.png',
                    caption: 'Trash: deleted records by module with a Restore button',
                    hint: 'screenshot of the deleted records page',
                },
            ],
        },
    ],
}
