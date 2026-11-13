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
                {
                    type: 'note',
                    label: 'Reversing a purchase that has landed cost',
                    text: 'While a landed cost is posted on a purchase, the purchase cannot be reversed — the message names the landed cost. Reverse the landed cost first, then the purchase.',
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
                        'A cash purchase must name the account the money comes from, and a payment cannot be more than the bill.',
                        'An order is billed once; reversing the purchase made from it reopens the order.',
                        'A purchase that has a landed cost cannot be reversed until that landed cost is reversed.',
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
        {
            id: 'keyboard',
            number: '14',
            title: 'Working fast with the keyboard',
            blocks: [
                {
                    type: 'p',
                    text: 'A purchase is entered the same way as a sale, entirely from the keyboard. The form opens with the cursor on the supplier, and Enter carries you through each line in the order you fill it.',
                },
                {
                    type: 'table',
                    headers: ['Key', 'What it does'],
                    rows: [
                        ['Enter on the supplier', 'Goes to the barcode / item search box.'],
                        ['Tab on the supplier', 'Goes to the item field of the first line.'],
                        ['Enter on the item', 'Takes the highlighted item and goes to the variant — or straight to the quantity when the item has only one variant.'],
                        ['Enter on the variant', 'Goes to the quantity.'],
                        ['Enter on the quantity', 'Goes to the price.'],
                        ['Enter on the price', 'Goes to the item field of the next line. A new line is added when there is none.'],
                        ['Arrow keys', 'Move up, down, left and right between the fields of the lines table. They never change a value.'],
                        ['Delete', 'On a number, or on an item box with nothing typed in it, empties the whole line. While you are typing text, Delete edits the text as usual.'],
                        ['F2 or Alt+B', 'Jumps to the barcode search from anywhere on the form.'],
                        ['Ctrl+S, then Enter', 'Saves. With “confirm before save” on, Ctrl+S opens the confirmation and Enter answers it; with it off, Ctrl+S steps onto the Save button and Enter presses it.'],
                    ],
                },
                {
                    type: 'list',
                    items: [
                        'Scan a barcode and the item is added as a line; scan it again and its quantity goes up by one. The barcode box is on the edit form as well.',
                        'Number fields take no negative figures and do not change when you scroll the mouse wheel over them.',
                        'Enter inside a line never saves the bill — only Ctrl+S followed by Enter does.',
                        'The purchase return and sales return forms walk the returned quantities with Enter in the same way.',
                    ],
                },
            ],
        },
        {
            id: 'payment-and-currency',
            number: '15',
            title: 'Payment, currency, free goods, discounts and rounding',
            blocks: [
                {
                    type: 'h4',
                    text: 'a) Purchase type and payment',
                },
                {
                    type: 'table',
                    headers: ['Purchase type', 'What happens', 'Payment status'],
                    rows: [
                        ['Cash', 'The whole bill is paid now from the cash or bank account you pick. The account is required and cannot be left empty.', 'Paid'],
                        ['Credit', 'A payment dialog opens: enter how much is paid now, the payment method and the cash/bank account. The rest stays owed to the supplier.', 'Paid when the full amount is paid, Partially paid when part of it is, Unpaid when nothing is'],
                        ['On loan', 'Nothing is paid now. The whole bill is owed to the supplier.', 'Unpaid'],
                    ],
                },
                {
                    type: 'list',
                    items: [
                        'The payment cannot be more than the bill. The dialog shows the bill after discounts, what is paid and what remains.',
                        'Only cash and bank accounts are offered for a payment.',
                        'The system works out the bill total from the lines itself: goods − line discounts − bill discount + tax. The form shows the same figure.',
                        'A draft with no payment is shown as unpaid. The edit form keeps the payment you saved and reopens the payment dialog.',
                    ],
                },
                {
                    type: 'h4',
                    text: 'b) Currency and rate',
                },
                {
                    type: 'list',
                    items: [
                        'Item prices are kept in the company currency. A bill in another currency shows each price divided by the rate.',
                        'Changing the currency converts every price on the bill, and changing it back gives exactly the original price. This also works for a currency whose stored rate is zero.',
                        'If a currency has no stored rate, pick the currency first and then type the rate — the prices convert as you type it. A rate of zero is not accepted.',
                        'A price you typed yourself is kept when only the rate of the same currency is changed. The price agreed on an order is kept the same way when the order becomes a bill.',
                    ],
                },
                {
                    type: 'h4',
                    text: 'c) Free goods',
                },
                {
                    type: 'p',
                    text: 'The Free column is for units the supplier gives with the purchase. You do not pay for them, but they do arrive on the shelf, and what you paid is shared across all the units. Buying 45 with 5 free puts 50 on the shelf, and each unit costs the amount paid divided by 50.',
                },
                {
                    type: 'h4',
                    text: 'd) Discounts and tax',
                },
                {
                    type: 'list',
                    items: [
                        'A discount lowers the cost of the goods; it is not booked as income. Each line is received at its value after its own discount and its share of the bill discount.',
                        'A percentage bill discount is taken on the value of the goods, before line discounts. A fixed bill discount comes off the bill once and is shared across the lines by their value.',
                        'Tax paid on a purchase cannot be reclaimed here, so it is part of what the goods cost.',
                    ],
                },
                {
                    type: 'h4',
                    text: 'e) Rounding',
                },
                {
                    type: 'list',
                    items: [
                        'Prices and unit costs keep their own precision (up to four decimals). A bag bought at 3,500 and kept per kilo is held as 71.4286 per kilo, not 71.43.',
                        'Every amount a document posts is rounded to cents: 49 kg at 71.4286 posts 3,500.00, not 3,500.0014. The same applies to sales, returns, transfers, adjustments and item openings, and balances are read at cents.',
                        'Stock columns on the forms are shown in the unit of the line with two decimals.',
                    ],
                },
            ],
        },
        {
            id: 'costing-returns-orders',
            number: '16',
            title: 'Cost of goods received, drafts, returns and orders',
            blocks: [
                {
                    type: 'h4',
                    text: 'a) At what cost the stock arrives',
                },
                {
                    type: 'list',
                    items: [
                        'Each line is received at what it really cost: its value after discounts, plus tax, spread over the units bought and the free units that came with them. A purchase discount therefore lowers the cost of the stock.',
                        'Stock cost is kept in the company currency, per unit of the line’s own measure.',
                        'Landed cost is shared out on that same received value and quantity.',
                    ],
                },
                {
                    type: 'h4',
                    text: 'b) Drafts',
                },
                {
                    type: 'list',
                    items: [
                        'A draft keeps its goods off the ledger and records the incoming stock only as expected. It becomes real stock when the purchase is posted.',
                        'Deleting a draft keeps its entry and its expected stock aside; restoring it from Deleted records brings both back, so it can still be posted.',
                        'There are no separate Approve and Reject buttons on a purchase. It is saved as a draft, posted, or reversed.',
                    ],
                },
                {
                    type: 'h4',
                    text: 'c) Returns',
                },
                {
                    type: 'list',
                    items: [
                        'A purchase return reduces what you owe the supplier by the net value per unit — after discounts — free units included.',
                        'You can return at most what was received.',
                    ],
                },
                {
                    type: 'h4',
                    text: 'd) Reversing a purchase',
                },
                {
                    type: 'list',
                    items: [
                        'A purchase that has a landed cost cannot be reversed until that landed cost is reversed first. The message names the landed cost.',
                        'Reversing a purchase made from an order reopens the order.',
                    ],
                },
                {
                    type: 'h4',
                    text: 'e) Orders and quotations',
                },
                {
                    type: 'list',
                    items: [
                        'An order is billed once. While a purchase made from it is a draft or posted, a second purchase from the same order is refused.',
                        'The order, the quotation and the purchase made from them use the same total formula, so the amount does not change on conversion.',
                    ],
                },
                {
                    type: 'h4',
                    text: 'f) Common questions',
                },
                {
                    type: 'table',
                    headers: ['Question', 'Answer'],
                    rows: [
                        ['“Choose the cash or bank account”', 'A cash purchase needs the account the money comes from. Pick it in the payment card.'],
                        ['“The payment cannot be more than the bill total”', 'Lower the amount paid, or check the discount — the bill is smaller than the payment.'],
                        ['“Landed cost … was added to this purchase. Reverse the landed cost first.”', 'Reverse the landed cost, then reverse the purchase.'],
                        ['“This purchase order has already been billed”', 'A purchase from that order already exists. Open it, or reverse it first.'],
                        ['The price did not change after I changed the currency', 'The currency has no stored rate. Type the rate and the prices convert.'],
                    ],
                },
            ],
        },
        {
            id: 'orders-quotations',
            number: '17',
            title: 'Orders and quotations in detail',
            blocks: [
                {
                    type: 'p',
                    text: 'Purchase orders and purchase quotations are filled in like a purchase invoice, so the keyboard chapter applies to them too. This chapter lists what is specific to them.',
                },
                {
                    type: 'h4',
                    text: 'a) The form',
                },
                {
                    type: 'list',
                    items: [
                        'Variant column: the variant is picked beside the item, so one item bought in several variants is entered once per variant. The column is on by default and can be switched off in the form settings.',
                        'Stock column: shows the stock of the chosen variant in the chosen warehouse, in the unit of the line. An order or a quotation does not move stock.',
                        'Barcode box: scan a barcode and the item is added as a line; scan it again and the quantity goes up by one.',
                        'Enter moves through item, variant, quantity and price and on to the next line. Delete on a number, or on an item box with nothing typed in it, empties the whole line.',
                        'Note: the note sits among the general fields at the top of the form.',
                        'Dates: the delivery date of an order and the validity date of a quotation may be in the future. The expiry date of a line is stored in Gregorian and shown in the company calendar.',
                        'The rate must be greater than zero, and it no longer multiplies the price.',
                        'The Category column is no longer on these forms.',
                        'The list shows the net amount of each order and quotation, after discounts.',
                    ],
                },
                {
                    type: 'h4',
                    text: 'b) The detail page',
                },
                {
                    type: 'list',
                    items: [
                        'It shows every field that was saved: supplier, warehouse, currency and rate, bill discount, note, who created and who last updated it and when, and each line with its batch, expiry date, quantity, free units, price, line discount and total.',
                        'Once an order has been billed, the page says “Completed by purchase” and links to that purchase.',
                        'The Reverse button on a posted order or quotation voids it: it can no longer be turned into a purchase.',
                        'Reversing the purchase made from an order releases the order, so it can be billed again.',
                    ],
                },
                {
                    type: 'note',
                    label: 'Sidebar names',
                    text: 'In the sidebar and in the quick links the documents are listed under short names: Purchase, Order, Return and Quotation, with the quotation last.',
                },
            ],
        },
    ],
}
