export default {
    id: 'cash',
    number: '6',
    title: 'Receipts & payments guide',
    subtitle: 'Receipt · payment · settlement · currency & rate · matching to invoices',
    summary:
        'A receipt records money received (usually from a customer) and a payment records money going out (usually to a supplier). Both update the party balance and the cash/bank account, and can be allocated to specific invoices.',
    chapters: [
        {
            id: 'purpose',
            number: '1',
            title: 'Purpose of this module',
            blocks: [
                {
                    type: 'p',
                    text: 'This module records cash flow between the company and its parties. Receipts and payments do not create income or expense themselves — they just move money and settle the party balance. Income and expense were already recorded when the sales or purchase invoice was posted.',
                },
                {
                    type: 'table',
                    headers: ['Document', 'Direction', 'Effect on accounts'],
                    rows: [
                        ['Receipt', 'Money in', 'Customer balance down, cash/bank account up'],
                        ['Payment', 'Money out', 'Supplier balance down, cash/bank account down'],
                    ],
                },
                {
                    type: 'note',
                    label: 'Receipts and payments with an employee',
                    text: 'Paying an employee’s salary is done from the "Pay payroll" menu, not this module. But a receipt from an employee (e.g. returning a cash advance) can be recorded here if the employee ledger appears in the party list.',
                },
            ],
        },
        {
            id: 'prerequisites',
            number: '2',
            title: 'Prerequisites',
            blocks: [
                {
                    type: 'list',
                    items: [
                        'At least one cash account and one bank account defined in the chart of accounts (account type: cash or bank).',
                        'The party (customer or supplier) already registered.',
                        'If you work in multiple currencies, today’s exchange rate up to date.',
                    ],
                },
            ],
        },
        {
            id: 'lifecycle',
            number: '3',
            title: 'Lifecycle',
            blocks: [
                {
                    type: 'flow',
                    steps: ['Draft', 'Posted', 'Reversed'],
                },
                {
                    type: 'table',
                    headers: ['State', 'What it means'],
                    rows: [
                        ['Draft', 'Saved but the party balance and cash account are not affected.'],
                        ['Posted', 'The party balance and cash/bank account are updated and, if allocated, the related invoices are marked paid. Locked.'],
                        ['Reversed', 'A mirror document cancels the effect and the allocations are released.'],
                    ],
                },
            ],
        },
        {
            id: 'receipt-fields',
            number: '4',
            title: 'Receipt field reference',
            blocks: [
                {
                    type: 'table',
                    headers: ['Field', 'Description'],
                    rows: [
                        ['Party', 'The customer or party the money is received from. Their prior balance is shown.'],
                        ['Date', 'The day money is received.'],
                        ['Cash/bank account', 'The account that actually received the money.'],
                        ['Currency and rate', 'The receipt currency. Must be compatible with the cash/bank account currency. If it differs from the base currency, enter the exchange rate.'],
                        ['Amount', 'The amount received in the receipt currency.'],
                        ['Cheque / reference number', 'For a non-cash deposit (cheque, remittance, bank transfer). Used as the voucher number.'],
                        ['Notes', 'The receipt description, visible on the party statement.'],
                        ['Allocation to invoices', 'Optional. Specifies which open invoices this money pays. If left blank, the money sits "on account" against the party’s total balance.'],
                    ],
                },
                {
                    type: 'figure',
                    src: '/images/user-manual/cash/receipts.png',
                    caption: 'The receipt form',
                    hint: 'screenshot of the receipt page',
                },
            ],
        },
        {
            id: 'payment-fields',
            number: '5',
            title: 'Payment field reference',
            blocks: [
                {
                    type: 'p',
                    text: 'The payment fields mirror the receipt. The key differences:',
                },
                {
                    type: 'table',
                    headers: ['Field', 'Description'],
                    rows: [
                        ['Party', 'The supplier or party the money is paid to.'],
                        ['Paying account', 'The cash/bank account the money leaves from.'],
                        ['Allocation to invoices', 'Which open purchase invoices this payment settles.'],
                    ],
                },
            ],
        },
        {
            id: 'step-by-step',
            number: '6',
            title: 'Step by step: recording a receipt',
            blocks: [
                {
                    type: 'ol',
                    items: [
                        'Receipt → New.',
                        'Select the party and read their prior balance as a sanity check.',
                        'Select the cash/bank account that received the money.',
                        'Enter the currency, rate, and amount. For a cheque, write the cheque number.',
                        'If needed, select the party’s open invoices so this money is allocated to them.',
                        '“Save draft” or “Post”.',
                        'Print the receipt if needed; the print window opens on the same page.',
                    ],
                },
            ],
        },
        {
            id: 'settlement',
            number: '7',
            title: 'Settlement — matching money to invoices',
            blocks: [
                {
                    type: 'p',
                    text: 'When you have several open invoices and several unallocated receipts for a party, use the "Settlement" page in accounting to specify which receipt paid which invoice. This is needed for an accurate "aging" report.',
                },
                {
                    type: 'table',
                    headers: ['Allocation method', 'What it means'],
                    rows: [
                        ['FIFO (automatic)', 'The money pays the oldest open invoice first, then the next.'],
                        ['Manual', 'You specify the amount for each invoice yourself.'],
                    ],
                },
            ],
        },
        {
            id: 'rules',
            number: '8',
            title: 'Rules the system enforces',
            blocks: [
                {
                    type: 'list',
                    items: [
                        'The amount must be greater than zero.',
                        'The receipt/payment currency must be compatible with the cash/bank account currency; otherwise an exchange rate is required.',
                        'The total of allocations cannot exceed the receipt amount.',
                        'A posted document is locked; correction only via reversal.',
                        'Reversing a receipt releases all its allocations and reopens the related invoices.',
                    ],
                },
            ],
        },
        {
            id: 'glossary',
            number: '9',
            title: 'Glossary',
            blocks: [
                {
                    type: 'table',
                    headers: ['Term', 'Meaning'],
                    rows: [
                        ['On account', 'Money received or paid but not allocated to a specific invoice, sitting only against the party’s total balance.'],
                        ['Allocation', 'Linking a receipt/payment to one or more specific invoices.'],
                        ['Settlement', 'The process of matching a party’s open documents.'],
                        ['Reference number', 'The cheque, remittance, or bank transfer number recorded for tracking.'],
                    ],
                },
            ],
        },
        {
            id: 'example',
            number: '10',
            title: 'Worked example',
            blocks: [
                {
                    type: 'p',
                    text: '"Ahmad Store" has two open invoices: invoice 101 for 3,000 and invoice 105 for 5,000 AFN. Today they bring 6,000 AFN in cash.',
                },
                {
                    type: 'ol',
                    items: [
                        'New receipt → Party: Ahmad Store → Account: Kabul cash → Amount: 6,000.',
                        'Allocation: invoice 101 in full (3,000) and invoice 105 partial (3,000).',
                        'Post. Invoice 101 becomes "paid", invoice 105 "partially paid" with 2,000 remaining. Ahmad’s total balance is 2,000 AFN.',
                    ],
                },
            ],
        },
        {
            id: 'reports',
            number: '11',
            title: 'Reports and troubleshooting',
            blocks: [
                {
                    type: 'table',
                    headers: ['Report', 'What it shows'],
                    rows: [
                        ['Receipt report', 'All receipts by date, party, or account.'],
                        ['Payment report', 'All payments by date, party, or account.'],
                        ['Cash position', 'The balance of every cash and bank account by currency.'],
                        ['Party statement', 'Invoices, receipts, and the balance in date order.'],
                    ],
                },
                {
                    type: 'table',
                    headers: ['Problem', 'Likely cause and fix'],
                    rows: [
                        ['Party balance does not match expectations', 'A receipt posted on the wrong party, or allocations are incomplete. Read the statement and the activity log.'],
                        ['Receipt does not match the invoice', 'The currency or rate was probably wrong. Reverse it and re-enter with the correct currency.'],
                        ['Cannot allocate a receipt', 'The invoices may still be drafts or belong to another branch.'],
                    ],
                },
            ],
        },
    ],
}
