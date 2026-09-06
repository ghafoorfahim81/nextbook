export default {
    id: 'reports',
    number: '13',
    title: 'Reports & tools guide',
    subtitle: 'Using a report · financial · operational · inventory · HR · activity log · trash · preferences',
    summary:
        'Open reports from the sidebar. Every report has filters and exports to Excel or PDF. This guide covers the most important reports and how to read them.',
    chapters: [
        {
            id: 'how-to-use',
            number: '1',
            title: 'Using a report',
            blocks: [
                {
                    type: 'p',
                    text: 'All reports share the same structure: a filter panel at the top and a result table below.',
                },
                {
                    type: 'table',
                    headers: ['Part', 'Use'],
                    rows: [
                        ['Date range', 'Most reports have a "from date" and "to date". Position reports (like the balance sheet) have one "as of date".'],
                        ['Extra filters', 'Branch, warehouse, party, category, employee, and so on — depending on the report.'],
                        ['Run', 'After setting the filters, run the report to get the result table.'],
                        ['Export', 'The export button gives you the current table as Excel or PDF — exactly what you see on screen.'],
                    ],
                },
                {
                    type: 'figure',
                    src: '/images/user-manual/reports/report-groups.png',
                    caption: 'The reports page with the filter panel and export button',
                    hint: 'screenshot of a report with the filter panel',
                },
                {
                    type: 'warn',
                    label: 'Posted documents only',
                    text: 'Reports show posted documents only. If a document is missing from a report, it is probably still a draft.',
                },
            ],
        },
        {
            id: 'financial-reports',
            number: '2',
            title: 'Financial reports and how to read them',
            blocks: [
                {
                    type: 'table',
                    headers: ['Report', 'What it shows', 'When to use it'],
                    rows: [
                        ['Trial balance', 'The balance of every account and proof that total debit = total credit.', 'The first place to look when a figure seems wrong.'],
                        ['Balance sheet', 'Assets, liabilities, and equity at a date.', 'To see the company’s financial position at month or year end.'],
                        ['Profit and loss', 'Income minus expenses over a period.', 'To see whether the company made a profit or a loss in the period.'],
                        ['General ledger', 'Every movement of one account with a running balance.', 'When you want to know where a figure on the balance sheet came from.'],
                        ['Cash book', 'The ins and outs of one cash or bank account.', 'To reconcile against the bank statement or a cash count.'],
                        ['Day book', 'All documents in date order.', 'A general review of a day’s or week’s financial activity.'],
                    ],
                },
                {
                    type: 'formula',
                    text: 'Profit and loss: sales income − cost of goods sold − operating expenses = net profit',
                },
            ],
        },
        {
            id: 'party-reports',
            number: '3',
            title: 'Party reports',
            blocks: [
                {
                    type: 'table',
                    headers: ['Report', 'What it shows'],
                    rows: [
                        ['Customer / supplier statement', 'All invoices, receipts/payments, and the running balance of one party in date order.'],
                        ['Receivable aging', 'Outstanding receivables from customers by time bucket (0–30, 31–60, 61–90 days and older).'],
                        ['Payable aging', 'Outstanding payables to suppliers by the same buckets.'],
                    ],
                },
                {
                    type: 'tip',
                    label: 'Accurate aging',
                    text: 'The aging report is accurate when receipts have been allocated to specific invoices via the "Settlement" page. If receipts sit on account, all the receivable shows in one column.',
                },
            ],
        },
        {
            id: 'operational-reports',
            number: '4',
            title: 'Operational and inventory reports',
            blocks: [
                {
                    type: 'table',
                    headers: ['Group', 'Reports'],
                    rows: [
                        ['Operations', 'Sales report, purchase report, user activity, today’s sales/purchases.'],
                        ['Expenses', 'Expense report by category.'],
                        ['Cash flow', 'Receipt report, payment report, cash position by currency.'],
                        ['Inventory', 'Current stock, stock movement, stock value, shortage, batch and expiry, fast/slow movers.'],
                        ['Human resources', 'Payroll register, payroll summary, withheld salary tax, attendance summary, leave balance, headcount, contract end, loans.'],
                    ],
                },
            ],
        },
        {
            id: 'activity-log',
            number: '5',
            title: 'The activity log',
            blocks: [
                {
                    type: 'p',
                    text: 'The activity log shows who created, posted, reversed, or deleted which document and when, and which fields changed. It is an audit trail and is not deleted.',
                },
                {
                    type: 'list',
                    items: [
                        'When a figure looks wrong, read this log before posting a correcting document to understand what happened.',
                        'You can filter by user, module, operation type, and date.',
                        'To see the detail of a change, click its row to see the before and after values.',
                    ],
                },
            ],
        },
        {
            id: 'trash',
            number: '6',
            title: 'Trash (deleted records)',
            blocks: [
                {
                    type: 'p',
                    text: 'Deleted records are kept here by module. You can restore them or, with permission, permanently delete them.',
                },
                {
                    type: 'warn',
                    label: 'A record with a posted history',
                    text: 'A record with a posted accounting history usually cannot be permanently deleted — deleting it would orphan accounting entries. You can restore it but not permanently delete it.',
                },
            ],
        },
        {
            id: 'preferences',
            number: '7',
            title: 'User preferences',
            blocks: [
                {
                    type: 'p',
                    text: 'Preferences personalise form behaviour for you and do not affect other users.',
                },
                {
                    type: 'table',
                    headers: ['Preferences group', 'What it controls'],
                    rows: [
                        ['Item management', 'Which fields on the item form are shown. Hide unnecessary fields for faster entry.'],
                        ['Sales and purchases', 'Visible columns in the item table, the numbering pattern, defaults.'],
                        ['Posting options', 'Whether some documents are posted immediately after saving.'],
                        ['Confirm and sound', 'Confirm before save in selected modules, and the alert sound.'],
                    ],
                },
                {
                    type: 'list',
                    items: [
                        'You can export your preferences and import them on another device.',
                        '"Reset" returns one group or all preferences to the default.',
                    ],
                },
                {
                    type: 'note',
                    label: 'The invoice template is not here',
                    text: 'The invoice print template is under Administration → Company so every salesperson prints the same layout. Details in the Administration guide.',
                },
            ],
        },
    ],
}
