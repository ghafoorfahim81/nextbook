export default {
    id: 'sales',
    number: '2',
    title: 'Sales module guide',
    subtitle: 'Quotation · order · sales invoice · sales return · printing',
    summary:
        'The sales module records everything you sell to a customer, from a price quote to the final invoice and returns. Posting a sale reduces stock and records the customer receivable and the income.',
    chapters: [
        {
            id: 'purpose',
            number: '1',
            title: 'Purpose of this module',
            blocks: [
                {
                    type: 'p',
                    text: 'The sales module manages the full selling chain — from the moment you quote a customer a price to when goods are delivered and money is received. Its core document is the sales invoice; the others (quotation and order) lead up to it, and the sales return corrects it.',
                },
                {
                    type: 'p',
                    text: 'When a sales invoice is posted, three things happen at once: stock of stockable items leaves the warehouse, the customer becomes a debtor of the company, and sales income is recorded in accounting. This is why choosing the right customer and warehouse before posting is critical.',
                },
                {
                    type: 'table',
                    headers: ['User', 'What they do'],
                    rows: [
                        ['Salesperson / cashier', 'Create the invoice, pick items, apply discounts, print the invoice'],
                        ['Sales manager', 'Approve large orders, review discounts, read the sales report'],
                        ['Accountant', 'Check recorded income, chase customer receivables, record receipts'],
                    ],
                },
            ],
        },
        {
            id: 'prerequisites',
            number: '2',
            title: 'Prerequisites — set these up before your first sale',
            blocks: [
                {
                    type: 'ol',
                    items: [
                        'Branch and warehouse: make sure at least one warehouse is defined for your branch.',
                        'Items: every item you sell must be registered in the inventory module, with an income account and a sale price.',
                        'Customers: register regular customers under “Customers & suppliers”. For walk-in cash sales you can create one generic customer called “Cash customer”.',
                        'Invoice template: the print template is set under Administration → Company and is the same for the whole company.',
                        'Optional: define customer groups, payment terms, and tax rates in advance.',
                    ],
                },
                {
                    type: 'note',
                    label: 'Cash customer',
                    text: 'If most of your sales are cash with no named customer, create a customer called “Cash customer” and post all walk-in sales against it. Its statement becomes long, but no receivable is left outstanding because you also record the receipt straight away.',
                },
            ],
        },
        {
            id: 'document-types',
            number: '3',
            title: 'Sales documents and their order',
            blocks: [
                {
                    type: 'flow',
                    steps: ['Sales quotation', 'Sales order', 'Sales invoice', 'Sales return'],
                },
                {
                    type: 'table',
                    headers: ['Document', 'When to use it', 'Effect on stock and accounts'],
                    rows: [
                        ['Sales quotation', 'To give a customer a price offer. Creates no commitment.', 'None. Does not take stock or write to the ledger.'],
                        ['Sales order', 'When the customer has confirmed but delivery or invoicing is later.', 'No accounting effect. Records the sales commitment only.'],
                        ['Sales invoice', 'The main sales document. Goods are delivered.', 'Post: stock down, receivable and income written.'],
                        ['Sales return', 'The customer returns all or part of the goods.', 'Post: stock returns, receivable and income reduced.'],
                    ],
                },
                {
                    type: 'p',
                    text: 'You do not always have to start from a quotation or order. For a simple over-the-counter sale, create a sales invoice directly. Quotations and orders help with larger deals or a supply chain, and can be converted to an invoice with one click without re-entering the items.',
                },
                {
                    type: 'tip',
                    label: 'Converting a document',
                    text: 'When you create a sales invoice, if the customer has an open order the system offers to load it. Items, quantity, and price come from the order and you only review and post.',
                },
            ],
        },
        {
            id: 'lifecycle',
            number: '4',
            title: 'The sales invoice lifecycle',
            blocks: [
                {
                    type: 'flow',
                    steps: ['Draft', 'Posted', 'Reversed'],
                },
                {
                    type: 'table',
                    headers: ['State', 'What it means', 'What you can do'],
                    rows: [
                        ['Draft', 'The invoice is saved but stock and accounts are not affected. Stock for these items is only “reserved” so it is not sold on another invoice.', 'Edit, delete, post'],
                        ['Posted', 'Stock has left the warehouse, the customer receivable and income are written. The invoice is locked.', 'Print, reverse'],
                        ['Reversed', 'A mirror document has been created that returns stock and accounts to their previous state.', 'Print, view the mirror document'],
                    ],
                },
                {
                    type: 'warn',
                    label: 'Wrong customer or warehouse',
                    text: 'A posted invoice on the wrong customer or warehouse cannot be quietly fixed. Reverse it and enter and post the correct invoice. If it is still a draft from the same day, just edit the draft.',
                },
            ],
        },
        {
            id: 'field-reference',
            number: '5',
            title: 'Sales invoice field reference',
            blocks: [
                { type: 'h4', text: 'a) Invoice header' },
                {
                    type: 'table',
                    headers: ['Field', 'Description'],
                    rows: [
                        ['Invoice number', 'The system numbers it automatically. The numbering pattern is configurable in preferences.'],
                        ['Date', 'The sale date. Income and the stock reduction post on this date.'],
                        ['Customer', 'The sales party. Their balance increases after posting. If they have a prior balance, the form shows it.'],
                        ['Warehouse', 'The warehouse goods leave from. It must have enough stock (unless negative-stock sales are allowed in preferences).'],
                        ['Currency and rate', 'The invoice currency. If it differs from the company base currency, enter the exchange rate.'],
                        ['Payment terms', 'The customer’s payment window (cash, 7 days, 30 days). Used for the receivable aging report.'],
                    ],
                },
                { type: 'h4', text: 'b) Item lines' },
                {
                    type: 'table',
                    headers: ['Field', 'Description'],
                    rows: [
                        ['Item', 'Pick from the list or scan the barcode.'],
                        ['Quantity and unit', 'The number sold. The unit must match the item’s registered unit.'],
                        ['Unit price', 'The sale price. It comes from the item default and can be changed if your role allows it.'],
                        ['Discount', 'As an amount or a percentage on that line.'],
                        ['Tax', 'If the item or customer has a tax rate, it is calculated automatically.'],
                        ['Batch / expiry', 'For batch-tracked items. The system suggests the oldest batch first (FIFO).'],
                    ],
                },
                {
                    type: 'note',
                    label: 'Some discounts fill themselves in',
                    text: 'If a discount rule covers the item, the line’s discount box is filled in for you and the rule’s name appears beneath it. Type over the figure to overrule it — the rules then leave that line alone. Clearing the box means no discount and is saved as such. Discount rules are set up under Inventory → Discount rules.',
                },
                { type: 'h4', text: 'c) Invoice footer' },
                {
                    type: 'list',
                    items: [
                        'Whole-invoice discount: on top of line discounts, you can discount the grand total.',
                        'Additional charges: such as freight billed to the customer.',
                        'Amount received: if you are taking money now, enter it here so a receipt is created automatically.',
                        'Notes: an internal note or text printed on the invoice.',
                    ],
                },
                {
                    type: 'figure',
                    src: '/images/user-manual/sales/field-reference.png',
                    caption: 'The sales invoice form — header, item table, and footer totals',
                    hint: 'screenshot of the create sale page',
                },
            ],
        },
        {
            id: 'step-by-step',
            number: '6',
            title: 'Step by step: posting a sale',
            blocks: [
                {
                    type: 'ol',
                    items: [
                        'From the sidebar, open Sales → New sale.',
                        'Check the branch and warehouse at the top of the page.',
                        'Select the customer. Read their prior balance as a sanity check.',
                        'Add items one by one: item, quantity, price, and a discount if needed. For batch items, pick the batch.',
                        'Review the footer: subtotal, tax, whole-invoice discount, and amount payable.',
                        'If money is received now, enter the amount received and the cash/bank account.',
                        'Click “Save draft” to review later, or “Post” if everything is correct.',
                        'After posting, print the invoice if needed. The print window opens on the same page.',
                    ],
                },
                {
                    type: 'tip',
                    label: 'Post immediately after save',
                    text: 'In preferences you can enable posting the invoice immediately after saving. Handy for a fast sales counter, but you lose the chance to review the draft.',
                },
            ],
        },
        {
            id: 'returns',
            number: '7',
            title: 'Sales returns',
            blocks: [
                {
                    type: 'p',
                    text: 'When a customer returns goods, create a sales return — do not reverse the original invoice. A sales return can cover only part of an invoice.',
                },
                {
                    type: 'ol',
                    items: [
                        'Open Sales → Sales return → New.',
                        'Select the customer and, where possible, the original invoice. The system shows the returnable items.',
                        'Enter the returned quantity for each item.',
                        'Select the warehouse the goods return to.',
                        'Post. Stock goes up and the customer receivable and income drop by the same amount.',
                    ],
                },
                {
                    type: 'warn',
                    label: 'Returning batch items',
                    text: 'For batch-tracked items, keep the same original batch and expiry so the stock value stays correct.',
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
                        'Enough stock: if the warehouse does not have enough, posting is refused — unless negative-stock sales are allowed in preferences.',
                        'Customer and warehouse in the same branch: you cannot sell from another branch’s warehouse.',
                        'Price below cost: depending on preferences, it may warn or block.',
                        'Currency and rate: must be compatible with the cash/bank account that receives the money.',
                        'A posted invoice is locked: the only way to correct it is a reversal.',
                        'Discount above the allowed limit: if your role has a discount cap, more than that is refused.',
                    ],
                },
            ],
        },
        {
            id: 'printing',
            number: '9',
            title: 'Printing and export',
            blocks: [
                {
                    type: 'p',
                    text: 'Invoice printing uses the company print template set under Administration → Company. That template is the same for every salesperson; it is not a per-user setting.',
                },
                {
                    type: 'list',
                    items: [
                        'The print window opens on the same page; the system does not send you to a new browser tab.',
                        'You can export the invoice to Excel or PDF.',
                        'Ready-made templates 1 to 5 and custom templates are available in the invoice designer.',
                    ],
                },
                {
                    type: 'figure',
                    src: '/images/user-manual/sales/printing.png',
                    caption: 'Sales invoice print preview with the company template',
                    hint: 'screenshot of the invoice print window',
                },
            ],
        },
        {
            id: 'glossary',
            number: '10',
            title: 'Glossary of confusing fields',
            blocks: [
                {
                    type: 'table',
                    headers: ['Term', 'Meaning'],
                    rows: [
                        ['Quotation', 'A price offer with no commitment. It has no effect until converted.'],
                        ['Sales order', 'A confirmed sales commitment that has not been invoiced yet.'],
                        ['Stock reservation', 'While an invoice is a draft, its item stock is set aside so it is not sold on another invoice, but it has not yet left real stock.'],
                        ['Payment terms', 'The window you give the customer to pay. The basis of the receivable aging report.'],
                        ['Aging', 'A report showing how many days each customer’s receivable has been outstanding.'],
                        ['FIFO', 'First in, first out. In sales, the oldest purchased batch is sold first.'],
                    ],
                },
            ],
        },
        {
            id: 'example',
            number: '11',
            title: 'Worked example: a day of sales',
            blocks: [
                {
                    type: 'p',
                    text: 'Noor Co. today sells 10 cartons of oil to “Ahmad Store” at 1,200 AFN per carton and takes 8,000 AFN in cash; the rest stays as a receivable.',
                },
                {
                    type: 'ol',
                    items: [
                        'New sale → Customer: Ahmad Store → Warehouse: Main store.',
                        'Item line: Oil, quantity 10 cartons, price 1,200 → line total 12,000 AFN.',
                        'Amount received: 8,000 AFN into the “Kabul cash” account.',
                        'Post. The system: oil stock down 10 cartons, sales income 12,000 recorded, cash up 8,000, Ahmad Store receivable now 4,000 AFN.',
                        'Print the invoice and give it to the customer.',
                        'A few days later, when they bring the remaining 4,000, create a receipt against Ahmad Store from Sales → Receipt.',
                    ],
                },
                {
                    type: 'formula',
                    text: 'Amount payable = sum of lines − whole-invoice discount + tax + additional charges',
                },
            ],
        },
        {
            id: 'reports',
            number: '12',
            title: 'Sales-related reports',
            blocks: [
                {
                    type: 'table',
                    headers: ['Report', 'What it shows'],
                    rows: [
                        ['Sales report', 'Sales by date, customer, item, or salesperson.'],
                        ['Customer statement', 'All invoices, receipts, and the balance of one customer.'],
                        ['Receivable aging', 'Outstanding receivables by time bucket (0–30, 31–60 days, ...).'],
                        ['Fast / slow movers', 'Which items sell quickly and which sit in the warehouse.'],
                        ['Profit and loss', 'The effect of sales income and cost of goods on period profit.'],
                    ],
                },
            ],
        },
        {
            id: 'troubleshooting',
            number: '13',
            title: 'Troubleshooting',
            blocks: [
                {
                    type: 'table',
                    headers: ['Problem', 'Likely cause and fix'],
                    rows: [
                        ['“Not enough stock” when posting', 'Wrong warehouse selected, or stock really is low. Check warehouse stock in inventory.'],
                        ['Sale price cannot be changed', 'Your role is not allowed to change price. Ask an administrator.'],
                        ['Invoice not in the sales report', 'It is probably still a draft. The sales report shows posted documents only.'],
                        ['Customer balance looks wrong', 'Read the customer statement and the activity log. A receipt may be posted on the wrong customer, or an invoice was reversed.'],
                        ['Print shows the old template', 'The template was changed under Administration → Company but the browser cached the old one. Refresh the page.'],
                    ],
                },
            ],
        },
        {
            id: 'keyboard',
            number: '14',
            title: 'Working fast with the keyboard',
            blocks: [
                {
                    type: 'p',
                    text: 'A sale can be entered from start to finish without touching the mouse. The form opens with the cursor on the customer, and Enter carries you through each line in the order you fill it.',
                },
                {
                    type: 'table',
                    headers: ['Key', 'What it does'],
                    rows: [
                        ['Enter on the customer', 'Goes to the barcode / item search box.'],
                        [
                            'Tab on the customer',
                            'Goes to the item field of the first line.',
                        ],
                        [
                            'Enter on the item',
                            'Takes the highlighted item and goes to the variant — or straight to the quantity when the item has only one variant.',
                        ],
                        ['Enter on the variant', 'Goes to the quantity.'],
                        ['Enter on the quantity', 'Goes to the price.'],
                        [
                            'Enter on the price',
                            'Goes to the item field of the next line. A new line is added when there is none.',
                        ],
                        [
                            'Arrow keys',
                            'Move up, down, left and right between the fields of the lines table. They never change a value.',
                        ],
                        [
                            'F2 or Alt+B',
                            'Jumps to the barcode search from anywhere on the form.',
                        ],
                        [
                            'Ctrl+S, then Enter',
                            'Saves. With “confirm before save” on, Ctrl+S opens the confirmation and Enter answers it; with it off, Ctrl+S steps onto the Save button and Enter presses it.',
                        ],
                    ],
                },
                {
                    type: 'note',
                    label: 'Inside a select',
                    text: 'While a list is open, Up and Down walk its options and Enter takes the highlighted one. Press Escape to close the list; the arrows then move between the lines again.',
                },
                {
                    type: 'tip',
                    label: 'Scanning',
                    text: 'Scan a barcode in the barcode box and the item is added as a line; scan it again and the quantity of that line goes up by one. The cursor stays in the box for the next scan. The barcode box is on the edit form as well.',
                },
                {
                    type: 'list',
                    items: [
                        'Number fields do not change when you scroll the mouse wheel over them.',
                        'Number fields on the sale form take no negative figures.',
                        'Enter inside a line never saves the bill — only Ctrl+S followed by Enter does.',
                    ],
                },
            ],
        },
        {
            id: 'payment-and-currency',
            number: '15',
            title: 'Payment, currency, free goods and expiry',
            blocks: [
                {
                    type: 'h4',
                    text: 'a) Sale type and payment',
                },
                {
                    type: 'table',
                    headers: ['Sale type', 'What happens', 'Payment status'],
                    rows: [
                        [
                            'Cash',
                            'The whole bill is received now into the cash/account you pick. The account is required and cannot be left empty.',
                            'Paid',
                        ],
                        [
                            'Credit',
                            'A payment dialog opens: enter how much is received now, the payment method and the cash/bank account. The rest stays on the customer.',
                            'Paid when the full amount is received, Partially paid when part of it is, Unpaid when nothing is',
                        ],
                        [
                            'On loan',
                            'Nothing is received now. The whole bill goes on the customer.',
                            'Unpaid',
                        ],
                    ],
                },
                {
                    type: 'list',
                    items: [
                        'The payment cannot be more than the bill. The dialog shows the bill after the bill discount, what is paid and what remains.',
                        'Only cash and bank accounts are offered for a payment.',
                        'The bill total is worked out by the system from the lines: goods − line discounts − bill discount.',
                        'A percentage bill discount is taken on the value of goods, before line discounts. A fixed bill discount is taken off the bill once.',
                    ],
                },
                {
                    type: 'h4',
                    text: 'b) Currency and rate',
                },
                {
                    type: 'list',
                    items: [
                        'Item prices are kept in the company currency. A bill in another currency shows each price divided by the rate: 10 AFN at a rate of 65 is 0.1538 USD.',
                        'Changing the currency converts every price on the bill, and changing it back gives exactly the original price.',
                        'If a currency has no stored rate, pick the currency first and then type the rate — the prices convert as you type it.',
                        'A price you typed yourself is kept when only the rate of the same currency is changed.',
                    ],
                },
                {
                    type: 'h4',
                    text: 'c) Free goods',
                },
                {
                    type: 'p',
                    text: 'The Free column is for units given away with the sale. They are not charged, but they do leave the warehouse and their cost goes to cost of goods sold. Selling 45 with 5 free takes 50 off the shelf, and the printed invoice shows “45 + 5 Free”.',
                },
                {
                    type: 'h4',
                    text: 'd) Variant, batch and expiry',
                },
                {
                    type: 'list',
                    items: [
                        'The stock shown on a line is the stock of the chosen variant in the chosen warehouse. If that variant has none there, a message says so.',
                        'The batch list shows only the batches of the chosen variant.',
                        'The expiry date is picked from the dates actually in stock, with the quantity of each; it cannot be typed. Leave it empty and the system takes the nearest expiry first.',
                        'When no sale price is set, the proposed price is built on the chosen variant’s own average cost.',
                    ],
                },
            ],
        },
        {
            id: 'costing-returns-orders',
            number: '16',
            title: 'Cost of goods, drafts, returns and orders',
            blocks: [
                {
                    type: 'h4',
                    text: 'a) How the cost of a sale is taken',
                },
                {
                    type: 'table',
                    headers: ['Method', 'Which stock leaves', 'Cost booked'],
                    rows: [
                        [
                            'FIFO',
                            'Nearest expiry first, then the oldest receipt.',
                            'The actual cost of the receipts the goods came from.',
                        ],
                        [
                            'LIFO',
                            'Nearest expiry first, then the newest receipt.',
                            'The actual cost of the receipts the goods came from.',
                        ],
                        [
                            'Weighted average',
                            'Nearest expiry first.',
                            'The average cost of the variant (or of the item when the variant has none).',
                        ],
                    ],
                },
                {
                    type: 'list',
                    items: [
                        'The method is set per item, or for the whole company when the item has none of its own.',
                        'Inventory and cost of goods sold in the ledger always equal the stock movements the sale wrote.',
                        'A line that names no batch still records which batch it really came from, so reversing the bill puts the goods back on that same batch.',
                    ],
                },
                {
                    type: 'h4',
                    text: 'b) Drafts',
                },
                {
                    type: 'list',
                    items: [
                        'A draft only reserves its stock; nothing leaves the warehouse and nothing reaches the ledger until it is posted.',
                        'A draft is costed on the day it is posted, at what the stock costs that day.',
                        'Deleting a draft releases its reservation; restoring it from Deleted records reserves the stock again.',
                    ],
                },
                {
                    type: 'h4',
                    text: 'c) Returns',
                },
                {
                    type: 'list',
                    items: [
                        'A return credits the customer what they actually paid per unit — after the line discount and the line’s share of the bill discount — not the list price.',
                        'Free units can be returned too. They were delivered at no charge, so they lower the per-unit figure: 45 sold + 5 free at 40 is 50 units at 36 each.',
                        'You can return at most what was delivered (sold + free), across all returns of that bill.',
                    ],
                },
                {
                    type: 'h4',
                    text: 'd) Orders and quotations',
                },
                {
                    type: 'list',
                    items: [
                        'An order is billed once. While a bill made from it exists, a second bill from the same order is refused.',
                        'Reversing that bill reopens the order, so it can be billed again.',
                        'The order, the quotation and the sale made from them use the same total formula, so the amount does not change on conversion.',
                    ],
                },
                {
                    type: 'h4',
                    text: 'e) Common questions',
                },
                {
                    type: 'table',
                    headers: ['Question', 'Answer'],
                    rows: [
                        [
                            '“Choose the cash or bank account”',
                            'A cash sale needs the account the money goes into. Pick it in the Payment card.',
                        ],
                        [
                            '“The payment cannot be more than the bill total”',
                            'Lower the amount received, or check the discount — the bill is smaller than the payment.',
                        ],
                        [
                            '“This expiry date is not in stock”',
                            'The date is no longer on the shelf in that warehouse. Pick one of the listed dates or leave it empty.',
                        ],
                        [
                            '“This sale order has already been billed”',
                            'A bill from that order already exists. Open it, or reverse it first.',
                        ],
                        [
                            'The price did not change after I changed the currency',
                            'The currency has no stored rate. Type the rate and the prices convert.',
                        ],
                    ],
                },
            ],
        },
    ],
}
