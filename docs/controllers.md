### Controllers

This section summarizes public actions, expected inputs, outputs, and side effects.

#### `App\Http\Controllers\SearchController`
- `POST /api/search/{resourceType}` → `search(Request $request, string $resourceType)`
  - Body: `{ search: string (>=2), fields?: string[], limit?: number (1..100), ...additionalParams }`
  - Returns: `{ success: boolean, data: any[], meta?: {resource_type, search_term, total, limit} }`
  - Errors: 422 validation; 500 with error message.
- `GET /api/search/resource-types` → `getResourceTypes()`
  - Returns map of resource type keys to descriptions.

#### `App\Http\Controllers\Inventory\ItemController`
- `GET /items` → `index(Request)`
  - Query: `perPage, sortField, sortDirection, search`
  - Returns Inertia view `Inventories/Items/Index` with `items` as `ItemResource` collection.
- `GET /items/create` → `create()` → Inertia `Inventories/Items/Create`.
- `POST /items` → `store(ItemStoreRequest)`
  - Validates item fields, optional `photo` upload, optional `openings[]` to create opening `Stock` and `StockOpening` records in a transaction.
  - Redirects to `items.create` (stay) or `items.index` with flash success.
- `GET /items/{item}` → `show(Request, Item)` → `ItemResource` JSON.
- `GET /items/{item}/edit` → `edit(Request, Item)` → Inertia `Inventories/Items/Edit` with `ItemResource`.
- `PUT/PATCH /items/{item}` → `update(ItemUpdateRequest, Item)`
  - Validates, handles optional `photo` update, deletes old openings/stocks, recreates from `openings[]`.
  - Redirects to `items.index` success.
- `DELETE /items/{item}` → `destroy(Request, Item)`
  - Deletes related openings and stocks; redirects back.

#### `App\Http\Controllers\Administration\DepartmentController`
- `GET /departments` → `index(Request)` → Inertia `Administration/Departments/Index` with `items` as `DepartmentResource` collection.
- `POST /departments` → `store(DepartmentStoreRequest)` → `DepartmentResource` JSON.
- `GET /departments/{department}` → `show(Request, Department)` → `DepartmentResource` JSON.
- `PUT/PATCH /departments/{department}` → `update(DepartmentUpdateRequest, Department)` → `DepartmentResource` JSON.
- `DELETE /departments/{department}` → `destroy(Request, Department)` → 204 No Content.
- `GET /departments/parents` → `getParents()` → `[ {id, name} ]` JSON.

#### `App\Http\Controllers\Administration\BrandController`
- `GET /brands` → Inertia `Administration/Brands/Index` with `BrandResource` collection.
- `POST /brands` → `store(BrandStoreRequest)` → redirect to `brands.index` success.
- `GET /brands/{brand}` → `show(Request, Brand)` → `BrandResource` JSON.
- `PUT/PATCH /brands/{brand}` → `update(BrandUpdateRequest, Brand)` → redirect back.
- `DELETE /brands/{brand}` → `destroy(Request, Brand)` → redirect back.

#### `App\Http\Controllers\Administration\CategoryController`
- `GET /categories` → Inertia `Administration/Categories/Index` with `CategoryResource` collection.
- `POST /categories` → `store(CategoryStoreRequest)` → redirect success.
- `GET /categories/{category}` → `show(Request, Category)` → `CategoryResource` JSON.
- `PUT/PATCH /categories/{category}` → `update(CategoryUpdateRequest, Category)` → redirect back.
- `DELETE /categories/{category}` → `destroy(Request, Category)` → redirect back.

#### `App\Http\Controllers\Administration\UnitMeasureController`
- `GET /unit-measures` → Inertia `Administration/UnitMeasures/Index` with `UnitMeasureResource` collection.
- `POST /unit-measures` → `store(Request)`
  - Creates or finds `Quantity` (metric) and creates new `UnitMeasure` under it. Validates positive `unit`.
  - Redirect to index with flash status.
- `GET /unit-measures/{unitMeasure}` → `show(Request, UnitMeasure)` → `UnitMeasureResource` JSON.
- `PUT/PATCH /unit-measures/{unitMeasure}` → `update(Request, UnitMeasure)`
  - Ensures `Quantity` exists/updated; updates measure fields; redirects with success.
- `DELETE /unit-measures/{unitMeasure}` → `destroy(Request, UnitMeasure)` → redirect success.

#### `App\Http\Controllers\Ledger\LedgerController`
- `GET /ledgers?type={customer|supplier|...}` → `index(Request)` → Inertia with `LedgerResource` collection filtered by type.
- `POST /ledgers` → `store(LedgerStoreRequest)` → `LedgerResource` JSON.
- `GET /ledgers/{ledger}` → `show(Request, Ledger)` → `LedgerResource` JSON.
- `PUT/PATCH /ledgers/{ledger}` → `update(LedgerUpdateRequest, Ledger)` → `LedgerResource` JSON.
- `DELETE /ledgers/{ledger}` → `destroy(Request, Ledger)` → 204 No Content.

#### `App\Http\Controllers\Ledger\SupplierController`
- `GET /suppliers` → Inertia `Ledgers/Suppliers/Index` with `LedgerResource` collection (`type=supplier`).
- `GET /suppliers/create` → Inertia `Ledgers/Suppliers/Create`.
- `POST /suppliers` → `store(LedgerStoreRequest)` → Creates supplier; optional opening transaction; redirect success.

#### `App\Http\Controllers\Ledger\CustomerController`
- `GET /customers` → Inertia `Ledgers/Customers/Index` with `LedgerResource` collection (`type=customer`).
- `GET /customers/create` → Inertia `Ledgers/Customers/Create`.
- `POST /customers` → `store(LedgerStoreRequest)` → Creates customer; optional opening transaction; redirect success.

#### `App\Http\Controllers\Purchase\PurchaseController`
- `GET /purchases` → Inertia `Purchase/Purchases/Index` with `PurchaseResource` collection.
- `GET /purchases/create` → Inertia `Purchase/Purchases/Create`.
- `POST /purchases` → `store(PurchaseStoreRequest)` → `PurchaseResource` JSON.
- `GET /purchases/{purchase}` → `show(Request, Purchase)` → `PurchaseResource` JSON.
- `PUT/PATCH /purchases/{purchase}` → `update(PurchaseUpdateRequest, Purchase)` → `PurchaseResource` JSON.
- `DELETE /purchases/{purchase}` → `destroy(Request, Purchase)` → 204 No Content.

#### `App\Http\Controllers\Inventory\FastOpeningController`
- `GET /item-fast-opening` → `index()` → Inertia `Inventories/Items/FastOpening` with `items`.
- `POST /item-fast-opening` → `store(FastOpeningRequest)` → Creates/updates opening `Stock` and `StockOpening` per row (transactional).
