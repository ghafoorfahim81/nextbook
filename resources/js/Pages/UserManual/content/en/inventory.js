export default {
    id: 'inventory',
    number: '4',
    title: 'Inventory module guide',
    subtitle: 'Items · variants · batch & expiry · opening · transfers · adjustments · pricing · discount rules · barcodes',
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
                        ['Unit of measure', 'Piece, kilogram, litre, carton. Conversions between units can be defined.'],
                        ['Customer group', 'Wholesale, retail, staff. Used to aim a discount rule at one kind of customer.'],
                    ],
                },
                {
                    type: 'note',
                    label: 'Size and colour are not master data',
                    text: 'You no longer pick a size or colour from a list when registering an item. They are typed as variant attributes on the item itself — see “Variants”.',
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
                        ['Costing method', 'FIFO, LIFO or weighted average. Leave it blank to follow the company default set under Company settings.'],
                        ['Sale price / Purchase price', 'The item’s fallback prices. A variant priced on its own overrides them.'],
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
                {
                    type: 'demo',
                    demo: 'item-variants',
                    caption: 'Try it: rename an attribute, add a variant, make two variants identical to see the duplicate check, star another default, and type a margin to fill the sale price.',
                    labels: {
                        banner: 'Live demo — sample data, nothing is saved',
                        number_locale: 'en',
                        form_title: 'New item with variants',
                        item_name: 'Item name',
                        item_sample: 'T-shirt',
                        attributes: 'Attributes',
                        attributes_default: ['Colour', 'Size'],
                        sample_values: [
                            ['Red', 'Blue'],
                            ['M', 'L'],
                        ],
                        new_attribute: 'Material',
                        sku: 'SKU',
                        barcode: 'Barcode',
                        purchase_price: 'Purchase price',
                        margin: 'Margin %',
                        sale_price: 'Sale price',
                        default_variant: 'Default variant',
                        generate_barcode: 'Generate barcode',
                        remove: 'Remove',
                        add_variant: 'Add variant',
                        duplicate: 'Another variant already has these attributes.',
                        hint: 'The starred variant is used when no specific one is chosen. Each variant has its own SKU, barcode and price. The margin only fills the sale price; it is not stored.',
                        save: 'Save item',
                        saved: 'This is only a demo — nothing was saved.',
                        attributes_list: ['Colour', 'Size'],
                    },
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
            id: 'discount-rules',
            number: '9',
            title: 'Discount rules',
            blocks: [
                {
                    type: 'p',
                    text: 'A discount rule gives a standing discount on a sale line without anybody having to remember it. You set it up once — “10% off everything in the Beverages category”, “50 AFN off this item for wholesale customers” — and the sale form fills the discount in for you when that item is added to an invoice.',
                },
                {
                    type: 'note',
                    label: 'Sales only',
                    text: 'Rules apply to sales. A purchase discount is agreed with the supplier on the bill itself, so there is nothing to set up in advance.',
                },
                { type: 'h4', text: 'What a rule is made of' },
                {
                    type: 'table',
                    headers: ['Field', 'What it does'],
                    rows: [
                        ['Name', 'A label you will recognise later, e.g. “Ramadan promo”. It is shown on the sale line so the cashier can see where the figure came from.'],
                        ['Applies to', 'All items, or one brand, one category, or one item.'],
                        ['Target', 'Which brand, category or item — depending on what you chose above.'],
                        ['Discount type', 'Percentage of the line, or a fixed amount off it.'],
                        ['Value', 'The percentage or the amount.'],
                        ['Customer group', '“All customers”, or one group. A rule aimed at a group only fires for customers in it.'],
                        ['Minimum quantity', 'The rule waits until the line reaches this quantity. Leave empty for no minimum.'],
                        ['Starts / Ends', 'The date window. Leave either side empty for open-ended.'],
                        ['Priority', 'A switch. When two rules are equally specific, the one switched on wins.'],
                        ['Active', 'Turn a rule off without deleting it.'],
                        ['Show on invoice', 'Whether the discount is printed on the customer’s invoice.'],
                    ],
                },
                { type: 'h4', text: 'Which rule wins' },
                {
                    type: 'p',
                    text: 'Only ever one. Discounts never stack. The most specific rule wins, and a rule aimed at the customer’s group beats the same kind of rule without one:',
                },
                {
                    type: 'table',
                    headers: ['Rule', 'Beats'],
                    rows: [
                        ['Named item', 'A category, brand or all-items rule'],
                        ['Category', 'A brand or all-items rule'],
                        ['Brand', 'An all-items rule'],
                        ['Tied to a customer group', 'The same scope without a group'],
                    ],
                },
                {
                    type: 'p',
                    text: 'If two rules are still level after that, the one with Priority switched on wins; if both or neither have it, the larger discount does.',
                },
                { type: 'h4', text: 'On the sale form' },
                {
                    type: 'p',
                    text: 'Add the item to a sale and the discount box fills in by itself, with the rule’s name underneath it. The figure is a suggestion, not a lock: type over it and it is yours — the rules stop touching that line, even if you change the quantity afterwards. A small “Apply …” link appears so you can hand the line back to the rule if you change your mind.',
                },
                {
                    type: 'warn',
                    label: 'Clearing the box means no discount',
                    text: 'If you empty the discount box or set it to zero, the sale saves with no discount on that line. It is treated as your decision and the rule will not put its figure back.',
                },
                {
                    type: 'p',
                    text: 'A quantity change can move the line onto a different rule — a “10 or more” bulk rule takes over as soon as the line reaches 10, and drops away again if you reduce it. Changing the customer re-checks every line that you have not typed over, because a different customer may be in a different group.',
                },
                {
                    type: 'note',
                    label: 'Orders converted into a sale',
                    text: 'When a sale order becomes a sale, the prices and discounts already agreed on the order are kept as they are. Rules do not overwrite them.',
                },
                {
                    type: 'demo',
                    demo: 'discount-rules',
                    caption: 'Try it: change the item, the quantity and the customer group. Only one rule wins each time; the others show why they do not apply or why they lose.',
                    labels: {
                        banner: 'Live demo — sample data, nothing is saved',
                        number_locale: 'en',
                        form_title: 'Discount rules',
                        item: 'Item',
                        qty: 'Quantity',
                        customer_group: 'Customer group',
                        items: [
                            {
                                name: 'Black tea 200 g',
                                price: 280,
                                category: 'Beverages',
                                brand: 'Golden',
                            },
                            {
                                name: 'Green tea 100 g',
                                price: 150,
                                category: 'Beverages',
                                brand: 'Silk',
                            },
                            {
                                name: 'Sugar 1 kg',
                                price: 85,
                                category: 'Grocery',
                                brand: 'Sweet',
                            },
                        ],
                        groups: ['Retail', 'Wholesale'],
                        rules: [
                            {
                                name: 'Ramadan promo',
                                scope: 'all',
                                target: '',
                                type: 'percent',
                                value: 2,
                                min_qty: 0,
                                group: null,
                                priority: false,
                            },
                            {
                                name: 'Beverages 10 %',
                                scope: 'category',
                                target: 'Beverages',
                                type: 'percent',
                                value: 10,
                                min_qty: 0,
                                group: null,
                                priority: false,
                            },
                            {
                                name: 'Golden tea, wholesale',
                                scope: 'item',
                                target: 'Black tea 200 g',
                                type: 'fixed',
                                value: 30,
                                min_qty: 0,
                                group: 1,
                                priority: false,
                            },
                            {
                                name: 'Bulk: 10 or more',
                                scope: 'brand',
                                target: 'Golden',
                                type: 'percent',
                                value: 15,
                                min_qty: 10,
                                group: null,
                                priority: true,
                            },
                        ],
                        scopes: {
                            all: 'All items',
                            category: 'Category',
                            brand: 'Brand',
                            item: 'Item',
                        },
                        all_groups: 'All customers',
                        priority: 'Priority',
                        wins: 'Wins — this is the discount the sale line gets.',
                        beaten: 'Applies, but a more specific rule wins. Discounts never stack.',
                        not_applicable: {
                            scope: 'Does not apply: another item, category or brand.',
                            group: 'Does not apply: it is aimed at another customer group.',
                            qty: 'Does not apply: the quantity is below its minimum.',
                        },
                        line_value: 'Line value',
                        discount: 'Discount',
                        line_total: 'Line total',
                        note: 'Order: a named item beats a category, a category beats a brand, a brand beats all items; a rule tied to the customer’s group beats the same scope without one; then Priority; then the larger discount.',
                    },
                },
            ],
        },
        {
            id: 'barcode',
            number: '10',
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
            number: '11',
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
            id: 'warehouses',
            number: '12',
            title: 'Warehouses',
            blocks: [
                {
                    type: 'p',
                    text: 'A warehouse is a place where stock is held. Stock is always counted per warehouse, and every purchase, sale, transfer, adjustment and opening names the warehouse it moves stock in or out of. Warehouses are listed under Inventory → Setup → Warehouses.',
                },
                {
                    type: 'table',
                    headers: [
                        'Field',
                        'Meaning',
                    ],
                    rows: [
                        [
                            'Name',
                            'Required, and unique within the branch.',
                        ],
                        [
                            'Address',
                            'Optional. Where the warehouse is.',
                        ],
                        [
                            'Main warehouse',
                            'The default warehouse of the branch. When you type an opening amount on an item, the main warehouse is selected for you.',
                        ],
                    ],
                },
                {
                    type: 'list',
                    items: [
                        'The company’s first warehouse is created for you when the company is set up. Each new branch gets its own.',
                        'A branch can have several warehouses; a document can only use warehouses of its own branch.',
                        'On the item page, “Stock by warehouse” shows for each warehouse what is on hand, what is reserved and what is available. Reserved is stock held by draft documents; available is what can still be sold.',
                        'An item can have a default warehouse of its own, used when a document is started for it.',
                    ],
                },
                {
                    type: 'tip',
                    label: 'One warehouse per physical place',
                    text: 'Create a warehouse for every place where you actually count stock — a shop shelf and a back store are two warehouses if you want to know what is in each. Move stock between them with an item transfer, never by editing quantities.',
                },
            ],
        },
        {
            id: 'units-setup',
            number: '13',
            title: 'Units of measure, categories and brands',
            blocks: [
                {
                    type: 'h4',
                    text: 'a) Units of measure',
                },
                {
                    type: 'p',
                    text: 'A unit of measure belongs to a kind of quantity — count, length, area, weight or volume. Each kind has a base unit, and every other measure of that kind is defined by its value against the base unit (a dozen is 12 pieces, a kilogram is 1,000 grams).',
                },
                {
                    type: 'list',
                    items: [
                        'The system suggests the usual measures for each kind: pieces, pair, dozen; centimetre, metre, kilometre, inch; square metre; gram, kilogram, ton; millilitre, litre.',
                        'If yours is not suggested, create a new quantity or a new measure and give its value against the base unit.',
                        'Every item counts its stock in one base unit. A line on an invoice may use any other unit of the same kind, and the system converts it.',
                        'The unit of an item cannot be changed once its opening has been posted.',
                    ],
                },
                {
                    type: 'h4',
                    text: 'b) Categories and brands',
                },
                {
                    type: 'list',
                    items: [
                        'A category groups items for reports, filters and discount rules (food, construction). A brand is the manufacturer’s trade name.',
                        'Both are optional on an item, but a category or a brand is what a discount rule and the bulk pricing page aim at.',
                        'Create them first, so they can be picked from the list; a missing one can also be created without leaving the item form.',
                    ],
                },
                {
                    type: 'h4',
                    text: 'c) Landed-cost categories',
                },
                {
                    type: 'p',
                    text: 'These are the kinds of extra cost you add to the price of imported goods — freight, customs, insurance, clearing. Create them once; the landed cost form offers them as a list. See the landed-cost chapter.',
                },
            ],
        },
        {
            id: 'item-page',
            number: '14',
            title: 'The item list and the item page',
            blocks: [
                {
                    type: 'h4',
                    text: 'a) The item list',
                },
                {
                    type: 'list',
                    items: [
                        'Search by name, code, barcode or SKU. The list shows the code, category, brand, stock and the Final Cost column — the item’s current average cost, after purchase discounts and landed cost.',
                        'A stock chip says In stock, Low stock (at or under the minimum) or Out of stock. Items with expiry tracking also show Expiring soon or Expired.',
                        'Export the list from the toolbar. The label says “Export to excel”.',
                    ],
                },
                {
                    type: 'h4',
                    text: 'b) Activate and deactivate',
                },
                {
                    type: 'p',
                    text: 'Deactivating an item keeps it in every report and past document, but it can no longer be picked on new ones. Its stock, variants and history are untouched, and you can reactivate it at any time. Deactivate rather than delete once an item has been used.',
                },
                {
                    type: 'h4',
                    text: 'c) The item page',
                },
                {
                    type: 'table',
                    headers: [
                        'Area',
                        'What it shows',
                    ],
                    rows: [
                        [
                            'Summary',
                            'Code, unit, accounts, tracking switches, and the current stock and stock value.',
                        ],
                        [
                            'Variants',
                            'Each variant with its SKU, barcode, prices and attributes. A variant that has transactions is locked and cannot be changed or deleted.',
                        ],
                        [
                            'Stock by warehouse',
                            'On hand, reserved and available in every warehouse.',
                        ],
                        [
                            'Openings',
                            'The opening stock entered at go-live, with the batch, expiry and unit cost. A locked opening has been used in transactions and cannot be edited.',
                        ],
                        [
                            'In history / Out history',
                            'Every movement in and out, each row opening the document it came from. Both can be exported.',
                        ],
                    ],
                },
            ],
        },
        {
            id: 'item-sections',
            number: '15',
            title: 'Every part of the item form',
            blocks: [
                {
                    type: 'p',
                    text: 'The item form is grouped into sections, and the company’s business type decides which of them are shown. This chapter explains every field so none of them is a mystery.',
                },
                {
                    type: 'h4',
                    text: 'a) Behaviour switches',
                },
                {
                    type: 'table',
                    headers: [
                        'Switch',
                        'What it does',
                    ],
                    rows: [
                        [
                            'Active',
                            'An inactive item cannot be picked on new documents.',
                        ],
                        [
                            'Stockable',
                            'Untick for services that hold no stock.',
                        ],
                        [
                            'Sellable / Purchasable',
                            'Whether the item appears on sales documents, purchase documents, or both.',
                        ],
                        [
                            'Sold by weight',
                            'The quantity is entered as a weight, not a count.',
                        ],
                        [
                            'Allow negative stock',
                            'Permits issuing more than is on hand. Use with care.',
                        ],
                        [
                            'Show in POS',
                            'Lists the item in the point-of-sale catalogue.',
                        ],
                    ],
                },
                {
                    type: 'h4',
                    text: 'b) Tracking',
                },
                {
                    type: 'table',
                    headers: [
                        'Tracking',
                        'What it does',
                    ],
                    rows: [
                        [
                            'Batch tracked',
                            'Stock is kept per batch number, each with its own cost. Permanent once a transaction is posted.',
                        ],
                        [
                            'Expiry tracked',
                            'Stock is kept per expiry date. Permanent once a transaction is posted.',
                        ],
                        [
                            'Serial / IMEI tracked',
                            'Each unit is recorded individually with its own serial number and cost.',
                        ],
                    ],
                },
                {
                    type: 'h4',
                    text: 'c) Stock control',
                },
                {
                    type: 'list',
                    items: [
                        'Minimum and maximum stock: the shortage report warns when stock falls to the minimum. The maximum cannot be below the minimum.',
                        'Reorder quantity and lead time (days between ordering and receiving): used for reorder points.',
                        'Default warehouse and rack number: where the item normally lives.',
                        'Storage zone: ambient, chilled or frozen.',
                    ],
                },
                {
                    type: 'h4',
                    text: 'd) Pricing',
                },
                {
                    type: 'list',
                    items: [
                        'Purchase price and sale price: the item’s fallback prices. A variant priced on its own overrides them.',
                        'Margin %: fills the sale price from the purchase price (or average cost) plus the margin, only when no sale price is set. It is a helper and is not stored.',
                        'Rates A, B and C: extra price levels, for example for wholesale customers. Pricing method decides how the price is arrived at.',
                        'A sale price below the purchase price is refused or warned about, depending on preferences.',
                    ],
                },
                {
                    type: 'h4',
                    text: 'e) Physical',
                },
                {
                    type: 'p',
                    text: 'Weight and dimensions (length, width, height), each with its unit. They are not decoration: a landed cost can be split across the items of a purchase by weight or by volume, and that needs these figures on every item involved.',
                },
                {
                    type: 'h4',
                    text: 'f) Sourcing and compliance',
                },
                {
                    type: 'table',
                    headers: [
                        'Field',
                        'What it is for',
                    ],
                    rows: [
                        [
                            'Manufacturer, country of origin, HS code',
                            'Reference data for imports and customs.',
                        ],
                        [
                            'Warranty (months)',
                            'How long the warranty runs.',
                        ],
                        [
                            'Shelf life (days)',
                            'Used to fill the expiry date automatically on receipt.',
                        ],
                        [
                            'Minimum shelf life on receipt (%)',
                            'A delivery arriving with less life left than this is rejected.',
                        ],
                        [
                            'Requires prescription, controlled substance',
                            'Flags for pharmacies.',
                        ],
                    ],
                },
                {
                    type: 'h4',
                    text: 'g) Accounts',
                },
                {
                    type: 'p',
                    text: 'The asset account carries the item’s stock value, the income account receives its sales revenue, and the cost account receives the cost of goods sold. Defaults are filled in from the company’s chart of accounts.',
                },
                {
                    type: 'note',
                    label: 'Fast search',
                    text: 'A short alias you can type on a sales or purchase line to find the item quickly.',
                },
            ],
        },
        {
            id: 'opening-details',
            number: '16',
            title: 'Fast entry and fast opening in detail',
            blocks: [
                {
                    type: 'h4',
                    text: 'a) Fast item entry',
                },
                {
                    type: 'list',
                    items: [
                        'The screen is for the essential fields only. Accounts, photo, variants and reorder levels are filled in later from the item form.',
                        'The code and barcode are generated for you. Typing a name selects the default unit; typing an opening amount selects the main warehouse.',
                        'Arrow keys move between cells, Enter jumps to the next row and Tab to the next column. On the expiry cell, Enter opens the calendar. A new row appears automatically as you type.',
                        'Completely empty rows are skipped, so you never have to delete the extra ones.',
                        'Column settings hides the columns you do not need; the choice is remembered in this browser only.',
                        'Every problem is shown against its row (name required, unit required, warehouse missing, duplicate name) and nothing is saved until they are fixed.',
                    ],
                },
                {
                    type: 'h4',
                    text: 'b) Final cost',
                },
                {
                    type: 'p',
                    text: 'Final cost is what one unit really costs you: the purchase price plus freight, duty and clearing. Opening stock is valued at it. Leave it empty and the purchase price is used.',
                },
                {
                    type: 'h4',
                    text: 'c) Fast opening',
                },
                {
                    type: 'list',
                    items: [
                        'It sets the stock you already hold, for items that have no opening yet. An item disappears from the list once its opening is posted.',
                        'Opening stock is a one-time go-live figure. After it, stock only moves through purchase, sale, transfer and adjustment.',
                        'Leave the amount empty on anything you are not counting; those rows are skipped.',
                        'Batch and expiry accept typing only on items tracked that way.',
                        'Only the rows on the current page are saved. Finish a page, save it, then move to the next.',
                        'Before posting, a summary shows how many items and what total value you are about to post. Posting writes the stock movements and one balanced opening-balance entry, and cannot be undone from this screen.',
                    ],
                },
                {
                    type: 'warn',
                    label: 'Check the quantities and the warehouse',
                    text: 'A wrong opening cannot be edited once it has been used in a transaction. Count carefully, pick the correct warehouse, and remember that the unit and the batch and expiry tracking are fixed once the opening is posted.',
                },
            ],
        },
        {
            id: 'transfer-details',
            number: '17',
            title: 'Item transfers in detail',
            blocks: [
                {
                    type: 'table',
                    headers: [
                        'State',
                        'What it means',
                    ],
                    rows: [
                        [
                            'Draft',
                            'Saved, not yet sent. Nothing has moved.',
                        ],
                        [
                            'In transit',
                            'Sent with “Complete transfer”: the goods have left the source warehouse and are held as goods in transit until the destination receives them.',
                        ],
                        [
                            'Received / completed',
                            'The destination has confirmed what arrived and the stock is added there.',
                        ],
                        [
                            'Cancelled',
                            'Called off. Everything the transfer posted, sending and receiving, is reversed.',
                        ],
                    ],
                },
                {
                    type: 'list',
                    items: [
                        'Source and destination must be different warehouses.',
                        'The same item, variant and batch cannot be on two lines.',
                        'Changing the source warehouse refreshes the item details from the new warehouse, clears the batches and removes items that have no stock there; the form tells you which.',
                        'Receiving: the destination enters what actually arrived for each line. It cannot be more than was sent, and anything missing is booked as inventory shrinkage; the form shows the shortfall before you confirm.',
                        'A transfer to a warehouse of another branch is visible to that branch too, shown as “In transit to …”, with a receiving panel for the quantities that arrived.',
                        'A completed or cancelled transfer cannot be edited, and a completed one cannot be deleted.',
                    ],
                },
                {
                    type: 'h4',
                    text: 'Transfer cost',
                },
                {
                    type: 'p',
                    text: 'If moving the goods cost money (freight between your own warehouses), enter the transfer cost, the cash or bank account it was paid from, and the expense account. It defaults to the Item Transfer Expense account, so the cost appears on its own line in the profit and loss.',
                },
            ],
        },
        {
            id: 'adjustment-details',
            number: '18',
            title: 'Stock adjustments in detail',
            blocks: [
                {
                    type: 'list',
                    items: [
                        'Each line has a direction — In increases stock, Out decreases it — a quantity and a reason.',
                        'Cost is worked out automatically from the costing method (FIFO, LIFO or weighted average); you do not type it.',
                        'An Out line cannot be more than what is on hand.',
                        'Only a draft can be edited or deleted. A posted adjustment can be reversed.',
                    ],
                },
                {
                    type: 'table',
                    headers: [
                        'Direction',
                        'Reasons offered',
                    ],
                    rows: [
                        [
                            'Out',
                            'Damage / breakage, expiry, theft / shrinkage, loss / missing, stock count correction (down), internal use / consumption, sample / promotion / giveaway, wastage / scrap, quality rejection.',
                        ],
                        [
                            'In',
                            'Stock count correction (up), found / recovered, opening stock entry, production / assembly output, surplus / overage.',
                        ],
                    ],
                },
                {
                    type: 'tip',
                    label: 'After a physical count',
                    text: 'Enter the difference for each item as a Stock count correction, up or down, so the reason is the same everywhere and the report of adjustments reads clearly.',
                },
            ],
        },
        {
            id: 'pricing-details',
            number: '19',
            title: 'Bulk pricing in detail',
            blocks: [
                {
                    type: 'ol',
                    items: [
                        'Open Inventory → Pricing. The newest products are listed first; search to reach any other.',
                        'Narrow the list with “Apply to”: all items, one category or one brand.',
                        'Tick the rows you want, or select all rows on the page.',
                        'Choose a bulk action, a direction and a value, then Apply. Nothing is saved yet.',
                        'Review the changed prices. Revert undoes the unsaved changes.',
                        'Press Save and confirm. Documents already made keep the price they were saved with.',
                    ],
                },
                {
                    type: 'table',
                    headers: [
                        'Bulk action',
                        'What it does',
                    ],
                    rows: [
                        [
                            'Change by %',
                            'Raises or lowers each price by a percentage. Enter the figure as a positive number and pick Increase or Decrease.',
                        ],
                        [
                            'Change by amount',
                            'Raises or lowers each price by a fixed amount.',
                        ],
                        [
                            'Set margin %',
                            'Sets each price from the item’s cost plus the margin. Rows with no cost are skipped, because there is nothing to measure a margin against.',
                        ],
                        [
                            'Round to nearest',
                            'Rounds prices to a nearest figure you choose.',
                        ],
                    ],
                },
                {
                    type: 'note',
                    label: 'Below cost',
                    text: 'A price lower than the item’s cost is marked “Below cost” so you notice it before saving.',
                },
                {
                    type: 'demo',
                    demo: 'pricing',
                    caption: 'Try it: tick rows, choose an action and Apply, then Revert or Save. The gift-wrapping row has no cost, so “Set margin %” skips it.',
                    labels: {
                        banner: 'Live demo — sample data, nothing is saved',
                        number_locale: 'en',
                        form_title: 'Bulk pricing',
                        action: 'Bulk action',
                        percent: 'Change by %',
                        amount: 'Change by amount',
                        margin: 'Set margin %',
                        round: 'Round to nearest',
                        direction: 'Direction',
                        increase: 'Increase',
                        decrease: 'Decrease',
                        value: 'Value',
                        apply: 'Apply',
                        revert: 'Revert',
                        select_all: 'Select all rows',
                        item: 'Item',
                        cost: 'Cost',
                        price: 'Sale price',
                        margin_col: 'Margin',
                        below_cost: 'Below cost',
                        save: 'Save',
                        changed: 'price(s) changed',
                        nothing_changed: 'No prices have been changed yet.',
                        skipped: 'row(s) skipped — no cost to measure a margin against.',
                        reverted: 'Unsaved prices were reverted.',
                        saved: 'Prices saved. (Demo — nothing was really saved.)',
                        items: [
                            {
                                name: 'Black tea 200 g',
                                cost: 200,
                                price: 280,
                            },
                            {
                                name: 'Basmati rice 5 kg',
                                cost: 1100,
                                price: 1450,
                            },
                            {
                                name: 'Cooking oil 5 L',
                                cost: 900,
                                price: 980,
                            },
                            {
                                name: 'Sugar 1 kg',
                                cost: 70,
                                price: 85,
                            },
                            {
                                name: 'Gift wrapping',
                                cost: 0,
                                price: 50,
                            },
                        ],
                    },
                },
            ],
        },
        {
            id: 'barcode-details',
            number: '20',
            title: 'Barcode printing in detail',
            blocks: [
                {
                    type: 'ol',
                    items: [
                        'Open Inventory → Barcode print and add products by name, code or barcode. Only products with a saved barcode can be printed; a product with variants prints one label per variant, each with its own barcode.',
                        'Set the number of labels per product. The total for one print has a limit, and the screen tells you how many labels remain.',
                        'Choose which texts to print on the label: product name, code, price and the variant.',
                        'Choose the barcode size (small 36 mm, medium 48 mm, large 62 mm) and the sheet: A4 or A5, portrait or landscape, or label rolls of one to five columns. For a custom sheet, set the label width and height, the spacing between labels, and the margins.',
                        'Choose the symbology. Code 128 accepts any text and is the safe default. EAN-13, EAN-8 and UPC need a valid check digit — they are retail codes you cannot invent.',
                        'Check the preview, which shows the labels exactly as they will print, then print.',
                    ],
                },
                {
                    type: 'tip',
                    label: 'A heading',
                    text: 'The optional heading is printed once at the top of the sheet — a shop name or a batch reference.',
                },
            ],
        },
        {
            id: 'landed-cost',
            number: '21',
            title: 'Landed cost',
            blocks: [
                {
                    type: 'p',
                    text: 'Landed cost adds the extra costs of bringing goods in — freight, customs, insurance, clearing — to the cost of the items on one or more purchase bills, so the stock is valued at what it really cost. It is under Inventory → Landed costs.',
                },
                {
                    type: 'ol',
                    items: [
                        'Press New and pick the purchase bill, or several bills of the same shipment. Their items load automatically. A bill can have only one landed cost.',
                        'Choose the cash or bank account the extra costs were paid from, and the currency and rate.',
                        'In the category breakdown, enter each extra cost against its landed-cost category (freight, customs and so on). Their total is the total additional cost.',
                        'Choose how to share it across the items: by value, by quantity, by weight, by volume, equally, or manually.',
                        'Press Allocate. The preview shows, for each item, its base unit cost, its share (%), the amount allocated and its landed unit cost.',
                        'The whole cost must be allocated, no more and no less. The form shows the remainder or the excess and will not post until they match.',
                        'Save as a draft, then Post. Posting adds each item’s share to its cost, recalculates the average and writes the accounting entry.',
                    ],
                },
                {
                    type: 'table',
                    headers: [
                        'Method',
                        'Shares the cost in proportion to',
                    ],
                    rows: [
                        [
                            'By value',
                            'The value of each line on the bill.',
                        ],
                        [
                            'By quantity',
                            'The number of units.',
                        ],
                        [
                            'By weight',
                            'The item’s weight. Every item needs a weight, all in the same unit.',
                        ],
                        [
                            'By volume',
                            'The item’s length × width × height. Every item needs its dimensions, all in the same unit.',
                        ],
                        [
                            'Equal',
                            'The same amount to every line.',
                        ],
                        [
                            'Manual',
                            'Whatever amounts you type per line.',
                        ],
                    ],
                },
                {
                    type: 'list',
                    items: [
                        'The cost columns are in the company’s home currency.',
                        'A posted landed cost can be reversed, with an optional reason. The entry is cancelled and the allocated cost is taken back out of the item costs. A reversed landed cost cannot be edited.',
                        'A purchase that carries a landed cost cannot be reversed until the landed cost is reversed first.',
                        'If the weight or dimensions are missing, the form names the items and asks you to fill them in or to choose another method.',
                    ],
                },
                {
                    type: 'formula',
                    text: 'Landed unit cost = base unit cost + the line’s share of the extra cost ÷ quantity',
                },
                {
                    type: 'demo',
                    demo: 'landed-cost',
                    caption: 'Try it: change the extra costs and switch the allocation method — see how each item’s landed unit cost moves. With Manual, the total must match before it can be posted.',
                    labels: {
                        banner: 'Live demo — sample data, nothing is saved',
                        number_locale: 'en',
                        form_title: 'Landed cost',
                        costs: 'Extra costs of the shipment',
                        categories: ['Freight', 'Customs'],
                        total_cost: 'Total additional cost',
                        method: 'Allocation method',
                        methods: {
                            by_value: 'By value',
                            by_quantity: 'By quantity',
                            by_weight: 'By weight',
                            by_volume: 'By volume',
                            equal: 'Equal',
                            manual: 'Manual',
                        },
                        method_notes: {
                            by_value: 'In proportion to each line’s value on the bill.',
                            by_quantity: 'In proportion to the number of units.',
                            by_weight: 'In proportion to weight. Every item needs a weight, all in one unit.',
                            by_volume: 'In proportion to length × width × height. Every item needs its dimensions.',
                            equal: 'The same amount to every line.',
                            manual: 'Type the amount for each line; the total must equal the extra costs.',
                        },
                        item: 'Item',
                        qty: 'Quantity',
                        base_cost: 'Base unit cost',
                        weight: 'Weight (kg)',
                        volume: 'Volume (m³)',
                        allocated: 'Allocated',
                        landed_unit: 'Landed unit cost',
                        fully_allocated: 'The whole cost is allocated — it can be posted.',
                        not_allocated: 'Not fully allocated. Remaining:',
                        items: [
                            {
                                name: 'Cooking oil (carton)',
                                qty: 100,
                                cost: 1000,
                                weight: 20,
                                volume: 0.05,
                            },
                            {
                                name: 'Basmati rice (bag)',
                                qty: 50,
                                cost: 1450,
                                weight: 25,
                                volume: 0.04,
                            },
                            {
                                name: 'Black tea (box)',
                                qty: 200,
                                cost: 280,
                                weight: 0.5,
                                volume: 0.002,
                            },
                        ],
                    },
                },
            ],
        },
        {
            id: 'glossary',
            number: '22',
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
                        ['Final cost', 'The item list column that shows the item’s current average cost, after purchase discounts and landed cost.'],
                        ['Stock in transit', 'Goods that have left the source warehouse but are not yet recorded at the destination.'],
                        ['Reorder point (minimum stock)', 'The figure at which the shortage report warns when stock falls to it.'],
                    ],
                },
            ],
        },
        {
            id: 'reports',
            number: '23',
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
                        ['Variant-wise', 'On-hand and movement split by variant, for items that have more than one.'],
                        ['Fast / slow movers', 'The turnover speed of each item.'],
                        ['Zero on hand', 'Items that have moved but are now at zero.'],
                        ['Maximum stock', 'Items sitting above their maximum level.'],
                    ],
                },
                { type: 'h4', text: 'Narrowing a report to one variant' },
                {
                    type: 'p',
                    text: 'Every inventory report has an Item filter and, next to it, a Variant filter. Choose an item first: the Variant list then offers only that item’s variants. Leave the variant empty to see the item as a whole.',
                },
                {
                    type: 'note',
                    label: 'The same filter on sales and purchases',
                    text: 'The Sales and Purchases reports carry it too, on their item-wise view, and each row names the variant that was sold or bought. Exports include that column.',
                },
                {
                    type: 'p',
                    text: 'Changing the item clears the variant, because a variant belongs to one item — if it did not, the report would come back empty for no visible reason.',
                },
            ],
        },
        {
            id: 'troubleshooting',
            number: '24',
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
                        ['The price filled in is not the one I expected', 'The line takes the variant’s own sale price first, then the item’s. Check the variant you picked — switching variant re-prices the line.'],
                        ['A discount appeared on a sale line by itself', 'A discount rule matched. Its name is shown under the box; type over the figure to overrule it, or turn the rule off under Discount rules.'],
                        ['A discount I deleted keeps coming back', 'It should not. Clearing the box is treated as “no discount” and saves as zero — if you see otherwise, the line was re-added rather than edited.'],
                        ['A report is empty after choosing a variant', 'That variant has no movement in the chosen dates or warehouse. Clear the variant to see the whole item.'],
                        ['Reversing a purchase left the average cost too high', 'It should not — reversing a receipt takes its cost back out of the average. If a figure still looks wrong, check for a later adjustment or landed cost on the same item.'],
                    ],
                },
            ],
        },
    ],
}
