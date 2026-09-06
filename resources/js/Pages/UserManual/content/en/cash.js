export default {
    id: 'cash',
    number: '6',
    title: 'Receipts & payments guide',
    subtitle: 'Money received · money paid · account transfer · currency and rate',
    summary:
        'A receipt records money received (usually from a customer) and a payment records money paid out (usually to a supplier). Both update the party balance and the cash/bank account.',
    chapters: [
        {
            id: 'receipts',
            number: '1',
            title: 'Receipts',
            blocks: [
                {
                    type: 'p',
                    text: 'A receipt records money received. Usually from a customer against a sales invoice, but it can be from any party.',
                },
                {
                    type: 'ol',
                    items: [
                        'Receipt → New.',
                        'Select the party (customer). Their prior balance is shown.',
                        'Select the cash or bank account that actually received the money.',
                        'Enter the amount, currency, and if needed the exchange rate.',
                        'For a non-cash deposit, write the cheque number or transfer reference.',
                        '“Save draft” or “Post”. After posting, the customer balance drops and the cash/bank account rises.',
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
            id: 'payments',
            number: '2',
            title: 'Payments',
            blocks: [
                {
                    type: 'p',
                    text: 'A payment records money going out. Usually to a supplier against a purchase invoice.',
                },
                {
                    type: 'ol',
                    items: [
                        'Payment → New.',
                        'Select the party (supplier).',
                        'Select the cash/bank account the money leaves from.',
                        'Enter and check the amount, currency, and rate.',
                        'Post. The supplier balance drops and the cash/bank account drops.',
                    ],
                },
                {
                    type: 'warn',
                    label: 'Wrong currency or rate',
                    text: 'If a receipt or payment is posted with the wrong currency or rate, it will not match the original invoice and the party statement stays wrong. Reverse it and re-enter it with the correct currency.',
                },
            ],
        },
        {
            id: 'transfer',
            number: '3',
            title: 'Account transfer',
            blocks: [
                {
                    type: 'p',
                    text: 'To move money between two of your own accounts (bank to cash, cash to bank), use an account transfer, not a receipt and payment. Details are in the accounting guide, chapter “Account transfer”.',
                },
            ],
        },
        {
            id: 'tips',
            number: '4',
            title: 'Tips and troubleshooting',
            blocks: [
                {
                    type: 'list',
                    items: [
                        'If the form shows the party’s prior balance, read it as a sanity check before posting.',
                        'Receipts and payments can be printed; the print window opens on the same page.',
                        'To allocate a receipt to specific invoices, use the “Settlement” page in accounting.',
                        'A posted receipt or payment is locked; correction only via reversal.',
                    ],
                },
            ],
        },
    ],
}
