<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Models\Purchase\Purchase;
use App\Models\Account\Account;
use App\Models\Ledger\Ledger;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'user' => 'App\Models\User',
            'role' => 'App\Models\Role',
            'permission' => 'App\Models\Permission',
            'account' => 'App\Models\Account',
            'ledger' => 'App\Models\Ledger',
            'purchase' => 'App\Models\Purchase\Purchase',
            'sale' => 'App\Models\Sale\Sale',
            'expense' => 'App\Models\Expense\Expense',
            'income' => 'App\Models\Income\Income',
            'transfer' => 'App\Models\Transfer\Transfer',
            'adjustment' => 'App\Models\Adjustment\Adjustment',
            'opening' => 'App\Models\Inventory\StockOpening',
            'stock_out' => 'App\Models\Inventory\StockOut',
            'item' => 'App\Models\Inventory\Item',
        ]);
    }
}
