export default {
    id: 'reports',
    number: '9',
    title: 'Reports & tools guide',
    subtitle: 'Financial & operational reports · activity log · trash · preferences',
    summary:
        'Open reports from the sidebar. Every report has filters and exports to Excel or PDF. The activity log and trash are the tracking and recovery tools.',
    chapters: [
        {
            id: 'report-groups',
            number: '1',
            title: 'Report groups',
            blocks: [
                {
                    type: 'table',
                    headers: ['Group', 'Sample reports'],
                    rows: [
                        ['Financial', 'Trial balance, balance sheet, profit and loss, general ledger, cash book, day book, journal book.'],
                        ['Cash flow', 'Receipt report, payment report, cash position by currency.'],
                        ['Parties', 'Customer and supplier statements, receivable and payable aging.'],
                        ['Operations', 'Sales report, purchase report, user activity.'],
                        ['Expenses', 'Expense report by category.'],
                        ['Inventory', 'Stock, movement, value, shortage, batch, expiry, fast/slow movers.'],
                        ['Human resources', 'Payroll register, attendance, leave balance, headcount, contract end, loans.'],
                        ['Management', 'Today’s sales/purchases/stock and similar summaries.'],
                    ],
                },
                {
                    type: 'figure',
                    src: '/images/user-manual/reports/report-groups.png',
                    caption: 'The reports page with filters and an export button',
                    hint: 'screenshot of a report with the filter panel',
                },
            ],
        },
        {
            id: 'activity-log',
            number: '2',
            title: 'The activity log',
            blocks: [
                {
                    type: 'p',
                    text: 'The activity log shows who created, posted, reversed, or deleted which document and when, and what changed. When a figure looks wrong, read this log before posting a correcting document.',
                },
            ],
        },
        {
            id: 'trash',
            number: '3',
            title: 'Trash (deleted records)',
            blocks: [
                {
                    type: 'p',
                    text: 'Deleted records are kept here by module. You can restore them or, with permission, permanently delete them. A record with a posted accounting history is usually not permanently deleted.',
                },
            ],
        },
        {
            id: 'preferences',
            number: '4',
            title: 'User preferences',
            blocks: [
                {
                    type: 'p',
                    text: 'Preferences personalise form behaviour for you: visible columns, numbering, confirm-before-save, sounds, and add-ons.',
                },
                {
                    type: 'list',
                    items: [
                        'Item preferences hide unnecessary fields so entry is faster.',
                        'The sales and purchase tabs control columns and numbering; set them before daily work.',
                        'Posting options can post some documents immediately after saving.',
                        'Confirm-before-save asks you in the modules you switch it on for.',
                    ],
                },
                {
                    type: 'note',
                    label: 'The invoice template is not here',
                    text: 'The invoice print template is under Administration → Company so every salesperson prints the same layout.',
                },
            ],
        },
    ],
}
