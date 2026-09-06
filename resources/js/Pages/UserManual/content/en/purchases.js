export default {
    id: 'purchases',
    number: '3',
    title: 'Purchase module guide',
    subtitle: 'Purchase quotation · order · purchase invoice · return · landed cost',
    summary:
        'The purchase module records everything you buy from a supplier. Posting a purchase increases stock and records a payable to the supplier, and import costs can be added to the real stock value.',
    chapters: [
        {
            id: 'purpose',
            number: '1',
            title: 'Purpose of this module',
            blocks: [
                {
                    type: 'p',
                    text: 'The purchase module is the mirror of the sales module. It manages the procurement chain: from asking a supplier for a price to receiving goods in the warehouse and recording the payable. Its core document is the purchase invoice.',
                },
                {
                    type: 'p',
                    text: 'When a purchase invoice is posted: stock of stockable items increases in the receiving warehouse, the company becomes a debtor of the supplier, and if needed the item’s weighted-average cost is recalculated.',
                },
                {
                    type: 'table',
                    headers: ['User', 'What they do'],
                    rows: [
                        ['Procurement officer', 'Create the purchase order, receive goods, post the purchase invoice'],
                        ['Warehouse keeper', 'Confirm received quantity and quality, pick the right warehouse'],
                        ['Accountant', 'Check the supplier payable, record landed cost, record payments'],
                    ],
                },
            ],
        },
        {
            id: 'prerequisites',
            number: '2',
            title: 'Prerequisites',
            blocks: [
                {
                    type: 'ol',
                    items: [
                        'Receiving warehouse: the warehouse purchased goods arrive into must be defined.',
                        'Items: every item must be registered in inventory, with an asset account and a cost account.',
                        'Suppliers: register suppliers under “Customers & suppliers”.',
                        'Landed cost categories: if you have import costs such as freight and customs, create these categories in advance.',
                    ],
                },
                {
                    type: 'warn',
                    label: 'Do not fake opening stock with a purchase',
                    text: 'To bring in the company’s opening stock at setup, use “Fast opening” in the inventory module, not a purchase invoice. The purchase invoice is only for real purchases.',
                },
            ],
        },
        {
            id: 'document-types',
            number: '3',
            title: 'Purchase documents and their order',
            blocks: [
                {
                    type: 'flow',
                    steps: ['Purchase quotation', 'Purchase order', 'Purchase invoice', 'Purchase return'],
                },
                {
                    type: 'table',
                    headers: ['Document', 'When to use it', 'Effect on stock and accounts'],
                    rows: [
                        ['Purchase quotation', 'Record the price a supplier quoted, for comparison.', 'None.'],
                        ['Purchase order', 'A formal order to the supplier. Also the basis for allocating landed cost.', 'No accounting effect.'],
                        ['Purchase invoice', 'Goods received and a payable is created.', 'Post: stock up, payable to the supplier written.'],
                        ['Purchase return', 'Faulty or excess goods sent back to the supplier.', 'Post: stock down, payable to the supplier reduced.'],
                    ],
                },
                {
                    type: 'tip',
                    label: 'Why start from a purchase order?',
                    text: 'If you have import costs (freight, customs), they must be recorded against a purchase order so they can be spread over the cost of that order’s items. So for imports, always start from a purchase order.',
                },
            ],
        },
        {
            id: 'lifecycle',
            number: '4',
            title: 'The purchase invoice lifecycle',
            blocks: [
                {
                    type: 'flow',
                    steps: ['Draft', 'Posted', 'Reversed'],
                },
                {
                    type: 'table',
                    headers: ['State', 'What it means'],
                    rows: [
                        ['Draft', 'The invoice is saved but stock and the payable are not affected.'],
                        ['Posted', 'Stock has entered the warehouse and the payable to the supplier is written. It is locked.'],
                        ['Reversed', 'A mirror document has returned stock and the payable to their previous state.'],
                    ],
                },
                {
                    type: 'warn',
                    label: 'Wrong item unit',
                    text: 'If you pick the wrong unit for an item (say “piece” instead of “carton”), the stock quantity and value break, and fixing it after posting requires a reversal. Double-check the unit before posting.',
                },
            ],
        },
        {
            id: 'field-reference',
            number: '5',
            title: 'Purchase invoice field reference',
            blocks: [
                { type: 'h4', text: 'a) Header' },
                {
                    type: 'table',
                    headers: ['Field', 'Description'],
                    rows: [
                        ['Invoice number', 'The system’s internal number.'],
                        ['Supplier bill number', 'The number on the supplier’s paper invoice. Important for matching and audit.'],
                        ['Date', 'The date goods were received / the payable was recorded.'],
                        ['Supplier', 'The purchase party. Their balance increases after posting.'],
                        ['Receiving warehouse', 'The warehouse goods enter.'],
                        ['Currency and rate', 'The invoice currency and the rate to the base currency.'],
                    ],
                },
                { type: 'h4', text: 'b) Item lines' },
                {
                    type: 'table',
                    headers: ['Field', 'Description'],
                    rows: [
                        ['Item', 'Pick from the list or scan the barcode.'],
                        ['Quantity and unit', 'The quantity received. Check the unit carefully.'],
                        ['Unit purchase price', 'The price you pay. The basis for cost.'],
                        ['Batch and expiry', 'Required for batch-tracked items. Each batch has its own value and expiry.'],
                        ['Discount', 'The supplier’s discount on that line.'],
                    ],
                },
                { type: 'h4', text: 'c) Footer' },
                {
                    type: 'list',
                    items: [
                        'Charges on the invoice: small costs split directly across this invoice.',
                        'Amount paid: if you pay now, a payment is created automatically.',
                        'Notes: an internal note.',
                    ],
                },
                {
                    type: 'figure',
                    src: '/images/user-manual/purchases/field-reference.png',
                    caption: 'The purchase invoice form',
                    hint: 'screenshot of the create purchase page',
                },
            ],
        },
        {
            id: 'step-by-step',
            number: '6',
            title: 'Step by step: posting a purchase',
            blocks: [
                {
                    type: 'ol',
                    items: [
                        'Open Purchase → New purchase (or convert an open purchase order).',
                        'Select the supplier and the receiving warehouse.',
                        'Enter the supplier bill number and date.',
                        'Add items: item, quantity, unit, purchase price, and for batch items, batch and expiry.',
                        'Review the footer: subtotal, discount, tax.',
                        'If you pay now, enter the amount and the paying account.',
                        '“Save draft” to review, or “Post” to finalise.',
                        'If you have import costs, go to the next chapter and record landed cost.',
                    ],
                },
            ],
        },
        {
            id: 'landed-cost',
            number: '7',
            title: 'Landed cost',
            blocks: [
                {
                    type: 'p',
                    text: 'Freight, customs, insurance, and similar costs you pay to bring goods in are part of the real stock value. If you record them as scattered expenses, the item cost looks lower than it is and sales profit is miscalculated. Landed cost spreads these amounts across the purchased items.',
                },
                {
                    type: 'ol',
                    items: [
                        'Open Inventory → Landed cost → New.',
                        'Select one or more related purchase orders. Their items come in automatically and cannot be changed here.',
                        'Enter the costs (freight, customs, ...) and specify an account and amount for each.',
                        'Choose the allocation method: by value, quantity, weight, or manual.',
                        'Make sure the total allocated exactly equals the total cost; otherwise posting is not possible.',
                        'Post. The system adds each item’s share to its cost and recalculates the weighted average.',
                    ],
                },
                {
                    type: 'formula',
                    text: 'Real unit cost = (purchase price + landed-cost share) ÷ quantity',
                },
                {
                    type: 'warn',
                    label: 'Order matters',
                    text: 'Record landed cost after posting the purchase invoice. If the item was sold before the landed cost was recorded, adjusting the cost gets complicated.',
                },
            ],
        },
        {
            id: 'returns',
            number: '8',
            title: 'Purchase returns',
            blocks: [
                {
                    type: 'ol',
                    items: [
                        'Open Purchase → Purchase return → New.',
                        'Select the supplier and, where possible, the original purchase invoice.',
                        'Enter the returned quantity per item and the warehouse goods leave from.',
                        'Post. Stock goes down and the payable to the supplier is reduced.',
                    ],
                },
                {
                    type: 'note',
                    label: 'Return after landed cost',
                    text: 'If landed cost had been applied to the returned item, the system also removes its proportional share from the stock value.',
                },
            ],
        },
        {
            id: 'rules',
            number: '9',
            title: 'Rules the system enforces',
            blocks: [
                {
                    type: 'list',
                    items: [
                        'Batch-tracked items cannot be posted without a batch and expiry.',
                        'The total landed-cost allocation must exactly equal the total cost.',
                        'The receiving warehouse must belong to the same branch.',
                        'A posted invoice is locked; correction only via reversal.',
                        'A purchase return cannot exceed the purchased quantity.',
                    ],
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
                        ['Supplier bill number', 'The number on the supplier’s paper invoice; different from the system’s internal number.'],
                        ['Landed cost', 'The side costs of bringing goods in, added to the stock cost.'],
                        ['Weighted average', 'A costing method where each new purchase updates the average based on quantity and price.'],
                        ['Allocation method', 'The rule that decides how landed cost is spread across different items (value, quantity, weight).'],
                        ['Open purchase order', 'An order not yet fully converted to an invoice.'],
                    ],
                },
            ],
        },
        {
            id: 'example',
            number: '11',
            title: 'Worked example: an import with costs',
            blocks: [
                {
                    type: 'p',
                    text: 'Noor Co. buys 100 cartons of oil at 1,000 AFN per carton from a foreign supplier and pays 20,000 AFN in freight and customs.',
                },
                {
                    type: 'ol',
                    items: [
                        'Create a purchase order: 100 cartons of oil × 1,000 = 100,000 AFN.',
                        'Convert the order to a purchase invoice and post it. Stock: 100 cartons, payable to supplier: 100,000.',
                        'Create a landed cost, select that purchase order, enter 20,000 freight/customs, and choose the “by quantity” allocation method.',
                        'Post. Each carton takes a 200 AFN cost share.',
                        'Real cost per carton: 1,200 AFN. Now sales profit is calculated correctly.',
                    ],
                },
            ],
        },
        {
            id: 'reports',
            number: '12',
            title: 'Purchase-related reports',
            blocks: [
                {
                    type: 'table',
                    headers: ['Report', 'What it shows'],
                    rows: [
                        ['Purchase report', 'Purchases by date, supplier, or item.'],
                        ['Supplier statement', 'Invoices, payments, and the balance of one supplier.'],
                        ['Payable aging', 'Outstanding payables to suppliers by time bucket.'],
                        ['Open purchase bills', 'Purchases not yet fully paid.'],
                        ['Stock value', 'Warehouse value after landed cost is included.'],
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
                        ['Cannot post landed cost', 'The allocation total does not equal the cost total. Recheck the numbers.'],
                        ['Item cost did not change', 'The landed cost may still be a draft, or the wrong purchase order was selected.'],
                        ['Supplier payable doubled', 'You probably posted both a purchase invoice and a manual journal entry. Reverse one.'],
                        ['Stock value went negative', 'An item was sold beyond stock and then a purchase was posted. Fix the document order by date.'],
                    ],
                },
            ],
        },
    ],
}
