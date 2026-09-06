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
                    type: 'table',
                    headers: ['Field', 'Why it matters'],
                    rows: [
                        ['Name and code (SKU)', 'A unique code for search, barcode, and preventing duplicates.'],
                        ['Item type', 'Stockable or service. Cannot be changed after a transaction is posted.'],
                        ['Asset account', 'The account that holds this item’s stock value.'],
                        ['Income account', 'The account this item’s sales income is recorded in.'],
                        ['Cost of goods account', 'The account the cost of sale is recorded in.'],
                        ['Default sale price', 'The price that appears automatically on a sales invoice.'],
                        ['Minimum / maximum stock', 'The basis for shortage and dead-stock reports.'],
                        ['Batch / expiry tracking', 'For medicine and dated goods. Cannot be turned off once enabled and a transaction is posted.'],
                        ['Costing method', 'Batch-tracked items use FIFO; the rest use weighted average.'],
                    ],
                },
                {
                    type: 'warn',
                    label: 'Batch tracking is a permanent decision',
                    text: 'If you enable batch tracking for an item and a transaction is then posted, you can no longer turn it off. Enable it only for items that genuinely have an expiry date.',
                },
                {
                    type: 'figure',
                    src: '/images/user-manual/inventory/item-fields.png',
                    caption: 'The item form — general info, accounts, and stock tabs',
                    hint: 'screenshot of the create item page',
                },
            ],
        },
        {
            id: 'fast-entry-opening',
            number: '4',
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
            number: '5',
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
            number: '6',
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
            number: '7',
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
            number: '8',
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
            number: '9',
            title: 'Item in / out records',
            blocks: [
                {
                    type: 'p',
                    text: 'On each item’s view page there are two lists: “In records” (purchase, sales return, inbound transfer, inbound adjustment) and “Out records” (sale, purchase return, outbound transfer, outbound adjustment). These are the full movement history of that item and can be exported.',
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
                        ['SKU', 'The unique stock-keeping code of each item.'],
                        ['Batch / lot', 'A specific consignment of an item with its own expiry and cost.'],
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
            number: '11',
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
            number: '12',
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
