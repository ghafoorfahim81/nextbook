export default {
    badge: 'NextBook — Financial & Administrative System',
    title: 'User Manual',
    subtitle: 'Inventory · Sales · Purchases · Accounting · Human Resources',
    version: 'Complete staff guide · Version 1.0 · September 2026',
    howToRead:
        'If you are new, start with Getting started and How the system works, then follow Daily workflows. Use the table of contents or search to jump to one module. Each chapter can be read on its own.',
    chapters: [
        {
            id: 'getting-started',
            number: '1',
            title: 'Getting started',
            blocks: [
                { type: 'h3', text: 'What NextBook is' },
                {
                    type: 'p',
                    text: 'NextBook is a multi-branch business system for Afghan companies. It keeps inventory, sales, purchases, cash, expenses, double-entry accounts, and human resources in one place. Every signed-in user works inside one company and one branch at a time.',
                },
                { type: 'h3', text: 'Sign in and choose language' },
                {
                    type: 'ol',
                    items: [
                        'Open the system and sign in with your email and password.',
                        'Use the language switcher in the header to choose English, Dari, or Pashto. Dates follow your company calendar (Gregorian or Jalali).',
                        'If you belong to more than one branch, pick the branch from the header. Lists and new documents stay inside that branch.',
                    ],
                },
                {
                    type: 'note',
                    label: 'Company is required',
                    text: 'A user without a company cannot open the main screens. Ask an administrator to attach you to the company and give you a role.',
                },
                { type: 'h3', text: 'Home and Dashboard' },
                {
                    type: 'p',
                    text: 'Home is the landing page after login. It includes shortcuts and tools such as currency exchange and unit conversion. Dashboard shows live totals for sales, purchases, cash, and other key figures. What you see depends on your permissions.',
                },
                { type: 'h3', text: 'Your profile menu' },
                {
                    type: 'table',
                    headers: ['Menu', 'What it does'],
                    rows: [
                        ['Account', 'Name, photo, password, two-factor login, and other sessions.'],
                        ['Notifications', 'In-app alerts for work that needs your attention.'],
                        ['User Manual', 'This guide. It stays inside the application — it is not a PDF.'],
                        ['What\'s New', 'Recent product changes such as draft, post, and reverse.'],
                        ['Logout', 'Ends your session on this device.'],
                    ],
                },
                {
                    type: 'tip',
                    label: 'Tip',
                    text: 'Use the header search to jump to a customer, supplier, item, or document without opening the sidebar.',
                },
            ],
        },
        {
            id: 'how-it-works',
            number: '2',
            title: 'How the system works',
            blocks: [
                { type: 'h3', text: 'Draft, post, and reverse' },
                {
                    type: 'p',
                    text: 'Sales, purchases, receipts, payments, journal entries, account transfers, expenses, drawings, and item transfers follow the same life cycle.',
                },
                {
                    type: 'flow',
                    steps: ['Draft — save without committing the books', 'Post — write the ledger and move stock', 'Reverse — create a mirrored correction'],
                },
                {
                    type: 'list',
                    items: [
                        'A draft can be edited or deleted. Stock on a draft sale is reserved, not taken.',
                        'Posting writes accounting lines and, for inventory items, real stock movement. Posted documents are locked.',
                        'If a posted document is wrong, reverse it and give a reason. The system creates the opposite entry. History is never silently edited.',
                    ],
                },
                {
                    type: 'warn',
                    label: 'Do not edit posted work',
                    text: 'There is no “fix the old invoice” button. Reverse the document, then enter a new correct draft and post it.',
                },
                { type: 'h3', text: 'Permissions and roles' },
                {
                    type: 'p',
                    text: 'Menus and buttons appear only when your role allows them. Preparing a payroll or a leave request is not the same permission as approving or posting it. Ask for the smallest role that matches your job.',
                },
                { type: 'h3', text: 'Print stays on this page' },
                {
                    type: 'p',
                    text: 'Invoice, receipt, and payment print opens the printer dialog on the screen you are already on. The system does not send you to a new browser tab.',
                },
                { type: 'h3', text: 'Trash and activity' },
                {
                    type: 'p',
                    text: 'Most records are soft-deleted. Open Trash to restore them. Activity logs show who created, posted, or reversed a document. A record that already has posted accounting history usually cannot be permanently removed.',
                },
            ],
        },
        {
            id: 'administration',
            number: '3',
            title: 'Administration and company setup',
            blocks: [
                { type: 'h3', text: 'Set these up before daily work' },
                {
                    type: 'ol',
                    items: [
                        'Open Administration → Company. Confirm name, calendar type, and business details.',
                        'Create Branches and Warehouses (stores).',
                        'Add Currencies and, if you trade in more than one, keep Rate Updates current.',
                        'Create Categories, Brands, Sizes, and Unit Measures used on items.',
                        'Create Customer Groups and Payment Terms if you group customers or offer credit.',
                        'Create Landed Cost Categories (freight, customs, and similar) before you allocate extra purchase costs.',
                    ],
                },
                { type: 'h3', text: 'Invoice format (company-wide)' },
                {
                    type: 'p',
                    text: 'Printed sales invoices use one format for the whole company. The format is not a personal preference. Open Company, click Edit, then use Invoice Designer.',
                },
                {
                    type: 'list',
                    items: [
                        'Built-in themes (Format 1–5) show a preview in the designer.',
                        'You can also save custom formats and mark one as the printing format.',
                        'View-only users can look at the designer; only Edit on the Company page unlocks changes.',
                    ],
                },
                {
                    type: 'note',
                    label: 'Shared format',
                    text: 'Every cashier and salesperson prints with the same company invoice. Changing it on Company updates print for all users.',
                },
                { type: 'h3', text: 'Branches and warehouses' },
                {
                    type: 'p',
                    text: 'A branch is the working location in the header. A warehouse (store) is where stock lives. Purchases, sales, transfers, and openings always ask for the correct store.',
                },
            ],
        },
        {
            id: 'accounting',
            number: '4',
            title: 'Accounting',
            blocks: [
                { type: 'h3', text: 'Chart of accounts' },
                {
                    type: 'p',
                    text: 'The chart of accounts is the list of general-ledger accounts. Pick the account type first. Only Cash/Bank accounts show the Opening section, which is for first-time migration — not for later corrections.',
                },
                {
                    type: 'tip',
                    label: 'Numbering',
                    text: 'Use a clear range such as 1xxx Assets, 2xxx Liabilities, 3xxx Equity, 4xxx Income, 5xxx Expenses so reports stay readable.',
                },
                {
                    type: 'warn',
                    label: 'After go-live',
                    text: 'Do not keep changing openings. Use Journal Entries for adjustments.',
                },
                { type: 'h3', text: 'Account transfers' },
                {
                    type: 'p',
                    text: 'Move money between two cash or bank accounts without creating income or expense. From and To cannot be the same account. Confirm currency and rate, and write a clear remark for reconciliation.',
                },
                { type: 'h3', text: 'Journal classes and journal entries' },
                {
                    type: 'p',
                    text: 'Journal classes group similar manual entries. A journal entry is a balanced voucher: total debit must equal total credit before you can save. Use ledger and bill-number fields when the entry belongs to a customer or supplier.',
                },
                {
                    type: 'note',
                    label: 'Keep modules consistent',
                    text: 'Do not post sales or purchases as raw journals if you already use the Sales and Purchase screens. Those modules already write the correct stock and party balances.',
                },
            ],
        },
        {
            id: 'inventory',
            number: '5',
            title: 'Inventory',
            blocks: [
                { type: 'h3', text: 'Items' },
                {
                    type: 'p',
                    text: 'An item is a product or service you buy or sell. Inventory items change on-hand quantity. Non-inventory items are used for services and charges.',
                },
                {
                    type: 'table',
                    headers: ['Field', 'Why it matters'],
                    rows: [
                        ['SKU', 'Unique code for search, barcodes, and avoiding duplicates.'],
                        ['Asset / Income / Cost accounts', 'Stock value, sales income, and cost of goods sold.'],
                        ['Min / Max stock', 'Used for low-stock and over-stock reports.'],
                        ['Batch / Expiry', 'Turn on for medicines and dated goods. Once on, it cannot be turned off.'],
                        ['Costing', 'Batch/expiry items use FIFO. Selling pulls the oldest lot first.'],
                    ],
                },
                { type: 'h3', text: 'Fast entry and fast opening' },
                {
                    type: 'p',
                    text: 'Fast Entry creates many items on one screen. Fill name and measure first; empty rows are ignored. Fast Opening sets starting quantities by store. Use it only for migration. After you go live, use purchases, transfers, and adjustments.',
                },
                { type: 'h3', text: 'Barcode print' },
                {
                    type: 'p',
                    text: 'Open Barcode Print, select items, and print labels. Scan those codes later on sale and purchase screens.',
                },
                { type: 'h3', text: 'Item transfers' },
                {
                    type: 'p',
                    text: 'Move stock from one store to another. From and To must be different. For batch items, keep the same batch and expiry so reports stay consistent. Draft, post, and reverse work the same as other documents.',
                },
                { type: 'h3', text: 'Stock adjustments' },
                {
                    type: 'p',
                    text: 'Use an adjustment when a count does not match the system (damage, loss, found stock). Choose the store, reason, and direction (in or out). Posting changes quantity and value.',
                },
                { type: 'h3', text: 'Pricing' },
                {
                    type: 'p',
                    text: 'Pricing lets you review and update selling prices in bulk so cashiers do not type a new price on every invoice.',
                },
                { type: 'h3', text: 'Landed costs' },
                {
                    type: 'p',
                    text: 'Freight, customs, and other extra costs belong on the purchase, not as a loose expense, when they change the true cost of stock.',
                },
                {
                    type: 'ol',
                    items: [
                        'Select one or more purchase orders. Their items load automatically and cannot be rewritten here.',
                        'Choose an allocation method, or pick Manual and type each item’s share.',
                        'The allocated lines must add up to the extra cost. Posting is blocked until they match.',
                        'Posting adds the share to the purchase stock movement and recalculates average cost.',
                    ],
                },
            ],
        },
        {
            id: 'ledgers',
            number: '6',
            title: 'Customers and suppliers',
            blocks: [
                {
                    type: 'p',
                    text: 'Customers and suppliers are ledgers — the parties you sell to and buy from. Keep names unique. Opening (old) balance is for migration only; after go-live, balances come from sales, purchases, receipts, and payments.',
                },
                {
                    type: 'list',
                    items: [
                        'Fill phone and address so invoices and follow-up calls are accurate.',
                        'Assign a customer group or payment term when you use credit limits or due dates.',
                        'Open the party’s show page to see the statement, print, or record a receipt or payment.',
                    ],
                },
                {
                    type: 'tip',
                    label: 'Employees are also ledgers',
                    text: 'Saving an employee creates a linked ledger named “Name (CODE)”. Salary, advances, and loans post against that ledger.',
                },
            ],
        },
        {
            id: 'sales',
            number: '7',
            title: 'Sales',
            blocks: [
                {
                    type: 'p',
                    text: 'A sale reduces stock (for inventory items) and increases what the customer owes. Always pick the correct customer and store.',
                },
                {
                    type: 'flow',
                    steps: ['Sale quotation', 'Sale order', 'Sale (invoice)', 'Sale return'],
                },
                {
                    type: 'table',
                    headers: ['Document', 'When to use it'],
                    rows: [
                        ['Quotation', 'Price offer. Does not take stock or post the ledger until you convert it.'],
                        ['Order', 'Confirmed demand. Useful when delivery or invoicing comes later.'],
                        ['Sale', 'The invoice. Posting takes stock and writes receivable and income.'],
                        ['Return', 'Customer sends goods back. Posting restores stock and reduces the receivable.'],
                    ],
                },
                {
                    type: 'list',
                    items: [
                        'Check quantity, measure, price, discount, and tax before posting.',
                        'Print uses the company invoice format from Administration → Company.',
                        'Print stays on the current page.',
                    ],
                },
                {
                    type: 'warn',
                    label: 'Wrong customer or store',
                    text: 'A sale posted to the wrong party or warehouse cannot be quietly edited. Reverse it, then enter the correct sale.',
                },
            ],
        },
        {
            id: 'purchases',
            number: '8',
            title: 'Purchases',
            blocks: [
                {
                    type: 'p',
                    text: 'A purchase increases stock and what you owe the supplier. Pick the supplier and the store that will receive the goods.',
                },
                {
                    type: 'flow',
                    steps: ['Purchase quotation', 'Purchase order', 'Purchase', 'Purchase return'],
                },
                {
                    type: 'list',
                    items: [
                        'Confirm item measures. A wrong unit distorts quantity and valuation.',
                        'After extra import costs arrive, use Landed Costs against the purchase order.',
                        'A purchase return sends goods back and reduces the payable.',
                    ],
                },
                {
                    type: 'note',
                    label: 'Opening stock',
                    text: 'Do not use purchases to fake opening balances. Use Fast Opening (or item openings) during setup, then use real purchases after go-live.',
                },
            ],
        },
        {
            id: 'cash',
            number: '9',
            title: 'Receipts and payments',
            blocks: [
                { type: 'h3', text: 'Receipts' },
                {
                    type: 'p',
                    text: 'A receipt records money received — usually from a customer. Choose the cash or bank account that actually received the money. Use cheque or reference fields for non-cash deposits.',
                },
                { type: 'h3', text: 'Payments' },
                {
                    type: 'p',
                    text: 'A payment records money paid — usually to a supplier. Choose the paying cash or bank account. Double-check currency and rate so the party statement stays correct.',
                },
                {
                    type: 'tip',
                    label: 'Old balance',
                    text: 'When the form shows the party’s previous balance, use it as a check before you post.',
                },
                {
                    type: 'warn',
                    label: 'Currency mistakes',
                    text: 'A receipt or payment in the wrong currency or rate will not match the invoice. Reverse it and enter it again.',
                },
            ],
        },
        {
            id: 'expenses',
            number: '10',
            title: 'Expenses',
            blocks: [
                {
                    type: 'p',
                    text: 'Create Expense Categories first (rent, fuel, utilities). Then record an Expense against the right accounts. Attach the bill or receipt for audit.',
                },
                {
                    type: 'list',
                    items: [
                        'Use a ledger when the expense is payable to a supplier or employee.',
                        'Multi-line expenses must still follow debit and credit rules.',
                        'Do not put freight that belongs on stock here if you intend to use Landed Cost.',
                    ],
                },
            ],
        },
        {
            id: 'owners',
            number: '11',
            title: 'Owners and drawings',
            blocks: [
                {
                    type: 'p',
                    text: 'Create one owner record for each partner so capital and drawings stay separate. A drawing is money or goods taken by an owner — it is not an expense and not a salary.',
                },
                {
                    type: 'note',
                    label: 'Remarks',
                    text: 'Write why the drawing or capital movement happened. You will need that note at year-end.',
                },
            ],
        },
        {
            id: 'hr',
            number: '12',
            title: 'Human resources',
            blocks: [
                {
                    type: 'p',
                    text: 'HR is a chain: hire the person, keep their contract and documents, record attendance and leave, then pay them. Information is entered once and reused.',
                },
                {
                    type: 'table',
                    headers: ['Screen', 'Purpose'],
                    rows: [
                        ['Employees', 'Profile, employment type, and the linked salary ledger.'],
                        ['Contracts', 'Start and end dates, terms, and renewals.'],
                        ['Documents', 'ID copies, contracts, and other files on the employee.'],
                        ['Departments', 'Organisation tree used by roster and payroll scope.'],
                        ['Designations', 'Job titles. Salary structures can attach to a designation.'],
                    ],
                },
                {
                    type: 'list',
                    items: [
                        'Employment type chooses which salary expense account payroll uses (permanent, temporary, contract).',
                        'Set a separation date only when status is resigned, terminated, or retired.',
                        'Deleting an employee hides the ledger; posted history blocks permanent delete.',
                    ],
                },
                {
                    type: 'tip',
                    label: 'Phone and email',
                    text: 'Contact details stay on the employee and are not copied to the ledger, so two people can share a phone number.',
                },
            ],
        },
        {
            id: 'attendance',
            number: '13',
            title: 'Attendance',
            blocks: [
                {
                    type: 'p',
                    text: 'Attendance can be typed on the roster, imported from a device, or clocked by the employee. All three write the same daily record.',
                },
                {
                    type: 'table',
                    headers: ['Screen', 'Purpose'],
                    rows: [
                        ['Roster', 'Pick a date and department, then confirm the pre-filled statuses.'],
                        ['Register', 'History of daily records.'],
                        ['Unmapped punches', 'Device IDs that do not belong to an employee yet.'],
                        ['Devices', 'Attendance terminals and their mappings.'],
                        ['Shifts', 'Working hours used by the roster and calculations.'],
                        ['Holidays', 'Public holidays that leave types can skip.'],
                        ['My attendance', 'Self-service clock-in for the signed-in employee.'],
                    ],
                },
                {
                    type: 'list',
                    items: [
                        'Re-importing the same file is safe; duplicate punches are rejected.',
                        'A day that “needs review” usually has a single punch with no pair. Fix it on the roster.',
                        'Days consumed by a posted payroll are locked. Reverse the payroll before you change those days.',
                    ],
                },
            ],
        },
        {
            id: 'leave',
            number: '14',
            title: 'Leave',
            blocks: [
                {
                    type: 'p',
                    text: 'Create Leave Types first (annual, sick, unpaid). Allocate balances, then staff raise Leave Requests. A request moves draft → pending → approved. Approval writes the days into attendance automatically.',
                },
                {
                    type: 'list',
                    items: [
                        'Day counts come from the leave type (weekends and holidays may be skipped). Do not type the count by hand.',
                        'Pending days are shown but not deducted until someone approves.',
                        'Approval is refused when the balance is short, except unpaid leave, which may go negative.',
                        'Cancelling approved leave removes future days and leaves past days alone.',
                        'Approving is a different permission from raising a request.',
                    ],
                },
            ],
        },
        {
            id: 'payroll',
            number: '15',
            title: 'Payroll',
            blocks: [
                {
                    type: 'ol',
                    items: [
                        'Define Salary Components (basic, allowances, deductions).',
                        'Build Salary Structures and attach them to an employee, designation, or department.',
                        'Set Tax Tables if you withhold wage tax.',
                        'Create a Payroll run, calculate (safe to repeat on a draft), then post.',
                        'Pay the net with Salary Payments.',
                        'Record Loans and advances; payroll can recover instalments automatically.',
                    ],
                },
                {
                    type: 'flow',
                    steps: ['Structure & components', 'Draft payroll & calculate', 'Post payroll', 'Salary payment'],
                },
                {
                    type: 'list',
                    items: [
                        'Leave scope blank to include everyone, or narrow by department or employment type.',
                        'Recalculating a draft replaces its payslips. That is the normal way to fix a structure.',
                        'Posting writes the ledger. After that you reverse; you do not edit.',
                        'A salary payment can be voided but not edited.',
                        'Approving a loan is not the same as paying it out.',
                    ],
                },
                {
                    type: 'warn',
                    label: 'Posted payroll locks attendance',
                    text: 'Correct a day from a posted month only after you reverse that payroll run.',
                },
            ],
        },
        {
            id: 'recruitment',
            number: '16',
            title: 'Recruitment',
            blocks: [
                {
                    type: 'p',
                    text: 'Open a Job Opening, collect Applications, and schedule Interviews. When you hire, create the Employee from the successful applicant so HR, attendance, and payroll can start.',
                },
            ],
        },
        {
            id: 'users',
            number: '17',
            title: 'Users and roles',
            blocks: [
                {
                    type: 'p',
                    text: 'Create users under User Management, then assign a Role. Put permissions on the role, not on every person. Give the least access the job needs.',
                },
                {
                    type: 'tip',
                    label: 'Cashiers',
                    text: 'A sales role usually needs sales, receipts, customers, and items — not chart-of-accounts edits or payroll posting.',
                },
            ],
        },
        {
            id: 'preferences',
            number: '18',
            title: 'Preferences',
            blocks: [
                {
                    type: 'p',
                    text: 'Preferences change how forms behave for you: visible columns, numbering, confirm-before-save, sounds, and plugins. Open Preferences from the sidebar or your profile area.',
                },
                {
                    type: 'list',
                    items: [
                        'Item Management hides unused item fields so data entry is faster.',
                        'Sale and Purchase tabs control columns and numbering. Set them before the team starts daily work.',
                        'Posting options can post some documents immediately after save.',
                        'Confirm-before-save asks you to confirm on the modules you enable.',
                    ],
                },
                {
                    type: 'note',
                    label: 'Invoice theme is not here',
                    text: 'The printed invoice format lives on Administration → Company so every user prints the same layout.',
                },
            ],
        },
        {
            id: 'reports',
            number: '19',
            title: 'Reports, activity, and trash',
            blocks: [
                {
                    type: 'p',
                    text: 'Open Reports from the sidebar. Each report has filters and can be exported. Groups include:',
                },
                {
                    type: 'table',
                    headers: ['Group', 'Examples'],
                    rows: [
                        ['Financial', 'Trial balance, balance sheet, profit and loss, general ledger, cash book, day book, journal book.'],
                        ['Cash flow', 'Receipt report, payment report, cash position by currency.'],
                        ['Party', 'Customer and supplier statements, aged receivables and payables.'],
                        ['Operations', 'Sales report, purchase report, user activity.'],
                        ['Expenses', 'Expense report.'],
                        ['Inventory', 'Stock, movement, valuation, low stock, batch, expiry, fast/slow moving.'],
                        ['HR', 'Payroll register, attendance, leave balances, headcount, contract expiry, loans.'],
                        ['Management', 'Today’s sale/purchase/closing stock and similar summaries.'],
                    ],
                },
                {
                    type: 'p',
                    text: 'Activity Logs show who did what. Trash holds deleted records until you restore or permanently delete them (when allowed).',
                },
            ],
        },
        {
            id: 'workflows',
            number: '20',
            title: 'Daily workflows',
            blocks: [
                { type: 'h3', text: 'A selling day' },
                {
                    type: 'ol',
                    items: [
                        'Confirm the branch and store.',
                        'Create the sale (or convert a quotation/order). Check customer, items, prices, and discounts.',
                        'Post the sale.',
                        'Print the invoice if the customer needs a copy.',
                        'When money arrives, create a Receipt against that customer and the correct cash/bank account, then post.',
                    ],
                },
                { type: 'h3', text: 'A buying day' },
                {
                    type: 'ol',
                    items: [
                        'Create or convert the purchase. Check supplier, store, quantities, and units.',
                        'Post the purchase so stock and the payable update.',
                        'If freight or customs arrived, post a Landed Cost.',
                        'When you pay, create a Payment and post it.',
                    ],
                },
                { type: 'h3', text: 'Month-end payroll' },
                {
                    type: 'ol',
                    items: [
                        'Finish the attendance register and approve leave for the period.',
                        'Confirm salary structures and open loans.',
                        'Create the payroll, calculate, review payslips, then post.',
                        'Record salary payments.',
                    ],
                },
                { type: 'h3', text: 'New company first week' },
                {
                    type: 'ol',
                    items: [
                        'Company, branches, warehouses, currencies, units, categories.',
                        'Chart of accounts and cash/bank openings.',
                        'Items (Fast Entry) and opening stock (Fast Opening).',
                        'Customers, suppliers, and opening party balances.',
                        'Invoice format on Company. Roles and users. Preferences for columns and posting.',
                    ],
                },
                {
                    type: 'tip',
                    label: 'When something looks wrong',
                    text: 'Check the document status first (draft, posted, reversed). Then check branch, store, party, currency, and rate. Use the party statement and activity log before you enter a second document.',
                },
            ],
        },
    ],
}
