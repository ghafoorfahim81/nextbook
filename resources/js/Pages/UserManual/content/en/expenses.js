export default {
    id: 'expenses',
    number: '7',
    title: 'Expenses & owner drawings guide',
    subtitle: 'Expense categories · multi-line expense · bill attachment · owners & drawings',
    summary:
        'The expenses module records the company’s running costs such as rent, fuel, and electricity. Owner drawings are recorded separately because they are not an expense and do not affect company profit.',
    chapters: [
        {
            id: 'purpose',
            number: '1',
            title: 'Purpose of this module',
            blocks: [
                {
                    type: 'p',
                    text: 'An expense is any money the company spends to run its operations that does not add value to an asset. An expense directly reduces company profit and appears in the profit and loss statement.',
                },
                {
                    type: 'table',
                    headers: ['Is an expense', 'Is not an expense'],
                    rows: [
                        ['Office rent, electricity, fuel, stationery, transport', 'Buying goods for resale (that is a purchase)'],
                        ['Service fees, repairs and maintenance', 'Import freight that sits on the goods cost (landed cost)'],
                        ['Admin staff salaries (through the payroll module)', 'Money withdrawn by an owner (owner drawing)'],
                    ],
                },
            ],
        },
        {
            id: 'categories',
            number: '2',
            title: 'Expense categories',
            blocks: [
                {
                    type: 'p',
                    text: 'Before recording the first expense, create the categories under Administration. Each category is wired to an expense account in the chart of accounts, so when you pick the category the account comes automatically.',
                },
                {
                    type: 'list',
                    items: [
                        'Examples: office rent, fuel, stationery and printing, utilities, transport and freight, hospitality, bank charges.',
                        'Do not make categories too granular; 8 to 15 categories are enough for useful reporting.',
                        'An inactive category does not appear on the new expense form, but its old expenses remain.',
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
                    type: 'p',
                    text: 'A draft expense is only saved. Posting writes the accounting entry (expense account debited, paying account credited). Reversing creates a mirror entry.',
                },
                {
                    type: 'tip',
                    label: 'Post immediately after save',
                    text: 'In preferences you can enable posting an expense immediately after saving.',
                },
            ],
        },
        {
            id: 'record-expense',
            number: '4',
            title: 'Field reference and recording an expense',
            blocks: [
                {
                    type: 'table',
                    headers: ['Field', 'Description'],
                    rows: [
                        ['Number', 'The system assigns it.'],
                        ['Date', 'The day the expense occurred.'],
                        ['Expense category', 'Determines the expense account.'],
                        ['Paying account', 'The cash/bank account the money leaves from, or the ledger of a party the expense is payable to.'],
                        ['Amount', 'The expense amount.'],
                        ['Notes', 'The expense description for audit.'],
                        ['Attachment', 'A scan or photo of the paper bill / receipt.'],
                    ],
                },
                {
                    type: 'ol',
                    items: [
                        'Expenses → New expense.',
                        'Pick the category and date.',
                        'Enter the paying account and amount.',
                        'Attach the paper bill.',
                        '“Save draft” or “Post”.',
                    ],
                },
                {
                    type: 'figure',
                    src: '/images/user-manual/expenses/record-expense.png',
                    caption: 'The expense form with the bill attachment field',
                    hint: 'screenshot of the expense page',
                },
            ],
        },
        {
            id: 'multi-line',
            number: '5',
            title: 'Multi-line expense',
            blocks: [
                {
                    type: 'p',
                    text: 'If one bill has several items (an invoice with both stationery and a repair, say), you can record several expense lines in one document. Each line has its own category and amount.',
                },
                {
                    type: 'warn',
                    label: 'Balance',
                    text: 'The total of the expense lines must equal the amount paid. The system checks this balance before posting.',
                },
            ],
        },
        {
            id: 'payable-expense',
            number: '6',
            title: 'An expense payable to a party',
            blocks: [
                {
                    type: 'p',
                    text: 'If you are not paying the expense in cash right now (an electricity bill paid next month, say), select the supplier or related party’s ledger instead of a cash account. The system records the expense and a payable to that party. Later, when you pay, settle the payable from the "Payment" module.',
                },
            ],
        },
        {
            id: 'owners-drawings',
            number: '7',
            title: 'Owners and drawings',
            blocks: [
                {
                    type: 'p',
                    text: 'Create an owner record for each partner so their capital and drawings stay separate. A "drawing" is money or goods an owner takes for personal use.',
                },
                {
                    type: 'table',
                    headers: ['Item', 'Accounting account', 'Effect'],
                    rows: [
                        ['Owner investment', 'Owner capital account', 'Capital and cash go up'],
                        ['Owner drawing', 'Owner drawing account', 'Cash goes down and drawing goes up; company profit does not change'],
                    ],
                },
                {
                    type: 'list',
                    items: [
                        'Record a drawing from Expenses → Drawing, not the normal expenses module.',
                        'A drawing has draft/post/reverse like other documents.',
                        'Write the reason for every drawing or capital movement in the notes; you need it at year end and profit distribution.',
                    ],
                },
                {
                    type: 'warn',
                    label: 'A drawing is not an expense',
                    text: 'If you record an owner drawing in an expense account, company profit is shown wrong (too low) and the calculated tax is wrong.',
                },
            ],
        },
        {
            id: 'reports',
            number: '8',
            title: 'Reports and troubleshooting',
            blocks: [
                {
                    type: 'table',
                    headers: ['Report', 'What it shows'],
                    rows: [
                        ['Expense report', 'Total expenses by category and time period.'],
                        ['Profit and loss', 'The effect of total expenses on period profit.'],
                        ['Expense account ledger', 'All movements of one specific expense account.'],
                    ],
                },
                {
                    type: 'table',
                    headers: ['Problem', 'Likely cause and fix'],
                    rows: [
                        ['An expense appears twice in profit and loss', 'You probably posted both an expense and a manual journal entry. Reverse one.'],
                        ['An owner drawing shows up in expenses', 'It was posted wrong; it should be in the drawing account. Reverse the expense and record the drawing correctly.'],
                        ['Import freight is in P&L but item cost is low', 'Freight should have been landed cost, not a plain expense. See the purchase guide.'],
                    ],
                },
            ],
        },
    ],
}
