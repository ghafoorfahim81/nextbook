export default {
    id: 'getting-started',
    number: '0',
    title: 'Getting started with NextBook',
    subtitle: 'Basics · items · customers & suppliers · opening balances · checks · staff · first day',
    summary: 'A first-week guide for a new business: enter your items, customers and suppliers, then your opening balances, so you can start working on real transactions.',
    chapters: [
        {
            id: 'step-1',
            number: '1',
            title: 'The big picture',
            blocks: [
                {
                    type: 'p',
                    text: 'NextBook keeps stock, sales, purchases, cash and accounting in one place. You enter each fact once and the other parts of the system use it.',
                },
                {
                    type: 'p',
                    text: 'Setting up takes four stages, in this order:',
                },
                {
                    type: 'table',
                    headers: ['Stage', 'What you do', 'Why it comes here'],
                    rows: [
                        [
                            'A. Check the basics',
                            'Company, currencies, warehouse',
                            'Everything else depends on them',
                        ],
                        [
                            'B. Build your lists',
                            'Items, customers, suppliers',
                            'You can\'t record a balance for something that doesn\'t exist yet',
                        ],
                        [
                            'C. Enter opening balances',
                            'What you already own and owe on day one',
                            'Without them your reports start from zero',
                        ],
                        [
                            'D. Check and go live',
                            'Compare with your own books, add staff',
                            'Catches mistakes while they are cheap to fix',
                        ],
                    ],
                },
                {
                    type: 'tip',
                    label: 'Pick a start date and keep to it',
                    text: 'Choose a day, such as the first of the month or the first day of the year. Every opening balance should be what you had at the end of the day before. Then enter every real transaction from that day on.',
                },
                {
                    type: 'h3',
                    text: 'What the system already did for you',
                },
                {
                    type: 'p',
                    text: 'When your company was created, NextBook prepared these, so you don\'t have to:',
                },
                {
                    type: 'list',
                    items: [
                        'A chart of accounts (cash, bank, receivables, payables, inventory, sales income, and so on)',
                        'Currencies, with your base currency marked',
                        'Units of measure (piece, kilogram, litre, carton, and others), item categories and brands',
                        'A Cash customer, for walk-in sales with no named customer',
                        'Expense categories',
                        'An open financial period for the current year',
                        'A main branch with a warehouse',
                        'Your login, with full (super admin) access',
                    ],
                },
                {
                    type: 'p',
                    text: 'Review these lists and add to them. You don\'t need to start from scratch.',
                },
            ],
        },
        {
            id: 'step-2',
            number: '2',
            title: 'Stage A: check the basics',
            blocks: [
                {
                    type: 'h3',
                    text: '2.1 Company',
                },
                {
                    type: 'p',
                    text: 'Open Administration → Company (or the company page from the header).',
                },
                {
                    type: 'p',
                    text: 'Check:',
                },
                {
                    type: 'list',
                    items: [
                        'Calendar type, Gregorian or Jalali. All dates in the system follow it.',
                        'Base currency, the currency your reports are shown in.',
                        'Business type, which decides which fields appear on the item form. A pharmacy sees batch and expiry. A mobile or computer shop sees variants and serial numbers.',
                        'Legal details: name, address, phone and tax number. They are printed on your invoices.',
                    ],
                },
                {
                    type: 'warn',
                    label: 'Decide the calendar and base currency now',
                    text: 'Changing them after real transactions exist is difficult and risky.',
                },
                {
                    type: 'h3',
                    text: '2.2 Currencies and rates',
                },
                {
                    type: 'p',
                    text: 'Open Administration → Currency.',
                },
                {
                    type: 'list',
                    items: [
                        'Your base currency has a rate of 1.',
                        'If you trade in other currencies (USD, PKR and so on), open Currency Rate Updates and enter today\'s rate for each one.',
                        'The other currencies start with no rate. You can\'t enter an opening balance in a foreign currency until its rate is set, because the system needs it to convert to your base currency.',
                    ],
                },
                {
                    type: 'h3',
                    text: '2.3 Warehouse',
                },
                {
                    type: 'p',
                    text: 'Open Administration → Warehouse.',
                },
                {
                    type: 'list',
                    items: [
                        'Your branch already has a main warehouse. Rename it if you like.',
                        'Add more if your stock is in several places, for example "Shop" and "Godown".',
                        'Every stock quantity you enter belongs to a specific warehouse.',
                    ],
                },
            ],
        },
        {
            id: 'step-3',
            number: '3',
            title: 'Stage B: build your lists',
            blocks: [
                {
                    type: 'h3',
                    text: '3.1 Items',
                },
                {
                    type: 'h4',
                    text: 'Prepare the groupings first (optional, 5 minutes)',
                },
                {
                    type: 'p',
                    text: 'Under Administration, look at Category, Brand and Unit Measure. Add anything missing, such as a category called "Building materials" or a unit called "Sack". Picking from these lists later is quicker than typing.',
                },
                {
                    type: 'p',
                    text: 'If you sell the same product to different kinds of customers at different prices, also create Customer Groups (for example "Wholesale" and "Retail").',
                },
                {
                    type: 'h4',
                    text: 'Option 1: many items at once with Fast Entry (recommended for a first load)',
                },
                {
                    type: 'p',
                    text: 'Open Inventory → Fast Entry.',
                },
                {
                    type: 'ol',
                    items: [
                        'You get a table of empty rows. Fill in one item per row: name and unit of measure are required. Category, brand, purchase price and sale price are optional.',
                        'Items get codes automatically.',
                        'Empty rows are ignored. Add more rows when you need them.',
                        'Click save.',
                    ],
                },
                {
                    type: 'p',
                    text: 'If you already know the opening quantity, you can fill in the quantity, warehouse and cost on the same row. That enters the opening stock at the same time (see 4.2). To keep things simple, you can leave them blank and use Fast Opening in Stage C.',
                },
                {
                    type: 'h4',
                    text: 'Option 2: one item at a time',
                },
                {
                    type: 'p',
                    text: 'Open Inventory → Item → New. The form asks for:',
                },
                {
                    type: 'table',
                    headers: ['Field', 'Notes'],
                    rows: [
                        [
                            'Name and Code',
                            'The code is suggested for you. Both must be unique.',
                        ],
                        [
                            'Item type',
                            'Stockable (you count it) or service (freight, installation: no stock).',
                        ],
                        [
                            'Unit of measure',
                            'The unit you count it in. Can\'t be changed after the first transaction.',
                        ],
                        ['Category', 'Optional but useful for reports.'],
                        [
                            'Asset, income and cost accounts',
                            'Filled in for you. Leave them unless your accountant says otherwise.',
                        ],
                        [
                            'Variants',
                            'Every item has at least one. This is where the sale price, purchase price, barcode and minimum stock are set. If the item comes in sizes or colours, add one row per combination.',
                        ],
                        [
                            'Batch / expiry tracking',
                            'Only for goods with a lot number or expiry date (medicine, food).',
                        ],
                    ],
                },
                {
                    type: 'warn',
                    label: 'Batch tracking is permanent',
                    text: 'Once a transaction has been posted for the item, you can\'t turn it off. Only switch it on for goods that really have lots or expiry dates.',
                },
                {
                    type: 'h4',
                    text: 'Tips',
                },
                {
                    type: 'list',
                    items: [
                        'If you have a barcode scanner, put the barcode on the variant. You can print labels from Inventory → Barcode Print.',
                        'Don\'t worry about getting every field perfect. You can edit an item later. A few fields (unit of measure, item type, batch tracking) lock once the item has posted transactions.',
                        'If you have hundreds of items, start with your top sellers. Add the rest as you meet them. The item picker on every invoice also lets you create a new item on the spot.',
                    ],
                },
                {
                    type: 'h3',
                    text: '3.2 Customers',
                },
                {
                    type: 'p',
                    text: 'Open Ledger → Customer → New.',
                },
                {
                    type: 'table',
                    headers: ['Field', 'Notes'],
                    rows: [
                        ['Name', 'Required. Everything else is optional.'],
                        ['Code', 'Optional. If you use one, it must be unique.'],
                        [
                            'Phone, WhatsApp, email, address, contact person',
                            'Appear on the customer statement.',
                        ],
                        ['Province / country', 'For reports and filters.'],
                        [
                            'Currency',
                            'The currency you normally trade in with this customer.',
                        ],
                        [
                            'Customer group',
                            'Ties the customer to group pricing and discounts.',
                        ],
                        [
                            'Payment term',
                            'The payment window you give them (cash, 7 days, 30 days). The receivable aging report uses it.',
                        ],
                        [
                            'Credit limit',
                            'Switch it on and set the amount you are willing to let this customer owe.',
                        ],
                        ['Discount', 'A standing percentage discount.'],
                        [
                            'Photo and attachments',
                            'Optional, for example a copy of a contract.',
                        ],
                        ['Opening balance', 'What this customer owes you today. See 4.3.'],
                    ],
                },
                {
                    type: 'p',
                    text: 'Use Create to save, or Create & New to save and start the next customer.',
                },
                {
                    type: 'list',
                    items: [
                        'You already have a Cash customer. Use it for walk-in sales so you don\'t have to name every buyer.',
                        'A customer with no sales or receipts can be deleted. Once they have real transactions, deactivate them instead.',
                    ],
                },
                {
                    type: 'h3',
                    text: '3.3 Suppliers',
                },
                {
                    type: 'p',
                    text: 'Open Ledger → Supplier → New. The form is the same as the customer form, and the Opening balance there is what you owe the supplier.',
                },
                {
                    type: 'h3',
                    text: '3.4 Someone who is both a customer and a supplier',
                },
                {
                    type: 'p',
                    text: 'Create them once as a customer and once as a supplier, and keep the two accounts separate. When you want to cancel what each owes the other, use Cash Transactions → Set-off. It cancels the two balances against each other without any cash moving.',
                },
            ],
        },
        {
            id: 'step-4',
            number: '4',
            title: 'Stage C: enter opening balances',
            blocks: [
                {
                    type: 'p',
                    text: 'An opening balance is what you already owned or owed on your start date. There are four kinds, and each one has its own place. Don\'t put them all into one journal entry.',
                },
                {
                    type: 'table',
                    headers: ['What you are entering', 'Where', 'What the system does'],
                    rows: [
                        [
                            'Money in cash boxes and bank accounts',
                            'Chart of Account',
                            'Debits the account',
                        ],
                        [
                            'Stock in the warehouse',
                            'Inventory → Fast Opening (or the item form)',
                            'Debits Inventory Stock',
                        ],
                        [
                            'Customers who owe you',
                            'The Opening balance section of each customer',
                            'Debits Accounts Receivable, and the customer\'s statement starts with this amount',
                        ],
                        [
                            'Suppliers you owe',
                            'The Opening balance section of each supplier',
                            'Credits Accounts Payable, and the supplier\'s statement starts with this amount',
                        ],
                    ],
                },
                {
                    type: 'p',
                    text: 'In every case the other side of the entry goes to a system account called Opening Balance Equity. After all the openings are in, this account shows the difference between what you own and what you owe, which is your starting capital. Your accountant can move it to the proper capital accounts later.',
                },
                {
                    type: 'h3',
                    text: '4.1 Cash and bank',
                },
                {
                    type: 'p',
                    text: 'Open Account → Chart of Account.',
                },
                {
                    type: 'ol',
                    items: [
                        'Your cash accounts already exist: Cash in Hand, Cash in Safe, Cash in Bank, Petty Cash. Open each one.',
                        'If you have several real bank accounts, create one account per bank account. Choose the type Cash or Bank, and name it clearly, for example "Azizi Bank – AFN".',
                        'In the Opening section, choose the currency (and rate, if it isn\'t your base currency), the side (debit for money you hold) and the amount.',
                        'Save.',
                    ],
                },
                {
                    type: 'p',
                    text: 'Delete or deactivate the accounts you don\'t use, so cashiers aren\'t confused by them later.',
                },
                {
                    type: 'h3',
                    text: '4.2 Stock',
                },
                {
                    type: 'p',
                    text: 'Open Inventory → Fast Opening.',
                },
                {
                    type: 'ol',
                    items: [
                        'The table lists every item that has no opening yet.',
                        'For each item you hold, fill in: — Warehouse · Quantity · Cost, the price you paid per unit. This sets the value of your stock. · Batch and expiry date, only for batch-tracked items',
                        'Rows with a quantity of zero are skipped.',
                        'Save.',
                    ],
                },
                {
                    type: 'warn',
                    label: 'Don\'t leave the cost empty',
                    text: 'The quantity is recorded, but no value is posted to accounting, so your stock value will look too low.',
                },
                {
                    type: 'warn',
                    label: 'Once only',
                    text: 'An item that already has an opening disappears from this list, so the same stock can\'t be entered twice. After you start trading, never use it to fix quantities. Use a Stock Adjustment (you\'ll count and correct) or a purchase.',
                },
                {
                    type: 'warn',
                    label: 'Don\'t enter your opening stock as a purchase',
                    text: 'A purchase would also create a payable to a supplier, and you\'d owe money you don\'t owe.',
                },
                {
                    type: 'h3',
                    text: '4.3 Customer balances',
                },
                {
                    type: 'p',
                    text: 'For each customer who owes you money:',
                },
                {
                    type: 'ol',
                    items: [
                        'Open Ledger → Customer, open the customer, and click Edit.',
                        'In Opening balance, find the currency row and enter the amount, and the rate if it isn\'t your base currency. Add a short remark such as "Balance carried from old ledger".',
                        'Save.',
                    ],
                },
                {
                    type: 'p',
                    text: 'If a customer owes you in two currencies, fill in two rows. Rows left empty are ignored, and each currency can be used only once per customer.',
                },
                {
                    type: 'p',
                    text: 'The amount is what they owe you.',
                },
                {
                    type: 'h3',
                    text: '4.4 Supplier balances',
                },
                {
                    type: 'p',
                    text: 'Same as 4.3, on Ledger → Supplier. The amount is what you owe the supplier.',
                },
                {
                    type: 'h3',
                    text: '4.5 Everything else',
                },
                {
                    type: 'p',
                    text: 'Fixed assets (vehicles, shop fittings), loans and owner\'s capital have no screen of their own. Your accountant should record them with a single Account → Journal Entry dated on your start date.',
                },
                {
                    type: 'note',
                    label: 'Note',
                    text: 'If you\'re not sure whether an item belongs in an opening journal entry, ask. Stock, customer and supplier balances must not go in the journal entry, because they would be counted twice.',
                },
            ],
        },
        {
            id: 'step-5',
            number: '5',
            title: 'Stage D: check your numbers',
            blocks: [
                {
                    type: 'p',
                    text: 'Before the first real sale, spend 15 minutes comparing the system with your own records.',
                },
                {
                    type: 'p',
                    text: 'Open Reports and check these:',
                },
                {
                    type: 'table',
                    headers: ['Report', 'What to look for'],
                    rows: [
                        [
                            'Trial balance',
                            'Total debit equals total credit. It always should.',
                        ],
                        [
                            'Stock / inventory reports',
                            'Every item has the quantity you counted, and the total stock value matches your estimate.',
                        ],
                        [
                            'Receivable aging',
                            'The list of customers who owe you matches your old customer ledger. The total matches the opening balances you entered.',
                        ],
                        ['Payable aging', 'The same, for suppliers.'],
                        [
                            'Balance sheet (as of your start date)',
                            'Cash, receivables and stock on the asset side. Payables on the liability side. The difference is in equity.',
                        ],
                    ],
                },
                {
                    type: 'p',
                    text: 'If something is wrong:',
                },
                {
                    type: 'list',
                    items: [
                        'A customer or supplier balance: edit the opening balance on that record. It is simply replaced.',
                        'A stock quantity: open the item and correct its opening, as long as no stock has been issued from it. Once stock has moved, use a Stock Adjustment.',
                        'A cash or bank account: edit the account\'s opening.',
                    ],
                },
                {
                    type: 'p',
                    text: 'Once real transactions begin, stop editing openings. Every later correction should be a visible document (journal entry, stock adjustment or reversal), so the history stays clean.',
                },
            ],
        },
        {
            id: 'step-6',
            number: '6',
            title: 'Add your staff',
            blocks: [
                {
                    type: 'p',
                    text: 'Open User Management.',
                },
                {
                    type: 'ol',
                    items: [
                        'Roles: create a role for each kind of job. For example, Cashier (sales and receipts), Storekeeper (items and stock), Accountant (everything financial).',
                        'Users: add each person, attach them to a role and to their branch.',
                    ],
                },
                {
                    type: 'p',
                    text: 'Give each person the smallest role that covers their work. Keep the super admin role for one or two trusted people. Menus and buttons that a role doesn\'t allow are hidden, so a missing menu usually means that person has no access to it.',
                },
            ],
        },
        {
            id: 'step-7',
            number: '7',
            title: 'Your first real day',
            blocks: [
                {
                    type: 'p',
                    text: 'Now you can trade.',
                },
                {
                    type: 'table',
                    headers: ['I want to…', 'Go to'],
                    rows: [
                        ['Sell something', 'Sale → New'],
                        ['Record money from a customer', 'Cash Transactions → Receipt'],
                        ['Buy goods', 'Purchase → New'],
                        ['Pay a supplier', 'Cash Transactions → Payment'],
                        ['Record rent, electricity, etc.', 'Expense → Expense'],
                        [
                            'Move cash between boxes and banks',
                            'Account → Account Transfers',
                        ],
                        ['Move stock between warehouses', 'Inventory → Item Transfer'],
                        ['Correct a stock count', 'Inventory → Stock Adjustment'],
                    ],
                },
                {
                    type: 'h3',
                    text: 'The one rule to learn: Draft, Post, Reverse',
                },
                {
                    type: 'p',
                    text: 'Almost every document works the same way.',
                },
                {
                    type: 'ol',
                    items: [
                        'Draft: you\'ve saved it, but nothing has changed in stock or accounting yet. You can still edit or delete it.',
                        'Post: the document takes effect. Stock moves, balances change and the document is locked.',
                        'Reverse: to undo a posted document, you reverse it. It stays in the history, and a mirror document cancels it out. Then you enter the correct one.',
                    ],
                },
                {
                    type: 'p',
                    text: 'There is no "edit a posted invoice" button, by design. It keeps your books trustworthy, and it\'s what an auditor or the tax office expects.',
                },
                {
                    type: 'h3',
                    text: 'Handy things to know',
                },
                {
                    type: 'list',
                    items: [
                        'Search box (top of every page): type a customer, item or document number to jump straight to it.',
                        'Branch switcher (header): if you work in more than one branch, make sure you\'re in the right one before you enter anything.',
                        'Language switcher: English, Dari and Pashto.',
                        'Trash (System → Trash): deleted records can be recovered from here.',
                        'Activity logs: show who did what and when.',
                        'User Manual (profile menu): a detailed guide to every module, inside the app.',
                    ],
                },
            ],
        },
        {
            id: 'step-8',
            number: '8',
            title: 'Setup checklist',
            blocks: [
                {
                    type: 'p',
                    text: 'Tick these off as you go.',
                },
                {
                    type: 'h4',
                    text: 'Basics',
                },
                {
                    type: 'list',
                    items: [
                        '[ ] Calendar type and base currency confirmed',
                        '[ ] Legal details and logo entered (they print on invoices)',
                        '[ ] Exchange rates entered for every currency you use',
                        '[ ] Warehouse(s) named correctly',
                    ],
                },
                {
                    type: 'h4',
                    text: 'Lists',
                },
                {
                    type: 'list',
                    items: [
                        '[ ] Categories, brands and units checked',
                        '[ ] Items entered (Fast Entry or item form)',
                        '[ ] Prices set on each item\'s variant',
                        '[ ] Customers entered',
                        '[ ] Suppliers entered',
                    ],
                },
                {
                    type: 'h4',
                    text: 'Opening balances (as of your start date)',
                },
                {
                    type: 'list',
                    items: [
                        '[ ] Cash and bank accounts',
                        '[ ] Stock, with cost',
                        '[ ] Customer balances',
                        '[ ] Supplier balances',
                        '[ ] Other assets and loans (journal entry from your accountant)',
                    ],
                },
                {
                    type: 'h4',
                    text: 'Check',
                },
                {
                    type: 'list',
                    items: [
                        '[ ] Trial balance is balanced',
                        '[ ] Stock list matches your count',
                        '[ ] Receivable and payable totals match your old ledger',
                    ],
                },
                {
                    type: 'h4',
                    text: 'Go live',
                },
                {
                    type: 'list',
                    items: [
                        '[ ] Roles and users created',
                        '[ ] Invoice print format checked with a test sale (delete or reverse it afterwards)',
                        '[ ] First real sale posted',
                    ],
                },
            ],
        },
        {
            id: 'step-9',
            number: '9',
            title: 'Common questions',
            blocks: [
                {
                    type: 'h4',
                    text: 'I made a mistake in an opening balance. Can I fix it?',
                },
                {
                    type: 'p',
                    text: 'Yes, until you start trading. Edit the customer, supplier or account, and the opening is replaced. For stock, open the item and fix its opening. The system locks an opening once stock has been issued from it. After you start trading, use stock adjustments and journal entries instead.',
                },
                {
                    type: 'h4',
                    text: 'I started selling before entering my opening stock.',
                },
                {
                    type: 'p',
                    text: 'Fast Opening is meant for the very beginning, and a stock opening is dated the day you enter it. Talk to your accountant before adding opening stock to a business that has already traded, so the dates and the cost are handled correctly.',
                },
                {
                    type: 'h4',
                    text: 'Why can\'t I see a menu my colleague sees?',
                },
                {
                    type: 'p',
                    text: 'Your role doesn\'t include it. Ask an administrator to adjust your role under User Management → Roles.',
                },
                {
                    type: 'h4',
                    text: 'What is "Opening Balance Equity"?',
                },
                {
                    type: 'p',
                    text: 'The balancing side of every opening entry. When you\'ve entered all your openings, its balance is your starting capital. Your accountant will normally move it to your capital accounts.',
                },
                {
                    type: 'h4',
                    text: 'What should I do if the system says a record "is in use"?',
                },
                {
                    type: 'p',
                    text: 'Something has already been posted against it, such as an item that was sold or a customer with invoices. Edit what you can, or deactivate it instead of deleting it.',
                },
            ],
        },
    ],
}
