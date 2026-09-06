export default {
    id: 'administration',
    number: '10',
    title: 'Administration & company setup guide',
    subtitle: 'Company · branch & warehouse · currency & unit · invoice template · users & roles',
    summary:
        'The administration module is where you define the company’s master data before daily work: org structure, currencies, units, categories, the invoice template, and users.',
    chapters: [
        {
            id: 'setup-order',
            number: '1',
            title: 'Setup order',
            blocks: [
                {
                    type: 'ol',
                    items: [
                        'Administration → Company: confirm the name, calendar type, base currency, and legal details.',
                        'Create branches and warehouses.',
                        'Add currencies and, if you work in multiple currencies, keep rate updates current.',
                        'Create units of measure, categories, brands, and item sizes.',
                        'Create customer groups and payment terms if needed.',
                        'Create landed-cost categories (freight, customs) before allocating additional costs.',
                        'Enter the chart of accounts and opening balances.',
                        'Set up the invoice template, roles, and users.',
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
            id: 'branch-warehouse',
            number: '2',
            title: 'Branch and warehouse',
            blocks: [
                {
                    type: 'p',
                    text: 'The branch is the place of work selected in the header and it scopes lists and documents. The warehouse is where stock is held; purchase, sale, transfer, and opening always need a specific warehouse. A branch can have several warehouses.',
                },
            ],
        },
        {
            id: 'invoice-template',
            number: '3',
            title: 'Invoice template (company-wide)',
            blocks: [
                {
                    type: 'p',
                    text: 'The printed sales invoice is the same for the whole company; it is not a per-user setting. Open the company page, click “Edit”, then use the “Invoice designer”.',
                },
                {
                    type: 'list',
                    items: [
                        'Ready-made templates 1 to 5 have a preview in the designer.',
                        'You can build a custom template and select one for printing.',
                        'A read-only user can see the designer but changes only open with the Edit button on the company page.',
                    ],
                },
                {
                    type: 'note',
                    label: 'A shared template',
                    text: 'Every salesperson prints with the same company template. A change on the company page changes everyone’s printout.',
                },
            ],
        },
        {
            id: 'currency-unit',
            number: '4',
            title: 'Currency and unit of measure',
            blocks: [
                {
                    type: 'list',
                    items: [
                        'Each cash/bank account has one currency. The company base currency is the basis of every financial report.',
                        'Enter today’s rate from “Currency rate update” so new documents post at the correct rate.',
                        'Units of measure can have conversions defined (1 carton = 12 pieces) so buying by carton and selling by piece stay consistent.',
                    ],
                },
            ],
        },
        {
            id: 'users-roles',
            number: '5',
            title: 'Users and roles',
            blocks: [
                {
                    type: 'p',
                    text: 'Create a user under user management and attach them to a role. Define access on the role, not per person. Give the smallest access needed to do the job.',
                },
                {
                    type: 'tip',
                    label: 'Example: cashier role',
                    text: 'A sales role usually needs sales, receipts, customers, and items — not editing the chart of accounts or posting payroll.',
                },
                {
                    type: 'warn',
                    label: 'Keep super admin limited',
                    text: 'A super admin can see and change everything. Give this role to only one or two trusted people.',
                },
            ],
        },
    ],
}
