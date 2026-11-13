### Enums

#### `App\Enums\Locale`
- Values: `fa`, `en`, `pa`
- `getLabel()`: i18n label for each case
- `values()`: array of string values

#### `App\Enums\BusinessType`
- Values: `pharmacy_shop`, `pharma_distribution`, `supermarket_grocery`, `accounting`, `automobile`, `billing_general`, `book_author_publisher`, `garment_size_wise`, `hotel_resorts`, `jewellery`, `mobile_serial_wise`, `pharma_manufacturing_batch`, `ply_carpet_feet_mtr_wise`, `restaurant_table_wise`
- Helpers: `getLabel()`, `values()`

#### `App\Enums\CalendarType`
- Values: `shamsi`, `miladi`
- Helpers: `getLabel()`, `values()`

#### `App\Enums\SalesPurchaseType`
- Values: `cash`, `credit`, `on_loan`
- Helpers: `getLabel()`, `values()`

#### `App\Enums\UserStatus`
- Values: `active`, `inactive`, `blocked`
- Used by `ledgers.status`, which replaced the `is_active` boolean. Only `active` parties are offered by the pickers.
- Helpers: `getLabel()`, `values()`

#### `App\Enums\WorkingStyle`
- Values: `normal`, `secondary`
- Helpers: `getLabel()`, `values()`
