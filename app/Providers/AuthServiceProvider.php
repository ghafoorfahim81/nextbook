<?php

namespace App\Providers;

use App\Models\Account\Account;
use App\Models\Account\AccountType;
use App\Models\AccountTransfer\AccountTransfer;
use App\Models\Administration\Branch;
use App\Models\Administration\Brand;
use App\Models\Administration\Category;
use App\Models\Administration\Company;
use App\Models\Administration\Currency;
use App\Models\Administration\Department;
use App\Models\Administration\Designation;
use App\Models\Administration\Size;
use App\Models\Administration\Store;
use App\Models\Administration\UnitMeasure;
use App\Models\Expense\Expense;
use App\Models\Expense\ExpenseCategory;
use App\Models\Inventory\Item;
use App\Models\Ledger\Ledger;
use App\Models\Owner\Owner;
use App\Models\Payment\Payment;
use App\Models\Purchase\Purchase;
use App\Models\Receipt\Receipt;
use App\Models\Role;
use App\Models\Sale\Sale;
use App\Models\User;
use App\Policies\AccountPolicy;
use App\Policies\AccountTransferPolicy;
use App\Policies\AccountTypePolicy;
use App\Policies\BranchPolicy;
use App\Policies\BrandPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\CurrencyPolicy;
use App\Policies\CustomerSupplierPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\DesignationPolicy;
use App\Policies\ExpenseCategoryPolicy;
use App\Policies\ExpensePolicy;
use App\Policies\ItemPolicy;
use App\Policies\OwnerPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PurchasePolicy;
use App\Policies\ReceiptPolicy;
use App\Policies\RolePolicy;
use App\Policies\SalePolicy;
use App\Policies\SizePolicy;
use App\Policies\StorePolicy;
use App\Policies\UnitMeasurePolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // Administration
        Category::class => CategoryPolicy::class,
        Department::class => DepartmentPolicy::class,
        Designation::class => DesignationPolicy::class,
        Branch::class => BranchPolicy::class,
        Brand::class => BrandPolicy::class,
        Store::class => StorePolicy::class,
        Company::class => CompanyPolicy::class,
        Currency::class => CurrencyPolicy::class,
        UnitMeasure::class => UnitMeasurePolicy::class,
        Size::class => SizePolicy::class,

        // Accounts
        Account::class => AccountPolicy::class,
        AccountType::class => AccountTypePolicy::class,

        // Inventory
        Item::class => ItemPolicy::class,

        // Ledgers (customers & suppliers)
        Ledger::class => CustomerSupplierPolicy::class,

        // Owners
        Owner::class => OwnerPolicy::class,

        // Purchases & Sales
        Purchase::class => PurchasePolicy::class,
        Sale::class => SalePolicy::class,

        // Receipts & Payments & Transfers
        Receipt::class => ReceiptPolicy::class,
        Payment::class => PaymentPolicy::class,
        AccountTransfer::class => AccountTransferPolicy::class,

        // Expenses
        ExpenseCategory::class => ExpenseCategoryPolicy::class,
        Expense::class => ExpensePolicy::class,

        // User management
        User::class => UserPolicy::class,
        Role::class => RolePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Global super-admin bypass for all authorization checks.
        Gate::before(function ($user, string $ability) {
            if (method_exists($user, 'hasRole') && $user->roles->contains('slug', 'super-admin')) {
                return true;
            }

            return null;
        });
    }
}


