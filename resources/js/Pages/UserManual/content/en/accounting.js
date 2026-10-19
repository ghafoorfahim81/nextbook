export default {
    id: 'accounting',
    number: '5',
    title: 'Accounting module guide',
    subtitle: 'Chart of accounts · openings · journal entry · account transfer · settlement · financial reports',
    summary:
        'The accounting module is the company’s double-entry general ledger. Most entries come automatically from sales, purchases, and cash; journal entries and account transfers cover what no other module handles.',
    chapters: [
        {
            id: 'purpose',
            number: '1',
            title: 'Purpose and the double-entry principle',
            blocks: [
                {
                    type: 'p',
                    text: 'NextBook accounting is double-entry: every financial event hits at least two accounts and total debit always equals total credit. This guarantees the books are always balanced and the reports are trustworthy.',
                },
                {
                    type: 'p',
                    text: 'The key point: you do not write most accounting entries by hand. When you post a sale, purchase, receipt, or payroll, the system creates the accounting entry itself. The accounting module is for seeing the result and for cases no other module covers (depreciation, period-end adjustments, corrections).',
                },
                {
                    type: 'formula',
                    text: 'Assets = Liabilities + Equity   ·   total debit = total credit',
                },
            ],
        },
        {
            id: 'chart-of-accounts',
            number: '2',
            title: 'The chart of accounts',
            blocks: [
                {
                    type: 'p',
                    text: 'The chart of accounts is the list of every general-ledger account. Each account has an “account type” that decides which financial statement it appears in and which side (debit or credit) it sits on.',
                },
                {
                    type: 'table',
                    headers: ['Account class', 'Natural balance', 'Example'],
                    rows: [
                        ['Asset', 'Debit', 'Cash, bank, item stock, receivable from customers'],
                        ['Liability', 'Credit', 'Payable to suppliers, salary payable, tax payable'],
                        ['Equity', 'Credit', 'Owner capital, retained earnings'],
                        ['Income', 'Credit', 'Sales income, service income'],
                        ['Expense', 'Debit', 'Cost of goods sold, rent, salary, electricity'],
                    ],
                },
                {
                    type: 'tip',
                    label: 'Tidy numbering',
                    text: 'Use a clear range: 1xxx assets, 2xxx liabilities, 3xxx equity, 4xxx income, 5xxx expense. This keeps reports readable and makes adding a new account easy.',
                },
                {
                    type: 'warn',
                    label: 'Openings only in cash/bank accounts',
                    text: 'Only cash and bank accounts have an “opening balance” field, and only for transferring initial balances at setup. For any later correction, use a journal entry.',
                },
                {
                    type: 'figure',
                    src: '/images/user-manual/accounting/chart-of-accounts.png',
                    caption: 'Chart of accounts — the account tree by class',
                    hint: 'screenshot of the chart of accounts page',
                },
            ],
        },
        {
            id: 'opening-balances',
            number: '3',
            title: 'Opening balances',
            blocks: [
                {
                    type: 'p',
                    text: 'When you start with NextBook, you must enter the company’s existing balances:',
                },
                {
                    type: 'table',
                    headers: ['Balance type', 'Where it is entered'],
                    rows: [
                        ['Cash and bank', 'The opening balance field in the chart of accounts'],
                        ['Item stock', 'Fast opening in the inventory module'],
                        ['Receivable from customers', 'The opening balance in each customer record'],
                        ['Payable to suppliers', 'The opening balance in each supplier record'],
                        ['Other accounts (fixed assets, loans)', 'A single opening journal entry'],
                    ],
                },
                {
                    type: 'warn',
                    label: 'After real work begins',
                    text: 'Once real documents start, do not keep changing opening balances. Make every correction with a separate journal entry so the history is preserved.',
                },
            ],
        },
        {
            id: 'journal-entries',
            number: '4',
            title: 'Journal class and journal entry',
            blocks: [
                {
                    type: 'p',
                    text: 'A “journal class” groups similar entries (adjustment entry, depreciation entry, period-close entry). A “journal entry” is a manual posting to the general ledger.',
                },
                {
                    type: 'ol',
                    items: [
                        'Accounting → Journal entry → New.',
                        'Enter the journal class, date, and description.',
                        'Add lines: each line is one account and one debit or credit amount.',
                        'If a line relates to a customer or supplier, also fill in the party ledger and bill number.',
                        'Make sure total debit equals total credit; otherwise posting is not possible.',
                        '“Save draft” or “Post”.',
                    ],
                },
                {
                    type: 'warn',
                    label: 'A journal entry does not replace the modules',
                    text: 'If you use the sales and purchase screens, do not record the same transactions as raw journal entries. A journal entry is for cases with no dedicated module.',
                },
                {
                    type: 'figure',
                    src: '/images/user-manual/accounting/journal-entries.png',
                    caption: 'The journal entry form — debit and credit lines with a balance indicator',
                    hint: 'screenshot of the journal entry page',
                },
            ],
        },
        {
            id: 'account-transfer',
            number: '5',
            title: 'Account transfer',
            blocks: [
                {
                    type: 'p',
                    text: 'An account transfer moves money between two cash or bank accounts without creating income or expense (for example, a bank withdrawal for the cash box).',
                },
                {
                    type: 'list',
                    items: [
                        'The source and destination accounts cannot be the same.',
                        'If the two accounts have different currencies, enter each side’s amount and the exchange rate separately.',
                        'Record a bank transfer fee as a separate expense or in the fee field.',
                    ],
                },
            ],
        },
        {
            id: 'settlement',
            number: '6',
            title: 'Settlement (matching open documents)',
            blocks: [
                {
                    type: 'p',
                    text: 'When you have several invoices and several receipts for one customer, “settlement” specifies which receipt paid which invoice. This is needed for an accurate receivable aging report.',
                },
                {
                    type: 'ol',
                    items: [
                        'Accounting → Settlement → select the party.',
                        'The system shows open documents (unpaid invoices and unallocated receipts).',
                        'Allocate receipts to invoices.',
                        'Review the preview and post.',
                    ],
                },
            ],
        },
        {
            id: 'financial-reports',
            number: '7',
            title: 'Financial reports',
            blocks: [
                {
                    type: 'table',
                    headers: ['Report', 'What it shows'],
                    rows: [
                        ['Trial balance', 'The balance of every account and proof the ledger is balanced.'],
                        ['Balance sheet', 'Assets, liabilities, and equity at a specific date.'],
                        ['Profit and loss', 'Income minus expenses over a time period.'],
                        ['General ledger', 'All movements of one specific account.'],
                        ['Cash book', 'The ins and outs of one cash or bank account.'],
                        ['Day book', 'All documents in date order.'],
                        ['Journal book', 'Manual journal entries.'],
                    ],
                },
                {
                    type: 'tip',
                    label: 'Trial balance first',
                    text: 'When a report does not look right, open the trial balance first. If it is balanced, the problem is in account classification, not in posting.',
                },
            ],
        },
        {
            id: 'glossary',
            number: '8',
            title: 'Glossary of accounting terms',
            blocks: [
                {
                    type: 'table',
                    headers: ['Term', 'Meaning'],
                    rows: [
                        ['Debit', 'The left side of an account. Assets and expenses increase with a debit.'],
                        ['Credit', 'The right side of an account. Liabilities, equity, and income increase with a credit.'],
                        ['General ledger', 'The set of all accounts and their movements.'],
                        ['Journal class', 'A label for grouping similar journal entries.'],
                        ['Settlement', 'Allocating a receipt/payment to a specific invoice.'],
                        ['Period-end adjustments', 'Entries such as depreciation and prepayments recorded before closing a period.'],
                        ['Retained earnings', 'Prior years’ undistributed profit added to equity.'],
                    ],
                },
            ],
        },
        {
            id: 'example',
            number: '9',
            title: 'Worked example: recording monthly depreciation',
            blocks: [
                {
                    type: 'p',
                    text: 'Noor Co. has a vehicle worth 120,000 AFN depreciated over 5 years; monthly depreciation is 2,000 AFN. No module records this automatically, so we make a journal entry.',
                },
                {
                    type: 'table',
                    headers: ['Account', 'Debit', 'Credit'],
                    rows: [
                        ['Depreciation expense', '2,000', '—'],
                        ['Accumulated depreciation — vehicle', '—', '2,000'],
                    ],
                },
                {
                    type: 'ol',
                    items: [
                        'New journal entry, class “Depreciation entry”, date the month end.',
                        'Line 1: Depreciation expense, debit 2,000.',
                        'Line 2: Accumulated depreciation — vehicle, credit 2,000.',
                        'It is balanced. Post it. Repeat every month.',
                    ],
                },
            ],
        },
        {
            id: 'troubleshooting',
            number: '10',
            title: 'Troubleshooting',
            blocks: [
                {
                    type: 'table',
                    headers: ['Problem', 'Likely cause and fix'],
                    rows: [
                        ['Journal entry will not post', 'Total debit and credit are not equal. Re-add the lines.'],
                        ['Trial balance is not balanced', 'Rare; usually a draft or half-posted document. Contact support.'],
                        ['Profit and loss does not match expectations', 'An account may be in the wrong class (an expense recorded as an asset). Check the account type in the chart of accounts.'],
                        ['Customer balance in the balance sheet differs from their statement', 'Open documents are not settled. Use the settlement page.'],
                    ],
                },
            ],
        },
    ],
}
