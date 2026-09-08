export default {
    id: 'inventory',
    number: '4',
    title: 'Inventory module guide',
    subtitle: 'Items · batch & expiry · opening · transfers · adjustments · pricing · barcodes',
    summary:
        'The inventory module tracks the stock of every item in every warehouse. Purchases and sales move stock; transfers, adjustments, and openings are the direct tools for correcting it.',
    chapters: [
        {
            id: 'purpose',
            number: '1',
            title: 'Purpose of this module',
            blocks: [
                {
                    type: 'p',
                    text: 'The inventory module is the foundation of the sales and purchase modules. Each item has a record that decides whether it is stockable or a service, what unit it is measured in, which accounting accounts it is wired to, and how it is costed.',
                },
                {
                    type: 'table',
                    headers: ['Item type', 'Has stock?', 'Example'],
                    rows: [
                        ['Stockable', 'Yes — purchases and sales move stock', 'Oil, rice, cement'],
                        ['Non-stock / service', 'No — only for income or expense', 'Freight, installation fee'],
                    ],
                },
            ],
        },
        {
            id: 'master-data',
            number: '2',
            title: 'Master data before registering items',
            blocks: [
                {
                    type: 'p',
                    text: 'Before registering items, create this master data under Administration so you can pick from a list when registering an item:',
                },
                {
                    type: 'table',
                    headers: ['Item', 'Use'],
                    rows: [
                        ['Category', 'Grouping items for reports and filters (food, construction).'],
                        ['Brand', 'The manufacturer’s trade name.'],
                        ['Size', 'For items that come in several sizes.'],
                        ['Unit of measure', 'Piece, kilogram, litre, carton. Conversions between units can be defined.'],
                    ],
                },
            ],
        },
        {
            id: 'item-fields',
            number: '3',
            title: 'Item field reference',
            blocks: [
                {
                    type: 'p',
                    text: 'An item is the catalogue entry. It carries the code, type, unit, accounts and the fields the company’s business type switches on. What is actually sold, scanned and priced is a variant of it — see the next chapter.',
                },
                {
                    type: 'table',
                    headers: ['Field', 'Why it matters'],
                    rows: [
                        ['Code', 'Generated automatically. Identifies the item internally and never changes.'],
                        ['Item type', 'Stockable item or service. Cannot be changed after a transaction is posted.'],
                        ['Unit of measure', 'The base unit every quantity is counted in.'],
                        ['Asset account', 'Holds this item’s stock value on the balance sheet.'],
                        ['Income account', 'Where its sales revenue is booked.'],
                        ['Cost of goods account', 'Where the cost is booked when it is sold.'],
                        ['Batch / expiry tracking', 'Groups stock into delivery lots with their own batch number, expiry and landed cost. Permanent once a transaction is posted.'],
                        ['Serial tracking', 'Records every unit individually with its own serial number and warranty.'],
                        ['Costing method', 'Batch/expiry-tracked items use FIFO; the rest use weighted average.'],
                    ],
                },
                {
                    type: 'note',
                    label: 'The form adapts to your trade',
                    text: 'The company business type decides which fields and sections appear. A pharmacy sees batch and expiry; a computer shop sees variants and serials; a bakery sees expiry only. Change it under Company settings.',
                },
                {
                    type: 'warn',
                    label: 'Batch tracking is a permanent decision',
                    text: 'Once batch tracking is enabled for an item and a transaction is posted, it can no longer be turned off. Enable it only for goods that genuinely carry an expiry or lot code.',
                },
            ],
        },
        {
            id: 'variants',
            number: '4',
            title: 'Variants — what is actually sold',
            blocks: [
                {
                    type: 'p',
                    text: 'Every item has at least one variant. The variant carries the SKU, barcode, sale price, purchase price and minimum/maximum stock — not the item.',
                },
                {
                    type: 'h4', text: 'Simple products',
                },
                {
                    type: 'p',
                    text: 'A bottle of water or a cake has one variant. Its single row on the item form holds the SKU, barcode and price. You never think of it as “a variant” — it is just where those fields live now.',
                },
                {
                    type: 'h4', text: 'Products with choices',
                },
                {
                    type: 'p',
                    text: 'A laptop sold in several RAM/storage builds, or a shirt in several colours and sizes, gets one variant per combination. Add an attribute column (RAM, Colour, Size…) and one row per combination; each row has its own SKU, barcode and price.',
                },
                {
                    type: 'table',
                    headers: ['Field', 'Meaning'],
                    rows: [
                        ['Default (star)', 'Used when no specific variant is chosen — barcode scans and price lookups fall back to it.'],
                        ['Attributes', 'The choices that make a variant distinct: RAM, storage, colour, size.'],
                        ['Margin %', 'A helper that fills the sale price from purchase price + margin. Not saved.'],
                        ['Generate barcode', 'Fills the barcode field with a fresh unique code.'],
                    ],
                },
                {
                    type: 'note',
                    label: 'Colour and size',
                    text: 'These are ordinary variant attributes now — you type them as attribute columns, not as separate tracking switches on the item.',
                },
            ],
        },
        {
            id: 'fast-entry-opening',
            number: '5',
            title: 'Fast entry and fast opening',
            blocks: [
                { type: 'h4', text: 'Fast item entry' },
                {
                    type: 'p',
                    text: 'When you want to enter dozens of items at once, use “Fast entry”. You see a table with empty rows; fill in each item’s name and unit. An empty row is not saved. Add each item’s remaining details later from the item edit page.',
                },
                { type: 'h4', text: 'Fast stock opening' },
                {
                    type: 'p',
                    text: 'To enter the company’s opening stock at setup, use “Fast opening”. You enter the starting quantity of each item by warehouse. For batch items, also enter the batch, expiry, and cost per batch.',
                },
                {
                    type: 'warn',
                    label: 'Opening only once',
                    text: 'Fast opening is only for transferring initial stock. After real work begins, every stock change must go through purchase, sale, transfer, or adjustment — not opening.',
                },
            ],
        },
        {
            id: 'transfers',
            number: '6',
            title: 'Transferring stock between warehouses',
            blocks: [
                {
                    type: 'p',
                    text: 'When you move stock from one warehouse to another, create an “Item transfer” document. Source and destination must be different.',
                },
                {
                    type: 'ol',
                    items: [
                        'Inventory → Item transfer → New.',
                        'Select the source and destination warehouses.',
                        'Enter items and quantities. For batch items, the same batch and expiry are kept.',
                        'Post. Stock leaves the source and is added to the destination. The company’s total stock value does not change.',
                    ],
                },
                {
                    type: 'note',
                    label: 'Two-step transfer',
                    text: 'If the move takes time, you can post the transfer and then “Complete” it once the goods reach the destination. Until then, the stock is “in transit”.',
                },
            ],
        },
        {
            id: 'adjustments',
            number: '7',
            title: 'Stock adjustments',
            blocks: [
                {
                    type: 'p',
                    text: 'When a physical count of a warehouse does not match the system figure — due to wastage, loss, breakage, or found goods — create a “Stock adjustment” document.',
                },
                {
                    type: 'ol',
                    items: [
                        'Inventory → Stock adjustment → New.',
                        'Select the warehouse.',
                        'For each item, enter the direction (in or out), the adjustment quantity, and the reason.',
                        'Post. The system corrects the stock quantity and value and records the other side in the adjustment expense/income account.',
                    ],
                },
                {
                    type: 'warn',
                    label: 'Write a precise reason',
                    text: 'A stock adjustment affects company profit and is examined in an audit. Always write a clear reason (result of annual count, water-damage wastage, ...).',
                },
            ],
        },
        {
            id: 'pricing',
            number: '8',
            title: 'Bulk pricing',
            blocks: [
                {
                    type: 'p',
                    text: 'The “Pricing” page lets you review and update the sale price of several items at once. This way the cashier does not have to type a new price on every invoice and prices stay consistent.',
                },
                {
                    type: 'figure',
                    src: '/images/user-manual/inventory/pricing.png',
                    caption: 'The bulk item pricing page',
                    hint: 'screenshot of the pricing page',
                },
            ],
        },
        {
            id: 'barcode',
            number: '9',
            title: 'Barcode printing',
            blocks: [
                {
                    type: 'p',
                    text: 'From “Barcode print”, select items and print the number of labels you need. Then scan the same code in the sale and purchase forms to add items faster.',
                },
            ],
        },
        {
            id: 'in-out-records',
            number: '10',
            title: 'Item in / out history',
            blocks: [
                {
                    type: 'p',
                    text: 'Each item’s view page has two tabs: In History (purchase, sales return, inbound transfer, inbound adjustment, opening) and Out History (sale, purchase return, outbound transfer, outbound adjustment). Together they are the item’s full movement history and can be exported.',
                },
                {
                    type: 'p',
                    text: 'Click any row to open the document it came from — the purchase, sale, transfer or adjustment. Opening rows have no document and are not clickable.',
                },
            ],
        },
        {
            id: 'glossary',
            number: '11',
            title: 'Glossary of confusing fields',
            blocks: [
                {
                    type: 'table',
                    headers: ['Term', 'Meaning'],
                    rows: [
                        ['Item', 'The catalogue entry — code, type, unit, accounts.'],
                        ['Variant', 'What is actually sold and scanned. Holds the SKU, barcode and price. Every item has at least one.'],
                        ['Default variant', 'The starred one, used when no specific variant is chosen.'],
                        ['SKU', 'The unique stock-keeping code — on the variant.'],
                        ['Batch / lot', 'A specific consignment of a variant with its own expiry and cost.'],
                        ['FIFO', 'First in, first out; the oldest batch is sold first.'],
                        ['Weighted average', 'A costing method that updates the average purchase price based on quantity.'],
                        ['Stock in transit', 'Goods that have left the source warehouse but are not yet recorded at the destination.'],
                        ['Reorder point (minimum stock)', 'The figure at which the shortage report warns when stock falls to it.'],
                    ],
                },
            ],
        },
        {
            id: 'reports',
            number: '12',
            title: 'Inventory reports',
            blocks: [
                {
                    type: 'table',
                    headers: ['Report', 'What it shows'],
                    rows: [
                        ['Current stock', 'The quantity of each item in each warehouse right now.'],
                        ['Stock movement', 'All ins and outs over a time period.'],
                        ['Stock value', 'The value of stock at cost.'],
                        ['Stock shortage', 'Items below minimum stock.'],
                        ['Batch and expiry', 'Batches nearing their expiry date.'],
                        ['Fast / slow movers', 'The turnover speed of each item.'],
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
                        ['Item stock is negative', 'A sale was posted before a purchase. Check document dates and fix the order if needed.'],
                        ['Cannot turn off batch tracking', 'The item has a posted transaction. This setting is locked afterwards.'],
                        ['Stock value does not match the financial report', 'An adjustment or landed cost may still be a draft, or a manual journal entry hit the asset account.'],
                        ['Item not found in sales', 'It may be inactive or deleted, or its type is service and it has no stock in the chosen warehouse.'],
                    ],
                },
            ],
        },
    ],
}
