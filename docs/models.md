### Models

Notation:
- Attributes from `$fillable`
- Casts from `$casts` or `casts()`
- Relationships with return type
- Searchable columns where present

#### `App\Models\Inventory\Item`
- Attributes: `name, code, generic_name, packing, barcode, unit_measure_id, brand_id, category_id, minimum_stock, maximum_stock, colors, size, photo, purchase_price, cost, sale_price, rate_a, rate_b, rate_c, rack_no, fast_search, branch_id, created_by, updated_by`
- Casts: numeric casts for stock/cost/rates; IDs as string
- Relationships:
  - `unitMeasure(): BelongsTo(Administration\UnitMeasure)`
  - `brand(): BelongsTo(Administration\Brand)`
  - `category(): BelongsTo(Administration\Category)`
  - `branch(): BelongsTo(Administration\Branch)`
  - `stocks(): HasMany(Inventory\Stock)`
  - `openings(): HasMany(Inventory\StockOpening)`
- Searchable: name, code, generic_name, packing, barcode, related names, stock limits, rates, branch.name

#### `App\Models\Administration\Department`
- ULID primary key (string, non-incrementing)
- Attributes: `id, name, code, remark, parent_id, created_by, updated_by`
- Casts: `id, created_by, updated_by, parent_id` as string
- Relationships: `parent(): BelongsTo(Department)`
- Searchable: `name, code, remark, parent.name`

#### `App\Models\Administration\Category`
- Attributes: `name, parent_id, created_by, updated_by, ...`
- Relationships: `parent(): BelongsTo(Category)`, `children(): HasMany(Category)`

#### `App\Models\Administration\Brand`
- Attributes include: `name, legal_name, registration_number, logo, email, phone, website, industry, type, address, city, country, branch_id, created_by, updated_by`

#### `App\Models\Administration\Store`
- Attributes: `name, address, branch_id, created_by, updated_by`
- Relationships: `branch(), createdBy(), updatedBy()`

#### `App\Models\Administration\UnitMeasure`
- Attributes: unit name, unit, symbol, description, quantity_id, branch_id, created_by, updated_by
- Relationships: `branch(): BelongsTo`, `quantity(): BelongsTo`

#### `App\Models\Administration\Quantity`
- Attributes: `quantity, unit, symbol, description, created_by, updated_by`
- Relationships: `measures(): HasMany(UnitMeasure)`, `branch(): BelongsTo`

#### `App\Models\Ledger\Ledger`
- Attributes: `name, code, email, phone_no, address, type, branch_id, currency_id, ...`
- Relationships: `currency(): BelongsTo(Currency)`, `branch(): BelongsTo(Branch)`

#### `App\Models\Account\AccountType`
- Attributes: `name, remark`

#### `App\Models\Account\Account`
- Attributes: `name, number, parent_id, account_type_id, branch_id, ...`
- Relationships: `accountType(): BelongsTo`, `parent(): BelongsTo(Account)`, `branch(): BelongsTo(Branch)`

#### `App\Models\Purchase\Purchase`
- Attributes: `number, supplier_id, date, total, ...`
- Relationships: `supplier(): BelongsTo(Ledger)`, `transaction(): BelongsTo(Transaction)`

#### `App\Models\Transaction\Transaction`
- Attributes: `account_id, amount, currency_id, rate, date, type, remark, created_by`
- Relationships: `currency(): BelongsTo(Currency)`, `account(): BelongsTo(Account)`

#### `App\Models\LedgerOpening\LedgerOpening`
- Attributes: `item_id, stock_id` and other opening audit fields
- Notes: used to mark `Stock` records as opening balances
