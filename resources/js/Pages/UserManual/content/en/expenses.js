export default {
    id: 'expenses',
    number: '7',
    title: 'Expenses guide',
    subtitle: 'Expense categories · recording an expense · attaching a bill · owner drawings',
    summary:
        'The expenses module records the company’s running costs such as rent, fuel, and electricity. Owner drawings are recorded separately because they are not an expense.',
    chapters: [
        {
            id: 'categories',
            number: '1',
            title: 'Expense categories',
            blocks: [
                {
                    type: 'p',
                    text: 'Before recording the first expense, create the categories (office rent, fuel, stationery, utilities, transport). Each category is wired to an expense account in the chart of accounts.',
                },
            ],
        },
        {
            id: 'record-expense',
            number: '2',
            title: 'Recording an expense',
            blocks: [
                {
                    type: 'ol',
                    items: [
                        'Expenses → New expense.',
                        'Select the expense category; the expense account comes automatically.',
                        'Select the paying account (cash/bank) or a party the expense is payable to.',
                        'Enter the amount, date, and description.',
                        'Attach the paper bill or receipt for audit.',
                        '“Save draft” or “Post”.',
                    ],
                },
                {
                    type: 'list',
                    items: [
                        'A multi-line expense (a bill with several items) must respect the debit and credit rules.',
                        'If the expense is payable to a supplier or employee, select the party ledger so it can be settled by a payment later.',
                        'Do not record freight that should sit on the stock cost here; use “Landed cost” in the purchase module.',
                    ],
                },
                {
                    type: 'figure',
                    src: '/images/user-manual/expenses/record-expense.png',
                    caption: 'The expense form with a bill attachment field',
                    hint: 'screenshot of the expense page',
                },
            ],
        },
        {
            id: 'owner-drawings',
            number: '3',
            title: 'Owners and drawings',
            blocks: [
                {
                    type: 'p',
                    text: 'Create an owner record for each partner so their capital and drawings stay separate. A “drawing” is money or goods an owner takes for personal use; it is not a company expense and not a salary.',
                },
                {
                    type: 'list',
                    items: [
                        'Record a drawing from Expenses → Drawing, not from the normal expenses module.',
                        'Write the reason for every drawing or capital movement in the notes; you will need it at year end.',
                        'A drawing has draft/post/reverse like other documents.',
                    ],
                },
            ],
        },
        {
            id: 'reports',
            number: '4',
            title: 'Reports and troubleshooting',
            blocks: [
                {
                    type: 'list',
                    items: [
                        'The expense report shows totals by category and time period.',
                        'If an expense appears twice in profit and loss, you may have posted both an expense and a journal entry. Reverse one.',
                        'If an owner drawing shows up in expenses, it was posted wrong; it should be in the drawings account, not an expense account.',
                    ],
                },
            ],
        },
    ],
}
